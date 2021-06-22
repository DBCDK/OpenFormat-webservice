<?php
/**
 *
 * This file is part of Open Library System.
 * Copyright © 2009, Dansk Bibliotekscenter a/s,
 * Tempovej 7-11, DK-2750 Ballerup, Denmark. CVR: 15149043
 *
 * Open Library System is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Open Library System is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Open Library System.  If not, see <http://www.gnu.org/licenses/>.
 */

//-----------------------------------------------------------------------------
require_once('OLS_class_lib/webServiceServer_class.php');
require_once('OLS_class_lib/format_class.php');
require_once('OLS_class_lib/rediscluster_class.php');
require_once("formatObject.php");

//-----------------------------------------------------------------------------
define(DEBUG_ON, FALSE);

//-----------------------------------------------------------------------------
class openFormat extends webServiceServer {

  private $redis_cache;

  /**
   * constructor
   * Initialize base class with configuration
   * Set cluster cache from config section
   */
  public function __construct() {
    webServiceServer::__construct('openformat.ini');
    $cachesettings = $this->config->get_section('rediscache');
    $this->redis_cache = new RedisClusterCache($cachesettings['redis_host'], $cachesettings['redis_port'], $cachesettings['redis_expire']);
  }

  /**
   * Make a cache key from pids and given display
   *
   * @param array $pids
   * @param $display
   *
   * @return string
   */
  private function make_cache_key(array $pids, $display) {
    $cache_key = "OF";
    foreach ($pids as $pid) {
      $cache_key .= $pid;
    }
    $cache_key .= $display;

    return md5($cache_key);
  }

  /**
   * Format from one or more pid(s)
   *
   * @param $param
   *    OLS object
   *
   * @return stdClass
   *    OLS object
   */
  public function formatObject($param) {
    // check if outputFormat is a json object (customDisplay)
    if (json_decode($param->outputFormat->_value)) {
      $param->customDisplay = $param->outputFormat;
    }
    // split pid(s) into an array
    $pids = $this->split_pid($param->pid->_value);
    // make a cache key
    $cache_key = $this->make_cache_key($pids, $param->outputFormat->_value);
    // check the cache
    if ($response = $this->redis_cache->get($cache_key)) {
      // @TODO should we log a cache hit?
      verboseJson::log(STAT, array(
          'Format' => $param->outputFormat->_value,
          'FromCache' => "yes",
          'Total' => sprintf('%01.3f', $this->watch->splittime('Total')),
        )
      );
      return $response;
    }

    // base xml (wrapper for output)
    $base_xml = "<collection>";
    // object to get dkabm and marcxchange from corepo
    $formatObject = new formatObject($this->config);
    // iterate pids
    foreach ($pids as $pid) {
      $result = $formatObject->getContent($pid, $this->watch);
      if (!$result['success']) {
        // @TODO if one fails .. should we continue with the rest ?
        return $this->send_error($result['xml_string']);
      }
      $original_xml = $result['xml_string'];
      $base_xml .= $this->prep_xml($pid, $original_xml);
    }
    // end tag - xml holds objects like: <collection><object/><object/><object/></collection>
    $base_xml .= "</collection>";

    $param->originalData = new stdClass();
    $param->originalData->_namespace = $this->xmlns['of'];
    $prepped_obj = $this->prep_obj($param, $base_xml);
    if (empty($prepped_obj)) {
      // logging is done in prep_obj method - @TODO return a meaningfull message
      return $this->send_error("An error occured");
    }

    $param->originalData->_value = $prepped_obj;

    $response = $this->format($param, FALSE);
    // set the cache
    $this->redis_cache->set($cache_key, $response);

    return $response;
  }

  /**
   * Do an object with identifier and dkabm+marcxchange (original) xml
   * We need the identifier here for openformat to parse a pid in response
   *
   * @param $pid
   * @param $original_xml
   *
   * @return string
   */
  private function prep_xml($pid, $original_xml) {
    return $prepped_xml = '<object xmlns="http://oss.dbc.dk/ns/opensearch"><identifier>' . $pid . '</identifier>' . $original_xml . '</object>';

  }

  /**
   * Split given pid on comma. Or wrap as array if one only
   *
   * @param $pid string
   *  one or more pid(s) eg. "870970-basis:47955653,870970-basis:50808874"
   *
   * @return array|false|string[]
   */
  private function split_pid($pid) {
    if (strpos($pid, ',') !== FALSE) {
      $pid = explode(',', $pid);
    }
    else {
      $pid = array($pid);
    }
    return $pid;
  }

  /**
   * Convert given xml to an OLS object for further handling
   *
   * @param $param
   * @param $base_xml
   *
   * @return null
   */
  private function prep_obj($param, $base_xml) {
    // this one fakes opensearch reply - we need the <identifier> part since we cannot get pid from marcxchange
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = FALSE;

    if ($dom->loadXML($base_xml)) {
      return $this->xmlconvert->xml2obj($dom);
    }
    else {
      verboseJson::log(ERROR, array(
          'Format' => $param->outputFormat->_value,
          'Message' => "failed to parse xml: " . $base_xml,
        )
      );
    }
    return NULL;
  }

  /**
   * \brief Handles the request and set up the response
   *
   * @param stdClass $param
   * @param bool $cache_me (not used for now)
   *
   * @return \stdClass
   */

  public function format($param, $cache_me = TRUE) {
    $res = new stdClass();
    if (!$this->aaa->has_right('openformat', 500)) {
      $res->error->_value = 'authentication_error';
      return $this->send_error('authentication_error');
    }
    else {
      $param->trackingId->_value = verboseJson::set_tracking_id('of', $param->trackingId->_value);
      $param->trackingId->_namespace = $this->xmlns['of'];
      if (is_array($param->originalData)) {
        foreach ($param->originalData as $key => $od) {
          $form_req[] = &$param->originalData[$key];
        }
      }
      else {
        $form_req[] = &$param->originalData;
      }

      // @TODO check if record is set here like:
      // $param->originalData->_value->collection->_value->object->_value
      // above returns a collection when <marcx:collection> is set
      // above returns a record when <dkabm:record> is set
      // if both are empty: can we return without formatting ????

      $formatRecords = new formatRecords($this->config->get_section('setup'), $this->xmlns['of'], $this->objconvert, $this->xmlconvert, $this->watch);
      $formatted = $formatRecords->format($form_req, $param);
    }
    for ($i = 0; $i < count($formatted); $i++) {
      $fkey = key($formatted[$i]);
      $res->{$fkey}[$i] = &$formatted[$i]->$fkey;
    }
    $ret = new stdClass();
    $ret->formatResponse->_value = &$res;
    $ret->formatResponse->_namespace = $this->xmlns['of'];
    if (!($dump_format = $this->dump_timer)) {
      $dmp_format = '%s';
    }
    $size_upload = 0;
    $size_download = 0;
    foreach ($formatRecords->get_status() as $r_c) {
      $size_upload += $r_c['size_upload'];
      $size_download += $r_c['size_download'];
    }

    // @TODO verboseJson does take an array as message - FIX it with timers etc.
    verboseJson::log(STAT, array(
        'Format' => $param->outputFormat->_value,
        'bytesIn' => $size_upload,
        'bytesOut' => $size_download,
        'js_server' => sprintf('%01.3f', $this->watch->splittime('js_server')),
        'Total' => sprintf('%01.3f', $this->watch->splittime('Total')),
      )
    );

    //var_dump($ret); var_dump($param); die();
    return $ret;

  }

  public function send_error($errormessage) {
    $ret = new stdClass();
    $ret->formatResponse->_value = $errormessage;
    $ret->formatResponse->_namespace = $this->xmlns['of'];

    return $ret;
  }

}

/*
 * MAIN
 */

$ws = new openFormat();
$ws->handle_request();
?>


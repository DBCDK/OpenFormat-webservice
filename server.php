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

//-----------------------------------------------------------------------------
define(DEBUG_ON, FALSE);

//-----------------------------------------------------------------------------
class openFormat extends webServiceServer {

  public function __construct() {
    webServiceServer::__construct('openformat.ini');
  }

  /**
   * \brief Handles the request and set up the response
   */

  public function format($param) {
    if (!$this->aaa->has_right('openformat', 500)) {
      $res->error->_value = 'authentication_error';
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

      $formatRecords = new formatRecords($this->config->get_section('setup'), $this->xmlns['of'], $this->objconvert, $this->xmlconvert, $this->watch);
      $formatted = $formatRecords->format($form_req, $param);
    }
    for ($i = 0; $i < count($formatted); $i++) {
      $fkey = key($formatted[$i]);
      $res->{$fkey}[$i] = &$formatted[$i]->$fkey;
    }
    $ret->formatResponse->_value = &$res;
    $ret->formatResponse->_namespace = $this->xmlns['of'];
    if (!($dump_format = $this->dump_timer)) {
      $dmp_format = '%s';
    }
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
        'Total' => sprintf('%01.3f', $this->watch->splittime('Total')))
    );

    //var_dump($ret); var_dump($param); die();
    return $ret;

  }

}

/*
 * MAIN
 */

$ws = new openFormat();
$ws->handle_request();
?>


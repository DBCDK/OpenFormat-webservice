<?php

/**
 * @file - format a single pid
 *
 */
require_once 'OLS_class_lib/memcache_class.php';
require_once('OLS_class_lib/xmlconvert_class.php');
require_once('OLS_class_lib/objconvert_class.php');


class formatObject {

  private $curl;

  private $config;

  private $curlstatus = array();

  /**
   * Constructor
   *
   * @param inifile $config
   * @param array $param
   */
  public function __construct($config) {
    $this->curl = new curl();
    $this->config = $config;
  }

  public function getContent($pid, &$watch = NULL) {
    $parsed_datastreams = $this->set_datastreams($pid);
    if(!$parsed_datastreams['success']){
      return $parsed_datastreams;
    }

    // curl->get
    /** @var stopwatch $watch */
    $watch->start('content-service');
    $content_response = $this->call_corepo($pid, $parsed_datastreams['datastreams']);
    $watch->stop('content-service');
    verboseJson::log(STAT, array(
        'content_Service' => $watch->splittime('content-service'),
      )
    );

    // check if all went well - if commonData is set we are good to go
    if($this->curlstatus['commonData']['success'] === FALSE) {
      $result['success'] = FALSE;
      $result['xml_string'] = 'URL: ' . $this->curlstatus['commonData']['status']['url'] . ' RETURNS error code: ' . $this->curlstatus['commonData']['status']['http_code'];
      return $result;
    }

    // all is well for further processing
    $result['success'] = TRUE;
    $result['xml_string'] = $content_response;
    return $result;
  }

  /**
   * Set the datastreams to get from corepo - check if pid is an article (catalogue 870971)
   * if so we want the local datastream included
   *
   * @param $pid
   *
   * @return array
   *  the datastreams to handle
   */
  private function set_datastreams($pid){
    // split pid in (catalogue, faust)
    $catalogue_faust = explode(":", $pid);
    // there should be 2 entries (catalogue, faust) - eg. [870971-avis, 38802399]
    if(count($catalogue_faust) !== 2){
      return array(
        'success' => FALSE,
        'xml_string' => "weird pid: $pid -- abort"
      );
    }

    // there are multiple data streams in corepo - always get commonData stream
    $datastreams = array("commonData");
    // if catalogue is article or review (870971) we want the local datastream also
    if(strpos($catalogue_faust[0], "870971") === 0){
      $datastreams[] = "localData.".$catalogue_faust[0];
    }

    return array(
      'success' => TRUE,
      'datastreams' => $datastreams
    );
  }

  /**
   * Get content from corepo
   * @param $pid
   *  id of manifestation to look for
   * @param array $datastreams
   *  the datastreams we would like to get
   * @return array
   */
  private function call_corepo($pid, array $datastreams){
    /** @var inifile $config */
    $content_url_ini = $this->config->get_value('service_url', 'content_service');
    $pid = trim($pid);

    $content_response = array();
    // get data for each datastream
    foreach ($datastreams as $datastream) {
      $content_url = str_replace(array('$pid','$datastream'), array($pid, $datastream), $content_url_ini);
      $this->curl->set_url($content_url);
      $tmp_content = $this->curl->get();
      // check curl status
      $status = $this->curl->get_status();
      // only add if all is good
      if ($status['http_code'] === 200) {
        $content_response[$datastream] = $tmp_content;
      }
      else{
        // something went wrong - set a flag and log
        $this->curlstatus[$datastream]['success'] = FALSE;
        $this->curlstatus[$datastream]['status'] = $status;
        verboseJson::log(ERROR, array(
            'ERROR - could not get datastream' => $datastream,
            'content_service_url' => $status['url'],
            'http_code' => $status['http_code']
          )
        );
      }
    }

    return $content_response;
  }
}
?>

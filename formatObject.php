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
  /**
   * Constructor
   * @param inifile $config
   * @param array $param
   */
  public function __construct($config) {
    $this->curl = new curl();
    $this->config = $config;
  }

  public function getContent($pid, &$watch=null){
    /** @var stopwatch $watch */
    $watch->start('content-service');
    /** @var inifile $config */
    $content_url = $this->config->get_value('service_url', 'content_service');
    $content_url .= "=" . $pid;
    $this->curl->set_url($content_url);
    $content_json = $this->curl->get();

    $php_content = json_decode($content_json, TRUE);
    $xml_string = $php_content['dataStream'];
    $watch->stop('content-service');

    return $xml_string;
  }
}

?>

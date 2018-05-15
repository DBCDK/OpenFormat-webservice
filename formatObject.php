<?php

/**
 * @file - format a single pid
 *
 */
require_once 'OLS_class_lib/memcache_class.php';


class formatObject {
  private $curl;
  private $cache;
  private $config;
  /**
   * Constructor
   * @param inifile $config
   * @param array $param
   */
  public function __construct($config, $param) {
    $this->curl = new curl();
    $this->config = $config;

    $this->getContent($param->pid->_value);
  }

  protected function getContent($pid){
    /** @var inifile $config */
    $content_url = $this->config->get_value('content_url', 'content_service');
    $content_url .= $pid;
    $this->curl->set_url($content_url);
    $content_xml = $this->curl->get();

    print($content_xml);
    die();
  }

}

?>

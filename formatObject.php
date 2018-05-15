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
    $this->cache = new cache($config['cache_host'], $setup['cache_port'], $setup['cache_expire']);
    $this->curl = new curl();
    $this->config = $config;

    $this->getContent($param->pid->_value);
  }

  protected function getContent($pid){
    /** @var inifile $config */
    $content_url = $config->get_value('content_url', 'content_service');
    $content_xml = $this->curl->get($content_url . $pid);

    print($content_xml);
    die();
  }

}

?>

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
require_once 'OLS_class_lib/memcache_class.php';
require_once 'OLS_class_lib/cql2solr_class.php';

//-----------------------------------------------------------------------------
define(DEBUG_ON, FALSE);

//-----------------------------------------------------------------------------
class openFormat extends webServiceServer {
    protected $curl;
    protected $transaction_id;
    protected $fake_multi;
    protected $js_server_url;

    public function __construct(){
        webServiceServer::__construct('openformat.ini');

        if (!$timeout = (integer) $this->config->get_value('curl_timeout', 'setup'))
            $timeout = 20;
        $this->curl = new curl();
        if (!($this->fake_multi = (integer) $this->config->get_value('fake_multi', 'setup')))
            $this->fake_multi = 1;
        $this->js_server_url = $this->config->get_value('js_server', 'setup');
        for ($i = 0; $i < $this->fake_multi; $i++) {
            $this->curl->set_option(CURLOPT_TIMEOUT, $timeout, $i);
            $this->curl->set_option(CURLOPT_HTTPHEADER, array('Content-Type: text/xml; charset=UTF-8'), $i);
            $this->curl->set_url($this->js_server_url, $i);
        }
/*
*/
    }

    /**
        \brief Handles the request and set up the response
    */

    public function formatSingleManifestation($param) {
        if (!$this->aaa->has_right('openformat', 500))
            $res->error->_value = 'authentication_error';
        else {
            if (!($this->transaction_id = $param->transactionId->_value))
                $this->transaction_id = (string) mt_rand();
            $form_req->formatSingleManifestationRequest->_value = $param;
            $res->bibliotekdkFullDisplay->_namespace = $this->xmlns['ofo'];
            $res->bibliotekdkFullDisplay->_value->displayInformation->_namespace = $this->xmlns['ofo'];
            $res->bibliotekdkFullDisplay->_value->displayInformation->_value = 
               $this->format_rec($form_req, $param->outputFormat->_value);
               //$this->format_rec($param->originalData->_value, $param->outputFormat->_value);
        }
        //var_dump($res); var_dump($param); die();
        $ret->formatSingleManifestationResponse->_value = $res;
        return $ret;

    }

    /** \brief Echos config-settings
     *
     */
    public function show_info() {
        echo '<pre>';
        echo 'version             ' . $this->config->get_value('version', 'setup') . '<br/>';
        echo 'logfile             ' . $this->config->get_value('logfile', 'setup') . '<br/>';
        echo 'verbose             ' . $this->config->get_value('verbose', 'setup') . '<br/>';
        echo 'js_server           ' . $this->config->get_value('js_server', 'setup') . '<br/>';
        echo 'aaa_credentials     ' . $this->strip_oci_pwd($this->config->get_value('aaa_credentials', 'aaa')) . '<br/>';
        echo '</pre>';
        die();
    }

    /** \brief Format the record according to format
     *
     */
    private function format_rec(&$rec, $format) {
        // return $this->fake_output();
        //verbose::log(TRACE, 'help ' . $rec->bibliographicData->_value->record->_value->description->_value);
        $xml_rec = $this->objconvert->obj2xmlNs($rec);
        //verbose::log(TRACE, 'help ' . $xml_rec);
        for ($i = 0; $i < $this->fake_multi; $i++) {
            $this->curl->set_post_xml($xml_rec, $i);
        }
        $this->watch->start('js_server');
        $js_result = $this->curl->get();
        $this->watch->stop('js_server');
        if (is_array($js_result)) $js_result = $js_result[0];
        //verbose::log(TRACE, 'js_result ' . $js_result);

        $dom = new DomDocument();
        $dom->preserveWhiteSpace = false;
        if ( $dom->loadXML($js_result) ) {
            $js_obj = $this->xmlconvert->xml2obj($dom);
            return $js_obj->bibliotekdkFullDisplay->_value;
        } else {
            $js_obj->xml->_value->description->_value = 'Error formatting record - no valid response';
            $js_obj->xml->_value->description->_namespace = $this->xmlns['ofo'];
            return $js_obj->xml->_value;
        }
    }

    private function strip_oci_pwd($cred) {
        if (($p1 = strpos($cred, '/')) && ($p2 = strpos($cred, '@')))
            return substr($cred, 0, $p1) . '/********' . substr($cred, $p2);
        else
            return $cred;
    }


    /** \brief
     *  return libraryno - align to 6 digits
     */
    private function normalize_agency($id) {
        if (is_numeric($id))
            return sprintf('%06s', $id);
        else
            return $id;
    }

    /** \brief
     *  return only digits, so something like DK-710100 returns 710100
     */
    private function strip_agency($id) {
        return preg_replace('/\D/', '', $id);
    }

    /** \brief
     *  return test output
     */
    private function fake_output() {
        return '<xml xmlns:ofo="' . $this->xmlns['ofo'] . '">' .
               '  <ofo:creator>' . 
               htmlspecialchars ('<div class="creator">Hans Engell</div>') . 
               '  </ofo:creator>' .
               '  <ofo:title>' .
               htmlspecialchars ('<div class="title">P Slotsholmen</div>') . 
               '  </ofo:title>' .
               '  <ofo:type>' .
               htmlspecialchars ('<div class="type">Bog</div>') . 
               '  </ofo:type>' .
               '  <ofo:description>' .
               htmlspecialchars ('<div class="description"><div class="subjects"><span class="subjectHeader">Emne:</span><span class="subject">1945-1999</span><span class="punctuation">;</span><span class="subject">96.72</span><span class="punctuation">;</span><span class="subject">Det Konservative Folkeparti</span><span class="punctuation">;</span><span class="subject">Hans Engell</span><span class="punctuation">;</span><span class="subject">Engell, Hans</span><span class="punctuation">;</span><span class="subject">erindringer</span><span class="punctuation">;</span><span class="subject">historie</span><span class="punctuation">;</span><span class="subject">politik</span><span class="punctuation">;</span><span class="subject">politikere</span><span class="punctuation">;</span><span class="subject">politiske forhold</span><span class="punctuation">;</span><span class="subject">politiske partier</span></div><div class="abstract">Hans Engell (f. 1948), der i 1997 gik af som konservativ partileder, beskriver det politiske liv p Slotsholmen fra han i 1978 startede som pressechef til han i 1993 sluttede som justitsminister, da Tamilsagen tvang Schlter-regeringen til at g</div></div>') . 
               '  </ofo:description>' .
               '  <ofo:details>' .
               htmlspecialchars ('<div class="details"><div class="format">455 sider, illustreret</div><div class="formHeader">Form: </div><div class="form">erindringer</div><div class="shelfHeader">Opstilling i folkebiblioteker: <div class="shelf">96.72</div><div class="note"><div class="noteHeader">Samhrende: </div><div class="noteLink">P Slotsholmen</div> ; <div class="noteLink">Farvel til Slotsholmen</div></div><div class="isbn">ISBN: 87-11-15086-6</div><div class="price">Pris ved udgivelsen: kr. 149,00</div></div>') . 
               '  </ofo:details>' .
               '  <ofo:publicationDetails>' .
               htmlspecialchars ('<div class="publication"><div class="edition">1. udgave, 3. oplag</div>. <div class="publisher">Aschehoug</div>, <div class="year">1997</div></div>') . 
               '  </ofo:publicationDetails>' .
               '</xml>';
    }


}

/*
 * MAIN
 */

    $ws=new openFormat();
    $ws->handle_request();
?>


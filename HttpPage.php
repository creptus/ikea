<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of HttpPage
 *
 * @author alexey.korolev
 */

namespace ikea;

use ikea\IPage;

class HttpPage implements IPage {

	protected $cache = null;

	/**
	 *
	 * @param ICache $cache
	 */
	public function __construct(ICache $cache = NULL) {
		$this->cache = $cache;
	}

	/**
	 *  Emualte Proxy urls
	 * @var array
	 */
	protected $parsdomains = array();
	/**
	 * Add emulate proxy url
	 * @param string $url
	 */
	public function addDomainToRequest($url){
		$this->parsdomains[]=$url;
	}


	public function isUseCache() {
		if ($this->cache !== NULL) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 *
	 * @param string $url url адрес
	 * @param string $prefix префикс для файла при кешировании
	 * @return ыекштп
	 */
	public function getPage($url, $prefix = '') {
		$result = null;
		if ($this->isUseCache()) {
			$key = $prefix . md5($url) . '.html';
			$this->cache->isValid($key, 1000 * 60 * 24 * 5); //5 days
			$val = $this->cache->get($key);
			if ($val !== NULL) {
				$result = $val;
			} else {
				//$res = array("error" => null, "response" => FALSE);
				$res = $this->request($url);
				if ($res['http_code'] != '200') {
					var_dump($res);
				}
				if ($res['error'] === NULL && $res['http_code']=='200') {
					$result = $res["response"];
					$this->cache->set($key, $result);
				}
			}
		} else {
			//$res = array("error" => null, "response" => FALSE);
			$res = $this->request($url);
			if ($res['error'] === NULL) {
				$result = $res["response"];
			}
		}

		return $result;
	}

	protected function request($url) {
		$result = array("error" => null, "response" => FALSE,"http_code"=>'200');
		$headers = array(
			"User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:42.0) Gecko/20100101 Firefox/42.0",
			"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
			//"Accept-Encoding: gzip, deflate",
			"Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3",
			"Cache-Control: max-age=0",
			"Connection: keep-alive",
		);
		if ($curl = curl_init()) {
			$_url='';
			if(count($this->parsdomains)>0){
				$_url=  array_shift($this->parsdomains);
				$this->parsdomains[]=$_url;
			}
			try {
				curl_setopt($curl, CURLOPT_URL, $_url.$url);
				curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
				curl_setopt($curl, CURLOPT_TIMEOUT, 60);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

				$result["response"] = curl_exec($curl);
				if (!curl_errno($curl)) {
					$info = curl_getinfo($curl);
					$result["http_code"]=$info['http_code'];
				}
				
				curl_close($curl);
				if ($result["response"] === false) {
					$result['error'] = 'CURL_FALSE_RESPONSE';
				} else {
					//$result["response"] = $this->gzdecode($result["response"]);
				}
			} catch (Exception $E) {
				$result['error'] = $E->getMessage();
			}
		} else {
			$result['error'] = 'CURL_NO_INIT';
		}

		return $result;
	}

	/**
	 * php method get_headers with cash
	 *
	 * @param string $url
	 * @return array
	 */
	public function getHeaders($url) {
		$headers = array();

		if ($this->isUseCache()) {
			$key = md5($url) . '.heades';
			$this->cache->isValid($key, 1000 * 60 * 24 * 5); //5 days
			$str = $this->cache->get($key);
			if ($str !== NULL) {
				$headers = unserialize($str);
			} else {
				$headers = @get_headers($url);
				$this->cache->set($key, serialize($headers));
			}
		} else {
			$headers = @get_headers($url);
		}
		return $headers;
	}

}

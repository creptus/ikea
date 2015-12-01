<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace ikea;

/**
 * Description of Cache
 *
 * @author alexey.korolev
 */
class Cache implements ICache {

	protected $cachPath = './cache/';

	public function setPath($path) {
		$this->cachPath = $path;
	}

	public function get($key) {
		$result = null;
		if (file_exists($this->cachPath . $key)) {
			$result = file_get_contents($this->cachPath . $key);
		}
		return $result;
	}

	public function set($key, $value) {
		file_put_contents($this->cachPath . $key, $value);
	}

	public function delete($key) {
		if (file_exists($this->cachPath . $key)) {
			unlink($this->cachPath . $key);
		}
	}

	/**
	 *
	 * @param string $key
	 * @param int $time  Время возвращается в формате временной метки (Unix TimeStamp Unix)
	 */
	public function isValid($key, $time) {
		if (file_exists($this->cachPath . $key)) {
			$filemtime = filemtime($this->cachPath . $key);
			if ((time() - $filemtime) > $time) {
				unlink($this->cachPath . $key);
			}
		}
	}

	public function clearAll() {
		throw new Exception('Not implemented');
	}

}

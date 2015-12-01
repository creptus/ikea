<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 *
 * @author alexey.korolev
 */
namespace ikea;

interface ICache {
	public function get($key);

	public function set($key,$value);

	public function delete($key);

	public function clearAll();

	function isValid($key, $time);

	
}

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

interface IPage {
	/**
	 *
	 * @param string $url
	 */
	public function getPage($url);
}

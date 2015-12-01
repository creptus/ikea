<?php

set_time_limit(0);
ini_set("memory_limit", "128M");
header('Content-Type: text/html; charset=utf-8');
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

include './IPage.php';
include './ICache.php';
include './Cache.php';
include './HttpPage.php';
include './models/ProductParsikea.php';
include './Parser.php';
include './DB.php';
include './ProductSaver.php';



$minprice = 1;
if (isset($_GET['minprice'])) {
	$minprice = intval($_GET['minprice']);
}
$maxprice = 50;
if (isset($_GET['maxprice'])) {
	$maxprice = intval($_GET['maxprice']);
}

$urls_manual=array();
if (isset($_GET['urls']) && trim($_GET['urls'])!='') {
	$urls=explode('\r',trim($_GET['urls']));
	foreach ($urls as $url){
		$url=  trim($url);
		if($url!=''){
			$urls_manual[]=$url;
		}
	}
}

$cache = new \ikea\Cache();

$source = new \ikea\HttpPage($cache);




$html = $source->getPage('http://www.ikea.com/ru/ru/catalog/allproducts/');

$cats = ikea\Parser::getCats($html);

//для того чтобы не искать в категориях где мах цена в категории меньше $minprice
$catsPartUrlPrice = array();
if ($source->isUseCache()) {
	$key = 'cats_part_url_price.ser';
	$cache->isValid($key, 1000 * 60 * 30); //30 minutes
	$_catsPartUrlPrice = $cache->get($key);
	if ($_catsPartUrlPrice === NULL) {
		foreach ($cats as $nameCat => $partUrl) {
			$url = 'http://www.ikea.com' . $partUrl;
			$html = $source->getPage($url);
			$price = \ikea\Parser::getPricesFromCat($html, 'price_cat_');
			$catsPartUrlPrice[$partUrl] = $price;
		}
		$cache->set($key, serialize($catsPartUrlPrice));
	} else {
		$catsPartUrlPrice = unserialize($_catsPartUrlPrice);
	}
}



$products_part_urls = []; //массив url товаров
$products_part_url_cat = []; //массив для поиска категории по $partUrl
//Гуляем по категориям
foreach ($cats as $nameCat => $partUrl) {
	//защита от категорий, где запращивая цена за пределами цен товаров в категории
	if (isset($catsPartUrlPrice[$partUrl])) {
		$priceCat = $catsPartUrlPrice[$partUrl];
		if (!($minprice >= $priceCat['min'] && $minprice <= $priceCat['max']) || !($maxprice >= $priceCat['min'] && $maxprice <= $priceCat['max'])) {
			continue;
		}
	}
	$url = 'http://www.ikea.com' . $partUrl . '?priceFilter=true&minprice=' . $minprice . '&maxprice=' . $maxprice;
	$html = $source->getPage($url);
	$data = ikea\Parser::getProductUrlFromCat($html);
	$products_part_urls = array_merge($products_part_urls, $data[1]);

	//Эта часть для вставки категории по старому, впоследствии можно отказаться от нее
	foreach ($data[1] as $u) {
		$products_part_url_cat[$u] = $nameCat;
	}
}

$saver = new ikea\ProductSaver(new ikea\DB('main', 'localhost', 'root', '', 'newikea'));

//Гуляем по товарам
$countSaveProduct = 0;
$affected_rows = 0;
$products_part_urls=  array_merge($products_part_urls,$urls_manual);
foreach ($products_part_urls as $partUrl) {
	$url = 'http://www.ikea.com' . $partUrl;
	$html = $source->getPage($url);
	$products = ikea\Parser::getProducts($html, $source);

	//Эта часть для вставки категории по старому, впоследствии можно отказаться от нее
	$cat = '';
	if (isset($products_part_url_cat[$partUrl])) {
		$cat = $products_part_url_cat[$partUrl];
	}

	foreach ($products as &$p) {
		$p->cat = $cat;
	}

	$affected_rows+=$saver->saveProducts($products);
	$countSaveProduct+=count($products);
}

echo "Products save: " . $countSaveProduct . " ({$affected_rows})";



/*
TRUNCATE TABLE `oc_product`;
TRUNCATE TABLE `oc_product_option`;
TRUNCATE TABLE `oc_product_option_value`;
TRUNCATE TABLE `oc_product_description`;
TRUNCATE TABLE `oc_product_to_category`;
TRUNCATE TABLE `oc_product_image`;
TRUNCATE TABLE `oc_product_to_store`;
TRUNCATE TABLE `oc_product_to_layout`;
TRUNCATE TABLE `oc_product_related`;
TRUNCATE TABLE `parsikea`;
 *  */








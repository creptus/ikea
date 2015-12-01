<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Parser
 *
 * @author alexey.korolev
 */

namespace ikea;

use ikea\models\ProductParsikea;

class Parser {

	/**
	 * 
	 *
	 * @param string $html
	 *
	 * @return array $cats  CatName=>URL
	 */
	public static function getCats($html) {
		/* $doc = new DOMDocument();
		  @$doc->loadHTML($html);
		  $breadCrumbs = $doc->getElementById('breadCrumbs')->getElementsByTagName('a');
		  $product_cat = '';
		  $_product_cat = array();
		  foreach ($breadCrumbs as $crumb) {
		  $_product_cat[] = $crumb->nodeValue . "|" . $crumb->getAttribute('href');
		  } */

		$cats = [];
		$doc = new \DOMDocument();
		@$doc->loadHTML($html);
		$finder = new \DomXPath($doc);
		$classname = "textContainer";
		$nodes = $finder->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

		foreach ($nodes as $node) {
			$_cats = $node->getElementsByTagName('a');
			foreach ($_cats as $_cat) {
				$cats[trim($_cat->nodeValue)] = $_cat->getAttribute('href');
			}
		}
		return $cats;
	}

	/**
	 * Возвращает минимальную и максимальную цену в каталоге Min and Max price in Catalog
	 *
	 * @param string $html
	 * @return array [min=>$min_price_in_catalog,max=>$max_price_in_catalog]
	 */
	public static function getPricesFromCat($html){
		$min_price = 0;
		$max_price = 0;
		$doc = new \DOMDocument();
		@$doc->loadHTML($html);

		//price
		$el_minprice = $doc->getElementById('minprice');
		if ($el_minprice) {
			$min_price = intval($el_minprice->getAttribute('value'));
		}
		$el_maxprice = $doc->getElementById('maxprice');
		if ($el_maxprice) {
			$max_price = intval($el_maxprice->getAttribute('value'));
		}
		return ["min"=>$min_price, "max"=>$max_price];
	}

	/**
	 * Собирает урлы товаров 
	 *
	 * @param string $html
	 * @return array array(array(url=>name),array(url))
	 */
	public static function getProductUrlFromCat($html) {
		$products_url = [];		
		$doc = new \DOMDocument();
		@$doc->loadHTML($html);
		$finder = new \DomXPath($doc);
		$classname = "product";
		$nodes = $finder->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
		foreach ($nodes as $node) {
			$_cats = $node->getElementsByTagName('a');
			foreach ($_cats as $_cat) {
				if ($_cat->getAttribute('href') == '#') {
					continue;
				}
				$products_url[$_cat->getAttribute('href')] = $_cat->nodeValue;
			}
		}
		$urls = [];
		foreach ($products_url as $url => $name) {
			$urls[] = $url;
		}
		

		return [$products_url, $urls];
	}

	/**
	 *
	 * Парсит карточку товара и возвращает все товары
	 *
	 * @param IPage $source
	 * @param string $html
	 *
	 * @return array of ProductParsikea
	 */
	public static function getProducts($html, $source) {
		$result = array();
		$doc = new \DOMDocument();
		@$doc->loadHTML($html);

		$crumbs = array();

		$breadCrumbs = $doc->getElementById('breadCrumbs');
		if ($breadCrumbs) {
			$a = $breadCrumbs->getElementsByTagName('a');
			foreach ($a as $crumb) {
				$href = $crumb->getAttribute('href');
				if ($href == '/ru/ru/') {
					continue;
				}
				$crumbs[] = $crumb->nodeValue . "|" . $href;
			}
		}


		//значения опций
		$attributes = array(); //список нащваний опций товара которые можно выбирать
		$subdiv = $doc->getElementById('subdiv');
		if ($subdiv) {
			$divs = $subdiv->getElementsByTagName('div');
			foreach ($divs as $div) {
				$attr_name = '';
				foreach ($div->getElementsByTagName('label') as $label) {
					$attr_name = trim($label->nodeValue);
				}
				$attributes[$attr_name] = array();
				$options = $div->getElementsByTagName('option');
				foreach ($options as $option) {
					$attributes[$attr_name][trim($option->nodeValue)] = $option->getAttribute('value');
				}
			}
		}




		//Json данные о товаре
		preg_match_all("/jProductData = (.*);/m", $html, $matches);
		$jProductData = new \stdClass();
		if ($matches[1] && $matches[1][0]) {
			$jProductData = json_decode($matches[1][0]);
		}


		$isset = '';
		preg_match_all("/var localStoreList = \"(.*)\";/m", $html, $matches);
		$localStoreList = '';
		if ($matches[1] && $matches[1][0]) {
			$localStoreList = $matches[1][0];
		}
		$isset = $localStoreList;


		preg_match_all("/jsonRelatedProducts = (.*);/m", $html, $matches);
		$jsonRelatedProducts = new \stdClass();
		if ($matches[1] && $matches[1][0]) {
			$jsonRelatedProducts = json_decode($matches[1][0]);
		}

		if (isset($jProductData->product)) {
			/* if(isset($jProductData->product->attributes)){
			  $obj->attributes=$jProductData->product->attributes;
			  } */
			$other_mod_sku = array();
			foreach ($jProductData->product->items as $item) {
				$product = new ProductParsikea();
				$product->cat = null; //	text
				$product->sku = preg_replace('(.{3})', "$0.", preg_replace("/\D/", "", $item->partNumber)); //	varchar				
				$product->url = 'http://www.ikea.com/ru/ru/catalog/products/' . $item->partNumber; //	text
				$product->img = self::getImages($item, $source); //	text
				$product->desc = self::getDesc($item); //	text
				$product->fulldesc = self::getFullDesc($item); //	longtext
				$product->title = self::getTitle($item); //	text
				$product->name = $item->name; //	text
				$product->qant = '1000'; //	text
				$product->price = $item->prices->normal->priceNormal->rawPrice; //	text
				$product->widh = self::getWidh($item); //	text
				$product->height = self::getHeight($item); //	text
				$product->lenght = self::getLenght($item); //	text
				$product->weight = self::getWeight($item); //	text
				$product->isset = $isset; //	text
				$product->releted = self::getRelated($jsonRelatedProducts, $product->sku); //	text
				$product->inserted = 0; //	int
				$product->other_mod_sku = ''; //	text
				$product->crumbs = implode(';', $crumbs); //	text
				$product->cat_entry_id = $item->catEntryId; //	varchar
				$product->main_cat_entry_id = $jProductData->product->catEntryId; //	varchar
				$product->attr_select = serialize($attributes); //	text
				$result[] = $product;

				$other_mod_sku[] = $product->sku;
			}

			$_other_mod_sku = implode(',', $other_mod_sku);
			foreach ($result as &$product) {
				$product->other_mod_sku = $_other_mod_sku;
			}
			//var_dump($jProductData->product->catEntryId);
		}
		//var_dump();

		return $result;
	}

	protected function getImages($item, $source) {
		$smallimgprod = $item->images->small;
		$normimgprod = $item->images->normal;
		$zoomimgprod = $item->images->zoom;
		$domain = 'http://www.ikea.com';
		$normimg = array();
		$normimg1 = array();
		$normimg2 = array();
		foreach ($zoomimgprod as $zoom) {
			if ($zoom != NULL) {
				$selimg = str_replace('_S5', '_S4', $domain . $zoom);
				$file_headers = $source->getHeaders($selimg);
				if ($file_headers[0] != 'HTTP/1.1 404 Not Found') {
					$normimg[] = $selimg;
				}
			}
		}
		foreach ($normimgprod as $norm) {
			if ($norm != NULL) {
				$selimg = str_replace('_S5', '_S4', $domain . $norm);
				$file_headers = $source->getHeaders($selimg);
				if ($file_headers[0] != 'HTTP/1.1 404 Not Found') {
					$normimg1[] = $selimg;
				}
			}
		}
		foreach ($smallimgprod as $small) {
			if ($small != NULL) {
				$selimg = str_replace('_S5', '_S4', $domain . $small);
				$file_headers = $source->getHeaders($selimg);
				if ($file_headers[0] != 'HTTP/1.1 404 Not Found') {
					$normimg2[] = $selimg;
				}
			}
		}
		if (isset($normimg[0])) {
			$normimg = implode("<br>", array_slice($normimg, 0, 3)); //Картинки
		} elseif (isset($normimg1[0])) {
			$normimg = implode("<br>", array_slice($normimg1, 0, 3));
		} else {
			$normimg = implode("<br>", array_slice($normimg2, 0, 3));
		}
		return $normimg;
	}

	protected function getRelated($jsonRelatedProducts, $sku) {
		$result = '';
		$_sku = str_replace('.', '', $sku);
		$_result = array();
		if (isset($jsonRelatedProducts->{'item_S' . $_sku})) {
			$info = $jsonRelatedProducts->{'item_S' . $_sku};
			if (isset($info->MAY_BE_COMPLETED_WITH) && is_array($info->MAY_BE_COMPLETED_WITH)) {
				$_result = array_merge($_result, $info->MAY_BE_COMPLETED_WITH);
			}
			if (isset($info->GETS_SAFER_WITH) && is_array($info->GETS_SAFER_WITH) && count($info->GETS_SAFER_WITH) > 0) {
				$_result = array_merge($_result, $info->GETS_SAFER_WITH);
			}
		}
		if (isset($jsonRelatedProducts->{'item_' . $_sku})) {
			$info = $jsonRelatedProducts->{'item_' . $_sku};
			if (isset($info->MAY_BE_COMPLETED_WITH) && is_array($info->MAY_BE_COMPLETED_WITH)) {
				$_result = array_merge($_result, $info->MAY_BE_COMPLETED_WITH);
			}
			if (isset($info->GETS_SAFER_WITH) && is_array($info->GETS_SAFER_WITH) && count($info->GETS_SAFER_WITH) > 0) {
				$_result = array_merge($_result, $info->GETS_SAFER_WITH);
			}
		}
		$result = implode(',', $_result);

		return $result;
	}

	protected function getHeight($item) {
		$pkgInfoArrprod = '';
		if (array_key_exists('pkgInfoArr', $item)) {
			$pkgInfoArrprod = $item->pkgInfoArr[0]->pkgInfo[0];
		}

		$heightMetprod = '';
		if ($pkgInfoArrprod != '' && array_key_exists('heightMet', $pkgInfoArrprod)) {
			$heightMetprod = preg_replace("~[^\d\.]+~", "", $pkgInfoArrprod->heightMet);
		}
		return $heightMetprod;
	}

	protected function getLenght($item) {
		$pkgInfoArrprod = '';
		if (array_key_exists('pkgInfoArr', $item)) {
			$pkgInfoArrprod = $item->pkgInfoArr[0]->pkgInfo[0];
		}

		$lengthMetprod = '';
		if ($pkgInfoArrprod != '' && array_key_exists('lengthMet', $pkgInfoArrprod)) {
			$lengthMetprod = preg_replace("~[^\d\.]+~", "", $pkgInfoArrprod->lengthMet);
		}

		return $lengthMetprod;
	}

	protected function getWeight($item) {
		$pkgInfoArrprod = '';
		if (array_key_exists('pkgInfoArr', $item)) {
			$pkgInfoArrprod = $item->pkgInfoArr[0]->pkgInfo[0];
		}

		$weightMetprod = '';
		if ($pkgInfoArrprod != '' && array_key_exists('weightMet', $pkgInfoArrprod)) {
			$weightMetprod = preg_replace("~[^\d\.]+~", "", $pkgInfoArrprod->weightMet);
		}

		$_weightMetprod = ''; //собираем сумму веса упаковок товара
		if (array_key_exists('pkgInfoArr', $item)) {
			$_pkgInfoArr = $item->pkgInfoArr;
			if (is_array($_pkgInfoArr)) {
				foreach ($_pkgInfoArr as $_pia) {
					if (is_array($_pia->pkgInfo)) {
						foreach ($_pia->pkgInfo as $_pi) {
							$_quantity = intval(preg_replace("~[^\d\.]+~", "", $_pi->quantity));
							if ($_quantity === 0) {
								$_quantity = 1;
							}
							$_weightMetprod+= floatval(preg_replace("~[^\d\.]+~", "", $_pi->weightMet)) * $_quantity;
						}
					}
				}
			}
		}
		if ($_weightMetprod != '') {
			$weightMetprod = $_weightMetprod;
		}
		return $weightMetprod;
	}

	protected function getWidh($item) {
		$pkgInfoArrprod = '';
		if (array_key_exists('pkgInfoArr', $item)) {
			$pkgInfoArrprod = $item->pkgInfoArr[0]->pkgInfo[0];
		}

		$widthMetprod = '';
		if ($pkgInfoArrprod != '' && array_key_exists('widthMet', $pkgInfoArrprod)) {
			$widthMetprod = preg_replace("~[^\d\.]+~", "", $pkgInfoArrprod->widthMet);
		}

		return $widthMetprod;
	}

	protected function getTitle($item) {
		$title = '';
		$colorfortitle = '';
		$colorprod = self::getColor($item);
		if ($colorprod != '') {
			$colorfortitle = $colorprod;
			$colorprod = '<div class="colorprod">Цвет:</div><div class="colorprodtext">' . $colorprod . '</div>';
		}
		$title = $item->type . ' ' . $item->name . ' ' . $colorfortitle;
		return $title;
	}

	protected function getColor($item) {
		$colorprod = '';
		if (array_key_exists('validDesign', $item)) {
			if (is_array($item->validDesign)) {
				$colorprod = implode(', ', $item->validDesign);
			}
		}
		if ($colorprod == '' && array_key_exists('color', $item)) {
			$colorprod = $item->color;
		}
		return $colorprod;
	}

	protected function getFullDesc($item) {
		$metricprod = $item->metric;
		$itemsize = '<div class="goodSize">Размеры товара:</div><div class="goodSizetext">' . $metricprod . '</div>';

		$goodToKnowprod = '';
		if (array_key_exists('goodToKnow', $item)) {
			$goodToKnowprod = $item->goodToKnow;
		}

		$colorprod = self::getColor($item);

		$custMaterialsprod = '';
		if (array_key_exists('custMaterials', $item)) {
			$custMaterialsprod = $item->custMaterials;
		}
		if ($custMaterialsprod != '') {
			$custMaterialsprod = '<div class="custMaterialsprod">Материалы:</div><div class="custMaterialsprodtext">' . $custMaterialsprod . '</div>';
		}

		$careInstprod = '';
		if (array_key_exists('careInst', $item)) {
			$careInstprod = $item->careInst;
		}
		if ($careInstprod != '') {
			$careInstprod = '<div class="careInstprod">Инструкция по уходу:</div><div class="careInstprodtext">' . $careInstprod . '</div>';
		}


		$fulldesc = '';
		$fulldesc = self::getCustBenefit($item) . $itemsize . $goodToKnowprod . $colorprod . $custMaterialsprod . $careInstprod;
		return $fulldesc;
	}

	protected function getCustBenefit($item) {
		$custBenefit = '';
		if (array_key_exists('custBenefit', $item)) {
			$custBenefit = str_replace(array('<cb>', '<cbs>', '</cbs>', '<t>', '</t>'), '', str_replace('</cb>', '<br>', $item->custBenefit));
		}
		return $custBenefit;
	}

	protected function getDesc($item) {
		$custBenefit = self::getCustBenefit($item);

		$descprod = '';
		if ($custBenefit != '') {
			$descprod = explode('.', $custBenefit);
			if (isset($descprod[1]))
				$descprod = $descprod[0] . '.' . $descprod[1] . '.';
			else
				$descprod = $descprod[0] . '.';
		}
		return $descprod;
	}

}

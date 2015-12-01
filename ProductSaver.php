<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ProductSaver
 *
 * @author alexey.korolev
 */

namespace ikea;

class ProductSaver {

	protected $db = null;

	/**
	 *
	 * @param DB $db
	 */
	public function __construct(DB $db) {
		$this->db = $db;
	}

	/**
	 *
	 * @param array of ProductParsikea $products
	 */
	public function saveProducts($products) {
		$fields = array('cat', 'sku', 'url', 'img', 'desc', 'fulldesc', 'title', 'name', 'qant', 'price', 'widh', 'height', 'lenght', 'weight', 'isset', 'releted', 'inserted', 'other_mod_sku', 'crumbs', 'cat_entry_id', 'main_cat_entry_id', 'attr_select');
		$_fields = array();
		$count_rows = 0;
		foreach ($fields as $field) {
			$_fields[] = "`{$field}`";
		}

		$sql = "INSERT IGNORE INTO `parsikea2` (" . implode(', ', $_fields) . ") VALUES ";
		$vals = [];

		foreach ($products as $product) {
			$val = [];
			//echo "'$product->sku',";
			foreach ($fields as $field) {
				$val[] = "'" . str_replace("'", "&#39;", $product->{$field}) . "'";
			}
			$vals[] = "(" . implode(',', $val) . ")";
		}
		
		while (count($vals) > 0) {
			$_vals = array_splice($vals, 0, 5);
			$_sql = $sql . implode(', ', $_vals) . " ON DUPLICATE KEY UPDATE `isset`=VALUES(`isset`), `price`=VALUES(`price`),`qant`=VALUES(`qant`),`releted`=VALUES(`releted`), `attr_select`=VALUES(`attr_select`);";
			$count_rows_affected = $this->db->query_execute('main', $_sql);
			$count_rows+=$count_rows_affected;
			//file_put_contents('./temp/'.time().'.sql', $_sql);
		}
		

		//echo $count_rows . " ";
		return $count_rows;
	}

}

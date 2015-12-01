<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of OpenCartConvertor
 *
 * @author alexey.korolev
 */

namespace ikea;

class OpenCartConvertor {

	protected $db = null;

	/**
	 *
	 * @param DB $db
	 */
	public function __construct(DB $db) {
		$this->db = $db;
	}

	protected $optionsNames = array();

	/**
	 *
	 * @param string $path path to save images
	 */
	public function convert($path) {
		$data = $this->getData();
		if (count($data) == 0) {
			return false;
		}

		foreach ($data as $d) {
			//опции в базу options save to db
			$_optins = @unserialize($d['attr_select']);
			if (is_array($_optins)) {
				$this->addOptions($_optins);
			}
			//категрии в базу categoryes save to db
			$category_ids = $this->makeCategory($d['crumbs']);

			$imagenamall = $this->downloadImages($d['url'], $d['img'], $path);

			$this->addProduct($d, $imagenamall, $category_ids);

		}
		var_dump('DB_ERRORS');
		var_dump($this->db->getErrors());

		//var_dump($this->categoryes);
		//var_dump($this->optionsNames);
		return true;
	}

	/**
	 * Analyze product
	 *
	 * @param type $data
	 * @param type $imagenamall
	 * @param type $category_ids
	 * @param bollean $check_mpn check uniq by main_cat_entry_id
	 *
	 * @return void
	 */
	protected function addProduct($data, $imagenamall, $category_ids, $check_mpn = true) {
		if ($check_mpn) {
			$sql = "SELECT `sku`,`product_id` FROM `oc_product` WHERE mpn='{$data['main_cat_entry_id']}' limit 1;";
			$rows = $this->db->query_get_multi_a('main', $sql);
			if (count($rows) > 0) {
				$product_id = $rows[0]['product_id'];
				if ($rows[0]['sku'] == $data['sku']) {
					//обновить товар Update good
					var_dump('update product ' . $product_id . ' ' . $data['sku'] . ' ' . $data['title']);
					$this->updateProduct($data, $product_id);
					$this->updateOptionToProduct($data, $product_id);
				} else {
					//this is option
					$sql = "SELECT * FROM `oc_otp_data` WHERE `model`='{$data['sku']}';";
					$rows = $this->db->query_get_multi_a('main', $sql);
					if (count($rows) == 0) {
						//новая опция New OTP Option
						var_dump('new option ' . $product_id . ' ' . $data['sku'] . ' ' . $data['title']);
						$this->addNewOptionsToProduct($data, $product_id);
					} else {
						//обновить опцию Update OTP Option
						var_dump('update option ' . $product_id . ' ' . $data['sku'] . ' ' . $data['title']);
						$this->updateOptionToProduct($data, $product_id);
					}
				}
			} else {
				//new
				var_dump('new product ' . $data['sku'] . ' ' . $data['title'] . ' category_ids=' . implode(',', $category_ids));
				$product_id = $this->addNewProduct($data, $imagenamall, $category_ids);
				//Нужно для выбора этой модели Need to select this sku
				$this->addNewOptionsToProduct($data, $product_id);
			}
		}
	}

	protected function updateProduct($data, $product_id) {

		//основные характеристики Common charecters
		$sql = "UPDATE `oc_product`
			SET price='{$data['price']}', weight='{$data['weight']}', height='{$data['height']}', "
				. "width='{$data['widh']}', length='{$data['lenght']}', date_modified='" . date("Y-m-d H:m:s") . "'"
				. "	WHERE `product_id`='{$product_id}';";
		$rows = $this->db->query_execute('main', $sql);

		//описания Description
		$title = htmlspecialchars($data['title']);
		$fulldesc = htmlspecialchars($data['fulldesc']);
		$desc = htmlspecialchars(str_replace('<br>.', '', $data['desc']));
		$sql = "UPDATE `oc_product_description` "
				. "SET `name`='{$data['name']}', `description`='{$fulldesc}', `meta_title`='{$title}', `meta_description`='{$desc}'"
				. "WHERE `product_id`={$product_id};";
		$this->db->query_execute('main', $sql);

		//магазины Shops
		//обнуляем кол-во Cleare quantity
		$sql = "UPDATE `oc_product_option_value` SET quantity='0' WHERE product_id={$product_id} AND option_id=13;";
		$this->db->query_execute('main', $sql);

		$option_value_ids = $this->getShops($data['isset']);

		if (count($option_value_ids) > 0) {
			//На усмотрение магазина(63) Fixed option
			$option_value_ids[] = '63';
			$sql = "UPDATE `oc_product_option_value` "
					. "SET quantity='1000' "
					. "WHERE product_id={$product_id} AND option_id=13 AND option_value_id IN (" . implode(', ', $option_value_ids) . ");";
			$this->db->query_execute('main', $sql);
		}

		$sql = "UPDATE `parsikea2` SET inserted='1' WHERE id='" . $data['id'] . "'";
		$this->db->query_execute('main', $sql);
	}

	/**
	 *
	 * @param string $isset
	 * @return array
	 */
	protected function getShops($isset) {
		$shops = explode(':', $isset);
		$option_value_ids = array();
		foreach ($shops as $shop) {
			$dataopt = explode('|', $shop);

			if ($dataopt['0'] == 344) {
				$option_value_ids[] = '50';
			} elseif ($dataopt['0'] == 426) {
				$option_value_ids[] = '51';
			} elseif ($dataopt['0'] == 464) {
				$option_value_ids[] = '57';
			} elseif ($dataopt['0'] == 336) {
				$option_value_ids[] = '60';
			} elseif ($dataopt['0'] == 335) {
				$option_value_ids[] = '62';
			}
		}
		return $option_value_ids;
	}

	protected function updateOptionToProduct($data, $product_id) {
		$sql = "SELECT * FROM `oc_otp_option_value` oov "
				. "WHERE oov.id=(SELECT od.otp_id FROM oc_otp_data od WHERE od.model='{$data['sku']}'  AND od.product_id={$product_id} LIMIT 1);";
		$rows = $this->db->query_get_multi_a('main', $sql);
		$quantity = '0';
		if (count($this->getShops($data['isset'])) > 0) {
			$quantity = '1000';
		}
		if (count($rows) > 0) {
			$sql = "UPDATE `oc_otp_data` "
					. "SET `price`='{$data['price']}', `weight`='{$data['weight']}', `quantity`='{$quantity}' "
					. "WHERE `otp_id`={$rows[0]['id']};";
			$this->db->query_execute('main', $sql);
		}
		$sql = "UPDATE `parsikea2` SET inserted='1' WHERE id='" . $data['id'] . "'";
		$this->db->query_execute('main', $sql);
	}

	protected function addNewOptionsToProduct($data, $product_id) {
		$optins = unserialize($data['attr_select']);
		if (is_array($optins)) {
			//var_dump($data['cat_entry_id']);
			$_options = array();
			foreach ($optins as $name => $opts) {
				//var_dump($opts);
				foreach ($opts as $name_val => $opt_cat_entry_ids) {
					$_arr = explode(';', $opt_cat_entry_ids);
					if (in_array($data['cat_entry_id'], $_arr)) {
						//$_options[$this->optionsNames[$name]['option_id']]=$this->optionsNames[$name]['vals'][$name_val];
						$_options[] = $this->optionsNames[$name]['option_id'];
						$_options[] = $this->optionsNames[$name]['vals'][$name_val];
						break;
					}
				}
			}
			//var_dump('$_options');
			//var_dump($_options);

			$vals = array();
			switch (count($_options)) {
				case 0:
					//error
					break;
				case 2:
					$vals = array_merge($_options, array(0, 0, 0, 0));
					break;
				case 4:
					$vals = array_merge($_options, array(0, 0));
					break;
				case 6:
					$vals = $_options;
					break;
			}
			//var_dump($vals);

			if (count($vals) > 0) {
				$sql = "INSERT INTO `oc_otp_option_value` (`product_id`, "
						. "`parent_option_id`, `parent_option_value_id`, `child_option_id`,`child_option_value_id`,"
						. " `grandchild_option_id`, `grandchild_option_value_id`) VALUES ({$product_id}," . implode(', ', $vals) . ');';
				//var_dump($sql);
				$oc_otp_option_value_id = 0;
				$oc_otp_option_value_id = $this->db->query_execute_with_last_inser_id('main', $sql);

				$sql = "INSERT INTO `oc_otp_data` (`otp_id`, `product_id`, `model`, `extra`, `quantity`, `subtract`, `price_prefix`, `price`, `special`, `weight_prefix`, `weight`) VALUES "
						. "('{$oc_otp_option_value_id}', '{$product_id}', '{$data['sku']}', '2', '1000', '1', '=', '{$data['price']}', '0', '=', '{$data['weight']}');";
				//var_dump($sql);
				$this->db->query_execute('main', $sql);
			}

			$sql = "UPDATE `parsikea2` SET inserted='1' WHERE id='" . $data['id'] . "'";
			$this->db->query_execute('main', $sql);




			//var_dump($this->optionsNames);
		}
	}

	/**
	 * Сохраняет новый товар Save new Product
	 *
	 * @param type $data
	 * @param type $imagenamall
	 * @param type $category_ids
	 * @return type
	 */
	protected function addNewProduct($data, $imagenamall, $category_ids) {

		//новый товар
		$countimg = count($imagenamall);
		$imgnamefirst = '';
		if (count($imagenamall) > 0) {
			$imgnamefirst = array_shift($imagenamall);
		}
		$sql = "INSERT INTO `oc_product`(`model`, `sku`, `upc`, `ean`, `jan`, `isbn`, `mpn`, `location`, `quantity`, `stock_status_id`,"
				. " `image`, `manufacturer_id`, `shipping`, `price`, `points`, `tax_class_id`, `date_available`, `weight`, "
				. "`weight_class_id`, `length`, `width`, `height`, `length_class_id`, `subtract`, `minimum`, "
				. "`sort_order`, `status`, `viewed`, `date_added`, `date_modified`, `titlebrend`) "
				. "VALUES ('" . $data['sku'] . "','" . $data['sku'] . "','','','','','" . $data['main_cat_entry_id'] . "','','" . $data['qant'] . "','5','" .
				$imgnamefirst . "','0','1','" . $data['price'] . "','0','0','" . date("Y-m-d") . "','" . $data['weight'] . "','1','" .
				$data['lenght'] . "','" . $data['widh'] . "','" . $data['height'] . "','1','1','1','1','1','0','" . date("Y-m-d H:m:s") . "','" .
				date("Y-m-d H:m:s") . "', '{$data['name']}');";
		$product_id = $this->db->query_execute_with_last_inser_id('main', $sql);

		//Магазин(13)
		$sql = "INSERT INTO `oc_product_option`(`product_id`, `option_id`, `value`, `required`) VALUES ('" . $product_id . "','13','','1');";
		$product_option_id = $this->db->query_execute_with_last_inser_id('main', $sql);
		$vals = array();
		//На усмотрение магазина(63)
		$vals[] = "('" . $product_option_id . "','" . $product_id . "','13','63','1000','1','0','+','0','+','0','+')";
		$shops = explode(':', $data['isset']);
		foreach ($shops as $shop) {
			$dataopt = explode('|', $shop);
			$option_value_id = '';
			if ($dataopt['0'] == 344) {
				$option_value_id = '50';
			} elseif ($dataopt['0'] == 426) {
				$option_value_id = '51';
			} elseif ($dataopt['0'] == 464) {
				$option_value_id = '57';
			} elseif ($dataopt['0'] == 336) {
				$option_value_id = '60';
			} elseif ($dataopt['0'] == 335) {
				$option_value_id = '62';
			}
			if ($option_value_id != '') {
				$vals[] = "('" . $product_option_id . "','" . $product_id . "','13','" . $option_value_id . "','1000','1','0','+','0','+','0','+')";
			}
		}
		$sql = "INSERT INTO `oc_product_option_value`(`product_option_id`, `product_id`, `option_id`, `option_value_id`, `quantity`, `subtract`,"
				. " `price`, `price_prefix`, `points`, `points_prefix`, `weight`, `weight_prefix`) "
				. "VALUES " . implode(', ', $vals) . ";";
		$this->db->query_execute('main', $sql);


		$vals = array();
		$langs = $this->getLanguages();
		$title = htmlspecialchars($data['title']);
		$fulldesc = htmlspecialchars($data['fulldesc']);
		$desc = htmlspecialchars(str_replace('<br>.', '', $data['desc']));
		foreach ($langs as $lng) {
			$vals[] = "('" . $product_id . "','" . $lng['language_id'] . "','" . $title . "','" . $fulldesc . "','','" . $title . "','" . $desc . "','')";
		}
		if (count($vals) > 0) {
			$sql = "INSERT INTO `oc_product_description`(`product_id`, `language_id`, `name`, "
					. "`description`, `tag`, `meta_title`, `meta_description`, `meta_keyword`) "
					. "VALUES " . implode(', ', $vals) . ";";
			$this->db->query_execute('main', $sql);
		}

		//добавляем в категорию
		if (count($category_ids) > 0) {
			$vals = array();
			foreach ($category_ids as $category_id){
				$vals[]="('" . $product_id . "','" . $category_id . "')";
			}
			$sql = "INSERT INTO `oc_product_to_category`(`product_id`, `category_id`) "
					. "VALUES ".implode(', ',$vals).';';
			$this->db->query_execute('main', $sql);
		}


		$vals = array();
		foreach ($imagenamall as $imegeinsert) {
			$vals[] = "('" . $product_id . "','" . $imegeinsert . "','0')";
		}
		if (count($vals) > 0) {
			$sql = "INSERT INTO `oc_product_image`(`product_id`, `image`, `sort_order`) VALUES " . implode(', ', $vals) . ";";
			$this->db->query_execute('main', $sql);
		}

		$sql = "INSERT INTO `oc_product_to_store`(`product_id`, `store_id`) VALUES ('" . $product_id . "','0')";
		$this->db->query_execute('main', $sql);
		$sql = "UPDATE `parsikea2` SET inserted='1' WHERE id='" . $data['id'] . "'";
		$this->db->query_execute('main', $sql);

		return $product_id;
	}

	protected function downloadImages($url, $img_arr, $path) {
		$imgs = explode('<br>', $img_arr);
		$namedir = explode('/', $url);
		/* $namedir1 = array_pop($namedir);
		  $namedir = array_pop($namedir); */
		$namedir = $namedir[count($namedir) - 1];
		$dir = $path . 'image/catalog/prod/' . $namedir . '/';

		$imagenamall = array();
		if (!is_dir($dir)) {
			mkdir($dir, 0777);
		}
		foreach ($imgs as $image) {
			$imgname = explode('/', $image);
			$imgname = array_pop($imgname);
			if (!file_exists($dir . $imgname)) {
				if (@fopen($image, "r")) {
					file_put_contents($dir . $imgname, file_get_contents($image));
					$imagenamall[] = 'catalog/prod/' . $namedir . '/' . $imgname;
				}
				$countimg = count($imagenamall);
				if ($countimg == 0) {
					$image = str_replace('_S4', '_S3', $image);
					$imgname = str_replace('_S4', '_S3', $imgname);
					if (!file_exists($dir . $imgname)) {
						if (@fopen($image, "r")) {
							file_put_contents($dir . $imgname, file_get_contents($image));
							$imagenamall[] = 'catalog/prod/' . $namedir . '/' . $imgname;
						}
					} else {
						$imagenamall[] = 'catalog/prod/' . $namedir . '/' . $imgname;
					}
				}
			} else {
				$imagenamall[] = 'catalog/prod/' . $namedir . '/' . $imgname;
			}
		}
		return $imagenamall;
	}

	protected $categoryes = array();

	/**
	 * Строит дерево каталога для товара (создает категории и структуру подкатегорий)
	 *
	 * @param array $crumbs
	 * @return array массив id катгорий
	 */
	protected function makeCategory($crumbs) {
		$_crumbs = explode(';', $crumbs);
		$cats = &$this->categoryes;
		$parent_id = 0;
		$categores_ids = array(); //id каткгорий для товара
		foreach ($_crumbs as $i => $crumb) {
			$c = explode('|', $crumb); //0 - name, 1- url
			$name = trim($c[0]);
			if (!isset($cats[$name])) {
				$sql = "SELECT cd.category_id FROM `oc_category_description`cd
						LEFT JOIN oc_category as c ON c.category_id=cd.category_id
						WHERE cd.`name`='{$name}' AND c.parent_id='{$parent_id}';";
				$rows = $this->db->query_get_multi_a('main', $sql);
				if (count($rows) == 0) {
					//добавить категорию
					$cats[$name] = $this->addCategory($parent_id, $name, $c[1], $i);
				} else {
					$cats[$name] = array("category_id" => 0, "url" => $c[1], "level" => $i);

					$cats[$name]["category_id"] = $rows[0]['category_id'];
				}
			}
			$parent_id = $cats[$name]["category_id"];
			$categores_ids[] = $cats[$name]["category_id"];
			$cats = &$cats[$name];
		}
		return $categores_ids;
	}

	/**
	 * Добавляет категорию
	 *
	 * @param int $parent_id
	 * @param string $name
	 * @param string $url
	 * @param level $level
	 * @return array
	 */
	protected function addCategory($parent_id, $name, $url, $level) {
		$cat = array("category_id" => 0, "url" => $url, "level" => $level);
		$sql = "INSERT INTO `oc_category`( `image`, `parent_id`, `top`, `column`, `sort_order`, `status`, `date_added`, `date_modified`) "
				. "VALUES ('', '{$parent_id}','0','0','0','1','" . date("Y-m-d H:m:s") . "','" . date("Y-m-d H:m:s") . "')";
		//var_dump($sql);
		$category_id = $this->db->query_execute_with_last_inser_id('main', $sql);
		$cat["category_id"] = $category_id;

		$vals = array();
		$rows_language = $this->getLanguages();
		foreach ($rows_language as $language) {
			$vals[] = "('" . $category_id . "', '" . $language['language_id'] . "','" . $name . "','','','','')";
		}
		$sql = "INSERT INTO `oc_category_description`(`category_id`, `language_id`, `name`, `description`, `meta_title`, `meta_description`, `meta_keyword`) "
				. "VALUES " . implode(', ', $vals) . ";";
		//var_dump($sql);
		$this->db->query_execute('main', $sql);

		$sql = "INSERT INTO `oc_category_to_store`(`category_id`, `store_id`) VALUES ('" . $category_id . "','0')";
		$this->db->query_execute('main', $sql);

		$sql = "INSERT INTO `oc_category_to_layout` (`category_id`, `store_id`, `layout_id`) VALUES ('{$category_id}', '0', '0')";
		$this->db->query_execute('main', $sql);

		$vals = array();
		$vals[] = "('" . $category_id . "','" . $category_id . "','{$cat['level']}')";
		if ($parent_id != 0) {
			$vals[] = "('" . $category_id . "','" . $parent_id . "','{$cat['level']}')";
		}
		$sql = "INSERT INTO `oc_category_path`(`category_id`, `path_id`, `level`) VALUES " . implode(', ', $vals) . ";";
		//var_dump($sql);
		$this->db->query_execute('main', $sql);
		return $cat;
	}

	protected $languages = null;

	protected function getLanguages() {
		if ($this->languages === null) {
			$sql = "SELECT * FROM `oc_language`;";
			$rows_language = $this->db->query_get_multi_a('main', $sql);
			$this->languages = $rows_language;
		}
		return $this->languages;
	}

	/**
	 * Проверяет/добовляет опции в справочник опций OpenCart
	 *
	 * @param type $opts
	 */
	protected function addOptions($opts) {
		foreach ($opts as $name => $option_values) {
			$rows_language = $this->getLanguages();
			//добавляем опцию и ее название, для всех языков
			if (!isset($this->optionsNames[$name])) {
				$this->optionsNames[$name] = array("option_id" => 0, "vals" => array());
				$sql = "SELECT * FROM `oc_option_description` WHERE `name`='{$name}' LIMIT 1;";
				$rows = $this->db->query_get_multi_a('main', $sql);
				if (count($rows) == 0) {
					$sql = "INSERT INTO `oc_option` (`type`, `sort_order`) VALUES ('select', '10');";
					$option_id = $this->db->query_execute_with_last_inser_id('main', $sql);
					$this->optionsNames[$name]["option_id"] = $option_id;
					$vals = array();
					foreach ($rows_language as $language) {
						$vals[] = "('{$option_id}', '" . $language['language_id'] . "', '{$name}')";
					}
					if (count($vals > 0)) {
						$sql = "INSERT INTO `oc_option_description` (`option_id`, `language_id`, `name`) VALUES " . implode(', ', $vals) . ";";
						$this->db->query_execute('main', $sql);
					}
				} else {
					$this->optionsNames[$name]["option_id"] = $rows[0]['option_id'];
				}
			}
			foreach ($option_values as $option_value_name => $skus) {
				$sql = "SELECT * FROM `oc_option_value_description` WHERE `name`='{$option_value_name}' AND option_id='{$this->optionsNames[$name]["option_id"]}' LIMIT 1;";
				$rows = $this->db->query_get_multi_a('main', $sql);
				if (count($rows) == 0) {
					$sql = "INSERT INTO `oc_option_value` (`option_id`, `sort_order`) VALUES ( '{$this->optionsNames[$name]["option_id"]}', '{$option_value_name}')";
					$option_value_id = $this->db->query_execute_with_last_inser_id('main', $sql);
					$this->optionsNames[$name]['vals'][$option_value_name] = $option_value_id;
					$vals = array();
					foreach ($rows_language as $language) {
						$vals[] = "('{$option_value_id}', '{$language['language_id']}', '{$this->optionsNames[$name]["option_id"]}', '{$option_value_name}')";
					}
					if (count($vals > 0)) {
						$sql = "INSERT INTO `oc_option_value_description` (`option_value_id`, `language_id`, `option_id`, `name`) VALUES " . implode(', ', $vals) . ";";
						$this->db->query_execute('main', $sql);
					}
				} else {
					$this->optionsNames[$name]['vals'][$option_value_name] = $rows[0]['option_value_id'];
				}
			}
		}
	}

	/**
	 * Возвращает группу товаров, с общим cat_entry
	 *
	 * @return array
	 */
	protected function getData() {
		$sql = "SELECT DISTINCT main_cat_entry_id FROM `parsikea2` WHERE inserted=0 ORDER BY id ASC LIMIT 1;";
		//var_dump($sql);
		$rows = $this->db->query_get_multi_a('main', $sql);

		$main_cat_entry_ids = array();
		foreach ($rows as $row) {
			$main_cat_entry_ids[] = "'" . $row['main_cat_entry_id'] . "'";
		}
		$sql = "SELECT * FROM `parsikea2` WHERE main_cat_entry_id IN (" . implode(',', $main_cat_entry_ids) . ");";


		//var_dump($sql);
		$rows = $this->db->query_get_multi_a('main', $sql);
		return $rows;
	}

	/**
	 * Создает связи между товарами
	 */
	public function related() {
		$sql = "TRUNCATE TABLE `oc_product_related`";
		$this->db->query_execute('main', $sql);

		$sql = "SELECT `sku`, `releted` FROM `parsikea2` WHERE `releted`!='';";
		$rows = $this->db->query_get_multi_a('main', $sql);
		$releted = array();
		$sku = array();
		foreach ($rows as $row) {
			$sku[] = "'" . $row['sku'] . "'";
			$releted[$row['sku']] = explode(',', $row['releted']);
			foreach ($releted[$row['sku']] as $k => $v) {
				$releted[$row['sku']][$k] = preg_replace('(.{3})', "$0.", preg_replace("/\D/", "", $releted[$row['sku']][$k]));
			}
		}
		//$sql = "SELECT `product_id`, `sku` FROM `oc_product` WHERE sku IN (" . implode(', ', $sku) . ");";
		$sql="SELECT p.`product_id`, p.`sku`, od.model as sku_sub
			FROM `oc_product` p
			LEFT JOIN oc_otp_data od ON od.product_id=p.product_id WHERE sku IN (" . implode(', ', $sku) . ");";
		$rows = $this->db->query_get_multi_a('main', $sql);

		$products = array();
		foreach ($rows as $row) {
			$products[$row['sku']] = $row['product_id'];
			if($row['sku_sub']!=''){
				$products[$row['sku_sub']] = $row['product_id'];
			}
		}
		$vals = array();
		foreach ($releted as $_sku => $rel_skus) {
			if (isset($products[$_sku])) {
				foreach ($rel_skus as $rel_sku) {
					if (isset($products[$rel_sku])) {
						$vals[] = "('" . $products[$_sku] . "','" . $products[$rel_sku] . "')";
						$vals[] = "('" . $products[$rel_sku] . "','" . $products[$_sku] . "')";
					}
				}
			}
		}
		if (count($vals) > 0) {
			$sql = "INSERT INTO `oc_product_related`(`product_id`, `related_id`) VALUES " . implode(', ', $vals) . ";";
			$this->db->query_execute('main', $sql);
			echo 'Дополняющие товары: ГОТОВО ' . count($vals);
		} else {
			echo 'Дополняющие товары: не найдено связей';
		}
	}

}

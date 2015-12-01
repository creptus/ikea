<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DB
 *
 * @author alexey.korolev
 */

namespace ikea;

class DB {

	/**
	 * Reaiurce links
	 * @var array
	 */
	private $link = array();

	public function __construct($id, $host, $user, $pass, $db, $charset = 'UTF8') {
		$this->connect($id, $host, $user, $pass, $db, $charset);
	}

	/**
	 *
	 * @param string $id    идентификатора соеденения
	 * @param string $host  адрес
	 * @param string $user  пользователь
	 * @param string $pass  пароль
	 * @param string $db    имя бд
	 * @param string $charset  кодировока, по умаолчанию UTF8
	 */
	public function connect($id, $host, $user, $pass, $db, $charset = 'UTF8') {
		$this->link[$id] = mysqli_connect($host, $user, $pass, $db);
		$this->query_execute($id, 'SET CHARSET ' . $charset);

		if (!$this->link[$id]) {
			printf("Connect failed: %s\n", mysqli_connect_error());
		}
	}

	/**
	 * Выполняется SQL запрос и возвращается массив, содержащий ссылки на объекты.
	 *
	 *
	 * @param string $id      идентификатора соеденения ResourceLinkId
	 * @param string $query  SQL запрос
	 * @return array
	 */
	public function query_get_multi_a($id, $query) {
		$res = mysqli_query($this->link[$id], $query);
		$result=array();
		if($res!==false && $res!==TRUE){
			$result=mysqli_fetch_all($res, MYSQLI_ASSOC);
		}

		$this->addError($id,$query);
		return $result;
	}

	/**
	 * Выполняет SQL запрос
	 * @param string $query   SQL запрос
	 * @return int
	 */
	public function query_execute($id, $query) {
		mysqli_query($this->link[$id], $query);
		$this->addError($id,$query);
		return mysqli_affected_rows($this->link[$id]);
	}

	/**
	 *
	 * @param string $id
	 * @param string $query
	 * @return int
	 */
	public function query_execute_with_last_inser_id($id, $query) {
		mysqli_query($this->link[$id], $query);
		$this->addError($id,$query);
		return mysqli_insert_id($this->link[$id]);
	}

	protected $errors = array();

	public function getErrors() {
		return $this->errors;
	}

	protected function addError($id,$query) {
		$err = mysqli_error($this->link[$id]);
		if ($err != '') {
			$this->errors[] = [$err,$query];
		}
	}

}

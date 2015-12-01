<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
set_time_limit(0);
ini_set("memory_limit", "128M");
header('Content-Type: text/html; charset=utf-8');

include './DB.php';
include './OpenCartConvertor.php';




$count = 10;
if (isset($_GET['count'])) {
	$count = intval($_GET['count']);
}


$convertor = new \ikea\OpenCartConvertor(new ikea\DB('main', 'localhost', 'root', '', 'newikea'));

if(isset($_GET['relates'])){
	echo $convertor->related();
	die();
}

for ($i = 0; $i < $count; $i++) {
	$result = $convertor->convert('C:\xampp\htdocs\new.ikeazakaz.ru.dev\\');
	if($result===false){
		break;
	}
}

$res = '{{TRUE}}';
if (!$result) {
	$res = '{{FALSE}}';
}
echo $count.$res;


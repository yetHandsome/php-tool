<?php
error_reporting(-1);

$config=[
            //redis地址
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => ''
        ];
include  dirname(__DIR__) . '/bin/cli/class/autoload.php';

$id = lib\SnowId::get($config);
$id2 = lib\SnowId::get($config);
$id3 = lib\SnowId::get($config);
$id4 = lib\SnowId::get($config);
$id5 = lib\SnowId::get($config);
$id6 = lib\SnowId::get($config);
var_dump($id);echo '<br>';
var_dump($id2);echo '<br>';
var_dump($id3);echo '<br>';
var_dump($id4);echo '<br>';
var_dump($id5);echo '<br>';
var_dump($id6);echo '<br>';
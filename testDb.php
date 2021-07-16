<?php

error_reporting(-1); //E_ERROR | E_PARSE
date_default_timezone_set('Asia/Shanghai');
ini_set("max_execution_time",6000);

$config=include __DIR__.'/config.php';
include  __DIR__ . '/bin/cli/class/autoload.php';

$db_conf = [
        'type' => 'mysql',
        'hostname' => "127.0.0.1",
        'hostport' => "3306",
        'database' => "INFORMATION_SCHEMA",
        'username' => "username",
        'password' => "password",
        'charset' => "utf8",
    ];

$db1 = new lib\Model($db_conf);

//查询出 username 与 database 直接的对应关系
$res = $db1->table('COLUMNS')->field('count(*) as c')->find();

var_dump($res['c']);
echo PHP_EOL;
echo 'sleep 60s, please restart MySQL server'.PHP_EOL; // 看到 sleep 输出重启数据库，这样肯定会断开连接
echo sleep(60);

$res = $db1->table('COLUMNS')->field('count(*) as c')->find();

var_dump($res['c']);

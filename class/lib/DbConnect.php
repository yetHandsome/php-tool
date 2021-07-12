<?php

namespace lib;

class DbConnect{
    private static $Instace = [];           ///对象
    
    private function __construct(){ //不允许实利化
    }
    
    private function __clone(){ //不允许克隆
    }

    public static function getInstace($params,$name = '',$reallyConnet=false){
         //添加一个name变量，可以使相同配置可根据name不同有多个连接，建议有事务的地方都加一个特定name（用于避免事务嵌套使用同一个连接）
        $name = !empty($name) ? $name : md5(json_encode($params));
        
        //如果没有连接过或者强制重连就连接一次数据库，否则直接返回上次连接对象
        if(!isset(static::$Instace[$name]) || $justConnet){
            static::$Instace[$name] = static::connectDb($params);
        }
        
        return static::$Instace[$name];
    }
    
//    $params = [
//        'type' => 'mysql',
//        'hostname' => "127.0.0.1",
//        'hostport' => "3306",
//        'database' => "db1",
//        'username' => "username",
//        'password' => "password",
//        'charset' => "utf8",
//    ]
    public static function connectDb($params){

        $dbms   = $params['type'];
        $host   = $params['hostname'];
        $port   = $params['hostport'];
        $dbName = isset($params['database']) ? 'dbname='.$params['database'] : '';
        $user   = $params['username'];
        $pass   = $params['password'];
        $charset = isset($params['charset']) ? 'charset='.$params['charset'] : '';
        
        $dsn    = "{$dbms}:host={$host};port={$port};{$dbName};{$charset}"; //;charset=utf8设置数据库编码可提高安全性
        
        try {
            $dbh = new \PDO($dsn, $user, $pass); //初始化一个PDO对象
            $dbh->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false); //禁止PHP模拟预编译
            return $dbh;
        } catch (\PDOException $e) {
            die ("Error!: " . $e->getMessage() . "<br/>");
        }
    }
}

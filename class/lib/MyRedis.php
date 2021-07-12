<?php

namespace lib;

use lib\Sentinel;

class MyRedis{
    private static $redisInstace = [];           //redis对象
    private static $sentinel = null;           //sentinel对象
    private static $readMode = 1;           //1.主从均匀随机读，2.读只请求从，3.读写都走主
    private static $AllNodeNum = 3;           //总节点个数
     
    private function __construct(){ //不允许实利化
    }
    
    private function __clone(){ //不允许克隆
    }
    
    public static function init($sentinelConfig){
        self::$readMode = $sentinelConfig['readMode'];
        self::$AllNodeNum = $sentinelConfig['AllNodeNum'];
        Sentinel::setConfig($sentinelConfig);
    }
    
    public static function run($type){
        $sentinel = self::$sentinel;
        if($type == 'slave'){
            $config = Sentinel::getSlaveConfig();
        }else{
            $config = Sentinel::getMasterConfig();
        }
        return self::getInstace($config);
    }
    
    public static function getInstace($config,$name=''){
        $params = !empty($name) ? $name : md5(json_encode($config));
        if(!isset(self::$redisInstace[$params])){
            self::$redisInstace[$params] = static::connectRedis($config);
        }
        return self::$redisInstace[$params];
    }
    
    private static function connectRedis($config) {
        $redis = new \Redis();
        $port = $config['port'] ? $config['port'] : 6379;
        $host = $config['host'] ? $config['host'] : '127.0.0.1';
        $redis->connect($host, $port);

        if ($config['password']) {
            $redis->auth($config['password']);
        }
        
        return $redis;
    }
    
    public static function __callStatic($name,$param) {
        $type = 'master';
        if(self::$readMode == 3){
            $type = 'master';
        }else{
            if(self::$readMode == 1){
                $n = rand(1,self::$AllNodeNum);
                if($n == self::$AllNodeNum){
                    $type = 'master';
                }else{
                    if(in_array($name, ['get','hget','hmget']) && self::$AllNodeNum>1){
                        $type = 'slave';
                    }
                }
            }else{
                if(in_array($name, ['get','hget','hmget']) && self::$AllNodeNum>1){
                    $type = 'slave';
                }
            }
            
        }
        return call_user_func_array(array(self::run($type),$name),$param);
        
    }
    
    
    public static function gzSet($redis,$redis_key,$redis_data,$expire=0){
        $redis->set($redis_key, gzcompress(json_encode($redis_data)));
        if($expire){
            $redis->expire($redis_key,$expire);
        }
    }
    
    public static function gzGet($redis,$redis_key){
        $redis_data = $redis->get($redis_key);
        if($redis_data){
            $redis_data = gzuncompress($redis_data);
            $redis_data = json_decode($redis_data,true);
        }
        return $redis_data;
        
    }


}

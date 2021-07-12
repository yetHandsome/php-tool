<?php

namespace lib;

use lib\MyRedis;

class SnowId{
    private static $redis = null;
    
    private static $redis_key_prefix = 'snowId_';
    
    private static $expire = 5;
    
    private static $leftZero = 5; //左补齐0，共5位 00001~99999
    
    public static function getId($redis){
        $msectime = self::msectime();
        
        $redisKey = self::$redis_key_prefix.$msectime;
        
        $serialNumber = $redis->incr($redisKey);
        
        $redis->expire($redisKey,$expire);
       
        $beautifySerialNumber = self::beautifyNumber($serialNumber);
        
        $id = $msectime.$beautifySerialNumber;
        
        return $id;
    }
    
    public static function get($redisConfig){
        //var_dump($redisConfig);die;
        $redis = MyRedis::getInstace($redisConfig);
        
        return self::getId($redis);
    }
    
    //返回当前的毫秒时间戳
    public static function msectime() {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);

        return $msectime;
    }
    
    //美化数字,左补齐 self::$leftZero 个 0
    public static function beautifyNumber($num) {
        $newStr = sprintf('%0'.intval(self::$leftZero).'s', $num);
        return $newStr;
    }
}


<?php

namespace lib;

use lib\MyRedis;

class SnowId{
    private static $redis = null;
    
    private static $redis_key_prefix = 'snowId:';
    
    private static $expire = 600; //key保留10分钟避免分布式某台机器时间戳有问题（所有机器时间差最大值不能超过10分钟）
    
    private static $leftZero = 2; //左补齐0，共2位 01~99
    
    private static $top = 99; //防止溢出，可以通过 $leftZero 算出防止溢出边界值，但是这样会耗时，写死程序效率更高
    
    private static $need_rand = false; //雪花id最后是否添加3位随机数
    

    public static function getId($redis,$partition){
        list($sec,$msec) = self::msectime();
        
        $redisKey = self::$redis_key_prefix.$sec;
        
        $serialNumber = $redis->hincrBy($redisKey,$msec,1);
                
        if($serialNumber>self::$top){
            usleep(1000); //暂停1毫秒
            return self::getId($redis,$partition);
        }
        
        $redis->expire($redisKey,self::$expire);
       
        $beautifySerialNumber = self::beautifyNumber($serialNumber);
        
        $br = '';
        
         if(self::$need_rand){
            $r = mt_rand(0,999);
            $br = sprintf('%0'.'3s', $r);
        }
        
        $msec = sprintf('%0'.'3s', $msec);
        //组成部分是 秒 占10位、毫秒 3位、分区 1位，该毫秒自增值 2位（有防止溢出处理），一共16位
        $id = $sec.$msec.$partition.$beautifySerialNumber.$br;
        
        return $id;
    }
    
    public static function get($redisConfig,$partition=0){
        //var_dump($redisConfig);die;
        $redis = MyRedis::getInstace($redisConfig);
        
        return self::getId($redis,$partition);
    }
    
    //返回当前的毫秒时间戳
    public static function msectime() {
        list($msec, $sec) = explode(' ', microtime());
        $msec = substr($msec,2,3);

        return [$sec,$msec];
    }
    
    //美化数字,左补齐 self::$leftZero 个 0
    public static function beautifyNumber($num) {
        $newStr = sprintf('%0'.intval(self::$leftZero).'s', $num);
        return $newStr;
    }
}


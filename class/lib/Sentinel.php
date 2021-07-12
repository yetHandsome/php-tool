<?php

namespace lib;

class Sentinel{
    private static $sentinelConfig = null;           //sentinel配置
    
    private function __construct(){ //不允许实利化
    }
    
    private function __clone(){ //不允许克隆
    }
    
    public static function setConfig($sentinelConfig){
        self::$sentinelConfig = $sentinelConfig;
    }
    
    public static function getMasterConfig(){
        if(empty(self::$sentinelConfig)){
            exit('please use sentinel::setInstace($sentinelConfig); before sentinel::getMasterConfig()');
        }
        foreach (self::$sentinelConfig['sentinel_config'] as $k => $v) {
             //初始化redis对象
            $redis = new \Redis();
            try {
                $status = @$redis->connect($v['host'], $v['port'],1);
                if(!$status){
                    continue;
                }
            } catch (\Exception $exc) {
                continue;
            }

            $result = $redis->rawCommand('SENTINEL', 'master', self::$sentinelConfig['master_name']);

            $master_ip = $result[3];
            $master_port = $result[5];
            
            return ['host'=>$master_ip,'port'=>$master_port,'password'=>self::$sentinelConfig['password']];
        }
       
    }
    
    public static function getSlaveConfig(){
        $allSlavesConfig = self::getSlaveAllConfig();
        $num = count($allSlavesConfig);
        $rand = rand(1,$num);
        return $allSlavesConfig[$rand-1];
    }
    
    public static function getSlaveAllConfig(){
        if(empty(self::$sentinelConfig)){
            exit('please use sentinel::setInstace($sentinelConfig); before sentinel::getMasterConfig()');
        }
        foreach (self::$sentinelConfig['sentinel_config'] as $k => $v) {
             //初始化redis对象
            $redis = new \Redis();
           
            try {
                $status = @$redis->connect($v['host'], $v['port'],1);
                if(!$status){
                    continue;
                }
            } catch (\Exception $exc) {
                continue;
            }

            $result = $redis->rawCommand('SENTINEL', 'slaves', self::$sentinelConfig['master_name']);
            $slaves = [];
            foreach ($result as $k => $v) {
                if($v[9]=='slave'){
                    $slaves_ip = $v[3];
                    $slaves_port = $v[5];
                    $slaves[] = ['host'=>$slaves_ip,'port'=>$slaves_port,'password'=>self::$sentinelConfig['password']];
                }
            }
            return $slaves;
        }
    }
    

    
}


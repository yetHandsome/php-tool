<?php

include  dirname(__DIR__) . '/class/autoload.php';

$config = ['password'=>'zhendao_zhendao123',
            'master_name'=>'mymaster',
            'AllNodeNum'=>3,
            'readMode' => 1, //1.主从均匀随机读，2.读只请求从，3.读写都走主
            'sentinel_config'=>[
                ['host'=>'172.25.0.210','port'=>26379],
                ['host'=>'172.25.0.211','port'=>26379],
                ['host'=>'172.25.0.212','port'=>26379]
            ]
        ];

lib\MyRedis::init($config);

lib\MyRedis::set('k1','v1');

$res = lib\MyRedis::get('k1');
var_dump($res);die;

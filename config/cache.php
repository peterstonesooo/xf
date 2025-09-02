<?php

return [
    // 默认缓存驱动
    'default' => 'file',

    // 缓存连接配置
    'stores' => [
        'file' => [
            'type' => 'File',
            'path' => runtime_path() . 'cache',
        ],
        'redis' => [
            'type' => 'redis',
            'host' => '1Panel-redis-ds0x',
            'port' => 6379,
            'password' => 'redis_zhDTMA',
            'select' => 3,
            'timeout' => 0,
            'persistent' => false,
        ],
    ],
];

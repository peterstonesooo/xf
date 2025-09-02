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
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => '',
            'select' => 0,
            'timeout' => 0,
            'persistent' => false,
        ],
    ],
];

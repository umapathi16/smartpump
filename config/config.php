<?php

function config($key)
{
    static $configs = [];

    [$file, $value] = explode('.', $key);

    if (!isset($configs[$file])) {
        $configs[$file] = require __DIR__ . "/{$file}.php";
    }

    return $configs[$file][$value] ?? null;
}

date_default_timezone_set(
    'Asia/Singapore'
);

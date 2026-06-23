<?php

use App\Providers\AppServiceProvider;

return [
    'name' => 'jq',
    'version' => trim((string) @file_get_contents(__DIR__.'/../version.txt')) ?: 'unreleased',
    'env' => 'development',
    'providers' => [
        AppServiceProvider::class,
    ],
];

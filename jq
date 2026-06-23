#!/usr/bin/env php
<?php

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
*/

$autoloader = require file_exists(__DIR__.'/vendor/autoload.php') ? __DIR__.'/vendor/autoload.php' : __DIR__.'/../../autoload.php';

/*
|--------------------------------------------------------------------------
| jq mode interception
|--------------------------------------------------------------------------
| jq's CLI grammar (a bare FILTER argument, repeatable -x flags, --arg name
| value) does not fit Symfony's option parser. Unless the first argument is a
| reserved framework subcommand, treat the invocation as jq and drive the
| engine directly.
*/

$jqArgs = array_slice($_SERVER['argv'] ?? [], 1);
$jqFirst = $jqArgs[0] ?? null;
$jqReserved = ['self-update', 'app:build', 'list', 'help', 'completion', '--help', '-h', '--version', '-V', '--ansi', '--no-ansi'];

if ($jqFirst === null || ! in_array($jqFirst, $jqReserved, true)) {
    exit(\App\Jq\Cli\Runner::main($jqArgs));
}

$app = require_once __DIR__.'/bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Run The Artisan Application
|--------------------------------------------------------------------------
*/

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$status = $kernel->handle(
    $input = new Symfony\Component\Console\Input\ArgvInput,
    new Symfony\Component\Console\Output\ConsoleOutput
);

/*
|--------------------------------------------------------------------------
| Shutdown The Application
|--------------------------------------------------------------------------
*/

$kernel->terminate($input, $status);

exit($status);

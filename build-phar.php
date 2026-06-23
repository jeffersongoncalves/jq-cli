#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Standalone PHAR builder
|--------------------------------------------------------------------------
| Box is the usual Laravel Zero packager, but its temp-directory cleanup is
| unreliable on Windows (rmdir "Resource temporarily unavailable"). This
| script builds builds/jq.phar directly with the Phar API instead.
|
| Usage: php -d phar.readonly=0 build-phar.php
*/

if (ini_get('phar.readonly')) {
    fwrite(STDERR, "phar.readonly is on. Run: php -d phar.readonly=0 build-phar.php\n");
    exit(1);
}

$base = __DIR__;
$output = $base.'/builds/jq.phar';

if (! is_dir($base.'/builds')) {
    mkdir($base.'/builds', 0755, true);
}
if (file_exists($output)) {
    unlink($output);
}

$phar = new Phar($output, 0, 'jq.phar');
$phar->startBuffering();

$include = ['app', 'bootstrap', 'config', 'vendor'];
$count = 0;

foreach ($include as $dir) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base.'/'.$dir, FilesystemIterator::SKIP_DOTS)
    );
    $phar->buildFromIterator($iterator, $base);
    $count++;
}

// loose root files needed at runtime
foreach (['jq', 'composer.json', 'version.txt'] as $rootFile) {
    if (file_exists($base.'/'.$rootFile)) {
        $phar->addFile($base.'/'.$rootFile, $rootFile);
    }
}

$stub = <<<'STUB'
#!/usr/bin/env php
<?php
Phar::mapPhar('jq.phar');
require 'phar://jq.phar/jq';
__HALT_COMPILER();
STUB;

$phar->setStub($stub);
$phar->stopBuffering();

echo 'Built '.$output.' ('.$phar->count().' files, '.round(filesize($output) / 1024 / 1024, 2)." MB)\n";

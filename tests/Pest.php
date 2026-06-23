<?php

use App\Jq\Cli\CliConfig;
use App\Jq\Engine;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Runs a jq program against an input document and returns the compact output
 * lines. $opts may set any CliConfig flag (e.g. ['rawOutput' => true]).
 *
 * @return list<string>
 */
function jq(string $program, string $input = 'null', array $opts = []): array
{
    $result = jqFull($program, $input, $opts);

    return $result['lines'];
}

/**
 * @return array{code: int, out: string, err: string, lines: list<string>}
 */
function jqFull(string $program, string $input = 'null', array $opts = []): array
{
    $cfg = new CliConfig;
    $cfg->compact = true;
    foreach ($opts as $key => $value) {
        $cfg->{$key} = $value;
    }

    $out = fopen('php://memory', 'r+');
    $err = fopen('php://memory', 'r+');
    $code = (new Engine($out, $err))->run($cfg, $program, $input);

    rewind($out);
    rewind($err);
    $stdout = (string) stream_get_contents($out);
    $stderr = (string) stream_get_contents($err);

    $lines = $stdout === '' ? [] : explode("\n", rtrim($stdout, "\n"));

    return ['code' => $code, 'out' => $stdout, 'err' => $stderr, 'lines' => $lines];
}

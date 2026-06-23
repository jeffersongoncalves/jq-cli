<?php

declare(strict_types=1);

use App\Jq\Engine;

it('reads multiple concatenated documents', function () {
    expect(jq('. + 1', '1 2 3'))->toBe(['2', '3', '4']);
});

it('reads NDJSON', function () {
    expect(jq('.x', "{\"x\":1}\n{\"x\":2}"))->toBe(['1', '2']);
});

it('slurps input into an array', function () {
    expect(jq('add', '1 2 3', ['slurp' => true]))->toBe(['6']);
});

it('treats each line as a raw string with --raw-input', function () {
    expect(jq('.', "a\nb", ['rawInput' => true]))->toBe(['"a"', '"b"']);
});

it('slurps raw input into one string', function () {
    expect(jq('length', "ab\ncd", ['rawInput' => true, 'slurp' => true]))->toBe(['5']);
});

it('emits raw output without quotes', function () {
    $result = jqFull('.name', '{"name":"Sam"}', ['rawOutput' => true]);
    expect($result['out'])->toBe("Sam\n");
});

it('joins output without newlines', function () {
    $result = jqFull('.[]', '["a","b"]', ['rawOutput' => true, 'joinOutput' => true]);
    expect($result['out'])->toBe('ab');
});

it('uses null input with -n', function () {
    expect(jq('1 + 2', '', ['nullInput' => true]))->toBe(['3']);
});

it('pulls additional inputs with the input builtin', function () {
    // first run consumes 1 and pulls 2; the remaining 3 then has no partner
    expect(jq('[., input]', '1 2 3'))->toBe(['[1,2]']);
});

it('collects all remaining inputs with inputs', function () {
    expect(jq('[inputs]', '1 2 3 4'))->toBe(['[2,3,4]']);
});

it('exposes named arguments as variables', function () {
    expect(jq('$x', 'null', ['namedArgs' => ['x' => 'value']]))->toBe(['"value"']);
});

it('returns exit code 1 for a false/null last output with -e', function () {
    expect(jqFull('.a', '{}', ['exitStatus' => true])['code'])->toBe(Engine::EXIT_FALSE_NULL);
});

it('returns exit code 4 when -e produces no output', function () {
    expect(jqFull('empty', '{}', ['exitStatus' => true])['code'])->toBe(Engine::EXIT_NO_OUTPUT);
});

it('returns exit code 0 for a truthy last output with -e', function () {
    expect(jqFull('.a', '{"a":1}', ['exitStatus' => true])['code'])->toBe(Engine::EXIT_OK);
});

it('returns exit code 3 for a program compile error', function () {
    $result = jqFull('.[', '{}');
    expect($result['code'])->toBe(Engine::EXIT_COMPILE);
    expect($result['err'])->toContain('error');
});

it('returns exit code 5 for invalid input JSON', function () {
    expect(jqFull('.', '{bad json')['code'])->toBe(Engine::EXIT_IO);
});

it('reports a runtime error and exits 5', function () {
    $result = jqFull('.a', '1');
    expect($result['code'])->toBe(Engine::EXIT_IO);
    expect($result['err'])->toContain('Cannot index');
});

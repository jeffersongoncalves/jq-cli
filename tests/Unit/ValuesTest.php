<?php

declare(strict_types=1);

use App\Jq\Runtime\JsonObject;
use App\Jq\Runtime\Values;

it('names types like jq', function () {
    expect(Values::typeName(null))->toBe('null');
    expect(Values::typeName(true))->toBe('boolean');
    expect(Values::typeName(1))->toBe('number');
    expect(Values::typeName(1.5))->toBe('number');
    expect(Values::typeName('x'))->toBe('string');
    expect(Values::typeName([1, 2]))->toBe('array');
    expect(Values::typeName(new JsonObject(['a' => 1])))->toBe('object');
});

it('orders values with jq total ordering', function () {
    $values = [new JsonObject(['a' => 1]), [], 'a', 1, true, false, null];
    usort($values, fn ($a, $b) => Values::compare($a, $b));

    expect(array_map(fn ($v) => Values::typeName($v), $values))
        ->toBe(['null', 'boolean', 'boolean', 'number', 'string', 'array', 'object']);
    expect($values[1])->toBeFalse();
    expect($values[2])->toBeTrue();
});

it('treats only null and false as falsy', function () {
    expect(Values::truthy(null))->toBeFalse();
    expect(Values::truthy(false))->toBeFalse();
    expect(Values::truthy(0))->toBeTrue();
    expect(Values::truthy(''))->toBeTrue();
    expect(Values::truthy([]))->toBeTrue();
});

it('encodes without escaping slashes and collapses integral floats', function () {
    expect(Values::encode('a/b', false))->toBe('"a/b"');
    expect(Values::encode(2.0, false))->toBe('2');
    expect(Values::encode(1.5, false))->toBe('1.5');
    expect(Values::encode(new JsonObject(['b' => 1, 'a' => 2]), false))->toBe('{"b":1,"a":2}');
    expect(Values::encode(new JsonObject(['b' => 1, 'a' => 2]), false, ['sortKeys' => true]))->toBe('{"a":2,"b":1}');
});

<?php

declare(strict_types=1);

dataset('jq cases', [
    // identity & paths
    'identity' => ['.', '{"a":1}', ['{"a":1}']],
    'field' => ['.a', '{"a":1,"b":2}', ['1']],
    'nested field' => ['.a.b', '{"a":{"b":42}}', ['42']],
    'optional missing' => ['.a.b?', '{"a":null}', ['null']],
    'index' => ['.[1]', '[10,20,30]', ['20']],
    'negative index' => ['.[-1]', '[10,20,30]', ['30']],
    'slice' => ['.[1:3]', '[1,2,3,4,5]', ['[2,3]']],
    'string slice' => ['.[0:3]', '"hello"', ['"hel"']],
    'iterate array' => ['.[]', '[1,2,3]', ['1', '2', '3']],
    'iterate object' => ['.[]', '{"a":1,"b":2}', ['1', '2']],
    'recurse' => ['..', '{"a":[1]}', ['{"a":[1]}', '[1]', '1']],

    // pipes and comma
    'pipe' => ['.a | .b', '{"a":{"b":5}}', ['5']],
    'comma' => ['.a, .b', '{"a":1,"b":2}', ['1', '2']],

    // construction
    'array construct' => ['[.[] | . * 2]', '[1,2,3]', ['[2,4,6]']],
    'object construct' => ['{x: .a, y: 2}', '{"a":1}', ['{"x":1,"y":2}']],
    'object shorthand' => ['{a, b}', '{"a":1,"b":2,"c":3}', ['{"a":1,"b":2}']],
    'object dynamic key' => ['{(.k): .v}', '{"k":"name","v":"sam"}', ['{"name":"sam"}']],
    'string interp' => ['"\(.a)-\(.b)"', '{"a":1,"b":2}', ['"1-2"']],

    // arithmetic & comparison
    'add numbers' => ['.a + .b', '{"a":2,"b":3}', ['5']],
    'concat strings' => ['.a + .b', '{"a":"x","b":"y"}', ['"xy"']],
    'concat arrays' => ['.a + .b', '{"a":[1],"b":[2]}', ['[1,2]']],
    'merge objects' => ['.a + .b', '{"a":{"x":1},"b":{"y":2}}', ['{"x":1,"y":2}']],
    'subtract arrays' => ['. - ["b"]', '["a","b","c","b"]', ['["a","c"]']],
    'comparison' => ['.a > .b', '{"a":3,"b":2}', ['true']],
    'equality' => ['.a == .b', '{"a":1,"b":1}', ['true']],

    // flow
    'alternative' => ['.a // "def"', '{"b":1}', ['"def"']],
    'if then else' => ['if . > 2 then "big" else "small" end', '3', ['"big"']],
    'try catch' => ['try error("boom") catch .', 'null', ['"boom"']],
    'optional error' => ['.[]?', '5', []],
    'reduce' => ['reduce .[] as $x (0; . + $x)', '[1,2,3,4]', ['10']],
    'foreach' => ['[foreach .[] as $x (0; . + $x)]', '[1,2,3]', ['[1,3,6]']],

    // binding & destructuring
    'binding' => ['. as $x | $x + 1', '5', ['6']],
    'array destructure' => ['. as [$a, $b] | $a + $b', '[10,20]', ['30']],
    'object destructure' => ['. as {a: $x} | $x', '{"a":7}', ['7']],

    // builtins
    'length array' => ['length', '[1,2,3]', ['3']],
    'length string' => ['length', '"héllo"', ['5']],
    'keys' => ['keys', '{"b":1,"a":2}', ['["a","b"]']],
    'map' => ['map(. + 1)', '[1,2,3]', ['[2,3,4]']],
    'select' => ['[.[] | select(. > 1)]', '[1,2,3]', ['[2,3]']],
    'add' => ['add', '[1,2,3,4]', ['10']],
    'range' => ['[range(1;4)]', 'null', ['[1,2,3]']],
    'sort' => ['sort', '[3,1,2]', ['[1,2,3]']],
    'sort_by' => ['sort_by(.x)', '[{"x":3},{"x":1}]', ['[{"x":1},{"x":3}]']],
    'group_by' => ['group_by(.)', '[1,1,2]', ['[[1,1],[2]]']],
    'unique' => ['unique', '[3,1,2,1,3]', ['[1,2,3]']],
    'min max' => ['[min, max]', '[3,1,2]', ['[1,3]']],
    'flatten' => ['flatten', '[1,[2,[3]]]', ['[1,2,3]']],
    'to_entries' => ['to_entries', '{"a":1}', ['[{"key":"a","value":1}]']],
    'from_entries' => ['from_entries', '[{"key":"a","value":1}]', ['{"a":1}']],
    'with_entries' => ['with_entries(.value += 1)', '{"a":1,"b":2}', ['{"a":2,"b":3}']],
    'ascii_upcase' => ['ascii_upcase', '"abc"', ['"ABC"']],
    'join' => ['join(",")', '["a","b","c"]', ['"a,b,c"']],
    'split' => ['split(",")', '"a,b,c"', ['["a","b","c"]']],
    'tostring' => ['[.[] | tostring]', '[1,true,null]', ['["1","true","null"]']],
    'type' => ['[.[] | type]', '[1,"a",null,[],{}]', ['["number","string","null","array","object"]']],
    'has' => ['has("a")', '{"a":1}', ['true']],
    'contains' => ['contains({"a":1})', '{"a":1,"b":2}', ['true']],
    'first' => ['first(.[])', '[5,6,7]', ['5']],
    'last' => ['last', '[5,6,7]', ['7']],
    'limit' => ['[limit(2; .[])]', '[1,2,3,4]', ['[1,2]']],
    'any' => ['any', '[false,true]', ['true']],
    'all' => ['all', '[true,false]', ['false']],

    // paths & updates
    'assign' => ['.a = 5', '{"a":1}', ['{"a":5}']],
    'update' => ['.a |= . + 1', '{"a":1}', ['{"a":2}']],
    'arith update' => ['.a += 10', '{"a":1}', ['{"a":11}']],
    'del' => ['del(.a)', '{"a":1,"b":2}', ['{"b":2}']],
    'paths' => ['[paths]', '{"a":{"b":1}}', ['[["a"],["a","b"]]']],
    'getpath' => ['getpath(["a","b"])', '{"a":{"b":9}}', ['9']],
    'setpath' => ['setpath(["a","b"]; 9)', '{"a":{"b":1}}', ['{"a":{"b":9}}']],

    // regex
    'test' => ['test("^h")', '"hello"', ['true']],
    'match offset' => ['[match("l"; "g") | .offset]', '"hello"', ['[2,3]']],
    'capture' => ['capture("(?<x>[a-z]+)")', '"abc"', ['{"x":"abc"}']],
    'gsub' => ['gsub("l"; "L")', '"hello"', ['"heLLo"']],
    'sub' => ['sub("l"; "L")', '"hello"', ['"heLlo"']],

    // formats
    'base64' => ['@base64', '"hello"', ['"aGVsbG8="']],
    'base64 roundtrip' => ['@base64 | @base64d', '"hi"', ['"hi"']],
    'csv' => ['@csv', '[1,"a",true]', ['"1,\"a\",true"']],
    'json format' => ['@json', '{"a":1}', ['"{\"a\":1}"']],

    // total ordering
    'total order sort' => ['sort', '[null, true, 1, "a", [], {}, false]', ['[null,false,true,1,"a",[],{}]']],
]);

it('evaluates jq programs', function (string $program, string $input, array $expected) {
    expect(jq($program, $input))->toBe($expected);
})->with('jq cases');

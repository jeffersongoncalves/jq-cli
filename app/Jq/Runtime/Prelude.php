<?php

declare(strict_types=1);

namespace App\Jq\Runtime;

/**
 * jq-defined builtins. Written in the jq language itself and parsed once into
 * the base environment, exactly like jq's own builtin.jq. Keeping these out of
 * PHP keeps the native surface small and the semantics faithful.
 */
final class Prelude
{
    public const SOURCE = <<<'JQ'
        def map(f): [.[] | f];
        def select(f): if f then . else empty end;
        def recurse(f): def r: ., (f | r); r;
        def recurse(f; cond): def r: ., (f | select(cond) | r); r;
        def recurse: recurse(.[]?);
        def recurse_down: recurse;
        def map_values(f): .[] |= f;
        def values: select(. != null);
        def nulls: select(. == null);
        def booleans: select(type == "boolean");
        def numbers: select(type == "number");
        def strings: select(type == "string");
        def arrays: select(type == "array");
        def objects: select(type == "object");
        def iterables: select(type | . == "array" or . == "object");
        def scalars: select(type | . != "array" and . != "object");
        def not: if . then false else true end;
        def abs: if type == "number" then (if . < 0 then - . else . end) else . end;
        def to_entries: [keys_unsorted[] as $k | {key: $k, value: .[$k]}];
        def from_entries:
            reduce .[] as $x ({};
                . + {
                    ($x.key // $x.k // $x.name // $x.Name | if type == "string" then . else tojson end):
                    (if $x | has("value") then $x.value else $x.v end)
                });
        def with_entries(f): to_entries | map(f) | from_entries;
        def add: reduce .[] as $x (null; . + $x);
        def any: reduce .[] as $x (false; . or $x);
        def any(f): reduce (.[] | f) as $x (false; . or $x);
        def any(g; f): reduce (g | f) as $x (false; . or $x);
        def all: reduce .[] as $x (true; . and $x);
        def all(f): reduce (.[] | f) as $x (true; . and $x);
        def all(g; f): reduce (g | f) as $x (true; . and $x);
        def flatten: flatten(1e9);
        def flatten(d):
            reduce .[] as $x ([];
                if ($x | type) == "array" and d > 0 then . + ($x | flatten(d - 1)) else . + [$x] end);
        def del(f): delpaths([path(f)]);
        def paths: path(..) | select(length > 0);
        def paths(f): . as $in | paths | select(. as $p | $in | getpath($p) | f);
        def leaf_paths: paths(scalars);
        def join(sep):
            reduce .[] as $x (null;
                (if . == null then "" else . + sep end)
                + ($x | if . == null then "" elif type == "string" then . else tojson end)) // "";
        def first(g): label $out | g | ., break $out;
        def first: .[0];
        def last(g): reduce g as $x (null; $x);
        def last: .[-1];
        def nth(n): .[n];
        def limit(n; f):
            if n > 0 then label $out | foreach f as $item (0; . + 1; $item, if . >= n then break $out else empty end)
            elif n == 0 then empty
            else f end;
        def nth(n; g): if n < 0 then error("Out of bounds negative array index") else last(limit(n + 1; g)) end;
        def until(cond; update): def _until: if cond then . else (update | _until) end; _until;
        def while(cond; update): def _while: if cond then ., (update | _while) else empty end; _while;
        def repeat(f): def _repeat: f, _repeat; _repeat;
        def index(i): indices(i) | .[0];
        def rindex(i): indices(i) | .[-1:][0];
        def inside(xs): . as $x | xs | contains($x);
        def in(xs): . as $x | xs | has($x);
        def combinations: if length == 0 then [] else .[0][] as $x | (.[1:] | combinations) as $rest | [$x] + $rest end;
        def combinations(n): . as $dot | [range(n) | $dot] | combinations;
        def walk(f): def w: if type == "object" then map_values(w) elif type == "array" then map(w) else . end | f; w;
        def transpose: (map(length) | max // 0) as $max | [range(0; $max) as $i | [.[][$i]]];
        def splits($re; flags): split($re; flags) | .[];
        def splits($re): split($re; null) | .[];
        def ascii_downcase2: .;
        def IN(s): any(s == .; .);
        def IN(src; s): any(src | s == .; .);
        def ascii_normalize: .;
        .
        JQ;
}

<?php

declare(strict_types=1);

namespace App\Jq\Runtime;

use App\Jq\Parser\Ast\Node;
use Generator;

/**
 * Native builtins implemented in PHP. Higher-level builtins (map, select,
 * to_entries, ...) live in {@see Prelude} and are written in jq itself.
 */
final class Builtins
{
    /**
     * @param  list<Node>  $args
     */
    public function dispatch(Interpreter $i, string $name, array $args, mixed $input, Environment $env): ?Generator
    {
        $arity = count($args);
        $key = "$name/$arity";

        return match ($key) {
            'empty/0' => $this->none(),
            'error/0' => $this->errorFilter($input),
            'error/1' => $this->one($this->raise($i->first($args[0], $input, $env))),
            'length/0' => $this->one($this->length($input)),
            'utf8bytelength/0' => $this->one($this->utf8ByteLength($input)),
            'keys/0' => $this->one($this->keys($input, true)),
            'keys_unsorted/0' => $this->one($this->keys($input, false)),
            'has/1' => $this->one($this->has($input, $i->first($args[0], $input, $env))),
            'contains/1' => $this->one($this->contains($input, $i->first($args[0], $input, $env))),
            'type/0' => $this->one(Values::typeName($input)),
            'isnan/0' => $this->one(is_float($input) && is_nan($input)),
            'isinfinite/0' => $this->one(is_float($input) && is_infinite($input)),
            'isnormal/0' => $this->one(is_int($input) || (is_float($input) && is_finite($input) && $input != 0.0)),
            'infinite/0' => $this->one(INF),
            'nan/0' => $this->one(NAN),
            'tostring/0' => $this->one($this->toString($input)),
            'tonumber/0' => $this->one($this->toNumber($input)),
            'tojson/0' => $this->one(Values::encode($input, false)),
            'fromjson/0' => $this->one($this->fromJson($input)),
            'ascii_downcase/0' => $this->one(mb_strtolower($this->asString($input, 'ascii_downcase'))),
            'ascii_upcase/0' => $this->one(mb_strtoupper($this->asString($input, 'ascii_upcase'))),
            'explode/0' => $this->one($this->explode($input)),
            'implode/0' => $this->one($this->implode($input)),
            'ltrimstr/1' => $this->one($this->ltrimstr($input, $i->first($args[0], $input, $env))),
            'rtrimstr/1' => $this->one($this->rtrimstr($input, $i->first($args[0], $input, $env))),
            'startswith/1' => $this->one($this->startsWith($input, $i->first($args[0], $input, $env))),
            'endswith/1' => $this->one($this->endsWith($input, $i->first($args[0], $input, $env))),
            'ascii/0' => $this->one(mb_chr((int) $input)),
            'split/1' => $this->one($this->split($input, $i->first($args[0], $input, $env))),
            'split/2' => $this->splitRegex($input, $i->first($args[0], $input, $env), $i->first($args[1], $input, $env)),
            'ltrimstr/0' => null,
            'reverse/0' => $this->one($this->reverse($input)),
            'sort/0' => $this->one($this->sort($input)),
            'sort_by/1' => $this->one($this->sortBy($i, $args[0], $input, $env)),
            'group_by/1' => $this->one($this->groupBy($i, $args[0], $input, $env)),
            'unique/0' => $this->one($this->unique($input)),
            'unique_by/1' => $this->one($this->uniqueBy($i, $args[0], $input, $env)),
            'min/0' => $this->one($this->minMax($input, true)),
            'max/0' => $this->one($this->minMax($input, false)),
            'min_by/1' => $this->one($this->minMaxBy($i, $args[0], $input, $env, true)),
            'max_by/1' => $this->one($this->minMaxBy($i, $args[0], $input, $env, false)),
            'floor/0' => $this->one($this->mathUnary($input, 'floor')),
            'ceil/0' => $this->one($this->mathUnary($input, 'ceil')),
            'round/0' => $this->one($this->mathUnary($input, 'round')),
            'fabs/0' => $this->one(abs($this->asNumber($input, 'fabs'))),
            'sqrt/0' => $this->one(sqrt($this->asNumber($input, 'sqrt'))),
            'exp/0' => $this->one(exp($this->asNumber($input, 'exp'))),
            'exp2/0' => $this->one(2 ** $this->asNumber($input, 'exp2')),
            'exp10/0' => $this->one(10 ** $this->asNumber($input, 'exp10')),
            'log/0' => $this->one(log($this->asNumber($input, 'log'))),
            'log2/0' => $this->one(log($this->asNumber($input, 'log2'), 2)),
            'log10/0' => $this->one(log10($this->asNumber($input, 'log10'))),
            'pow/2' => $this->one($this->asNumber($i->first($args[0], $input, $env), 'pow') ** $this->asNumber($i->first($args[1], $input, $env), 'pow')),
            'getpath/1' => $this->one(Paths::get($input, $this->asPath($i->first($args[0], $input, $env)))),
            'setpath/2' => $this->one(Paths::set($input, $this->asPath($i->first($args[0], $input, $env)), $i->first($args[1], $input, $env))),
            'delpaths/1' => $this->one($this->delpaths($input, $i->first($args[0], $input, $env))),
            'path/1' => $this->pathFilter($i, $args[0], $input, $env),
            'range/1' => $this->range($i, $args, $input, $env),
            'range/2' => $this->range($i, $args, $input, $env),
            'range/3' => $this->range($i, $args, $input, $env),
            'test/1', 'test/2' => $this->one($this->regexTest($input, $i, $args, $env)),
            'match/1', 'match/2' => $this->regexMatch($input, $i, $args, $env),
            'capture/1', 'capture/2' => $this->regexCapture($input, $i, $args, $env),
            'scan/1', 'scan/2' => $this->regexScan($input, $i, $args, $env),
            'sub/2', 'sub/3' => $this->regexSub($input, $i, $args, $env, false),
            'gsub/2', 'gsub/3' => $this->regexSub($input, $i, $args, $env, true),
            'now/0' => $this->one($this->nowSeconds()),
            'gmtime/0' => $this->one($this->gmtime($input)),
            'mktime/0' => $this->one($this->mktime($input)),
            'todate/0', 'todateiso8601/0' => $this->one($this->todate($input)),
            'fromdate/0', 'fromdateiso8601/0' => $this->one($this->fromdate($input)),
            'strftime/1' => $this->one($this->strftime($input, $i->first($args[0], $input, $env))),
            'env/0' => $this->one($this->envObject()),
            'input/0' => $this->input(),
            'inputs/0' => $this->inputs(),
            'input_line_number/0' => $this->one(0),
            'debug/0' => $this->debug($input, null),
            'debug/1' => $this->debug($input, $i->first($args[0], $input, $env)),
            'stderr/0' => $this->stderr($input),
            'builtins/0' => $this->one([]),
            'indices/1' => $this->one($this->indices($input, $i->first($args[0], $input, $env))),
            default => null,
        };
    }

    // ----- generator helpers ----------------------------------------------

    private function one(mixed $value): Generator
    {
        yield $value;
    }

    private function none(): Generator
    {
        return;
        yield;
    }

    // ----- simple builtins -------------------------------------------------

    private function length(mixed $v): int|float
    {
        return match (true) {
            $v === null => 0,
            is_bool($v) => throw JqException::of(Values::typeName($v).' ('.Values::encode($v, false).') has no length'),
            is_int($v), is_float($v) => abs($v),
            is_string($v) => mb_strlen($v),
            is_array($v) => count($v),
            $v instanceof JsonObject => $v->count(),
            default => 0,
        };
    }

    private function utf8ByteLength(mixed $v): int
    {
        if (! is_string($v)) {
            throw JqException::of(Values::typeName($v).' only strings have UTF-8 byte length');
        }

        return strlen($v);
    }

    /**
     * @return list<string|int>
     */
    private function keys(mixed $v, bool $sorted): array
    {
        if ($v instanceof JsonObject) {
            $keys = $v->keys();
            if ($sorted) {
                sort($keys);
            }

            return $keys;
        }
        if (is_array($v)) {
            return array_keys($v);
        }
        throw JqException::of(Values::typeName($v).' ('.Values::encode($v, false).') has no keys');
    }

    private function has(mixed $v, mixed $key): bool
    {
        if ($v instanceof JsonObject && is_string($key)) {
            return $v->has($key);
        }
        if (is_array($v) && (is_int($key) || is_float($key))) {
            $idx = (int) $key;

            return $idx >= 0 && $idx < count($v);
        }
        throw JqException::of('Cannot check whether '.Values::typeName($v).' has a '.Values::typeName($key).' key');
    }

    private function contains(mixed $a, mixed $b): bool
    {
        if ($a instanceof JsonObject && $b instanceof JsonObject) {
            foreach ($b->props as $k => $bv) {
                if (! $a->has((string) $k) || ! $this->contains($a->get((string) $k), $bv)) {
                    return false;
                }
            }

            return true;
        }
        if (is_array($a) && is_array($b)) {
            foreach ($b as $bv) {
                $found = false;
                foreach ($a as $av) {
                    if ($this->contains($av, $bv)) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    return false;
                }
            }

            return true;
        }
        if (is_string($a) && is_string($b)) {
            return $b === '' || str_contains($a, $b);
        }

        return Values::equals($a, $b);
    }

    private function toString(mixed $v): string
    {
        return is_string($v) ? $v : Values::encode($v, false);
    }

    private function toNumber(mixed $v): int|float
    {
        if (is_int($v) || is_float($v)) {
            return $v;
        }
        if (is_string($v) && is_numeric(trim($v))) {
            $n = trim($v) + 0;

            return $n;
        }
        throw JqException::of("Cannot parse '".(is_string($v) ? $v : Values::typeName($v))."' as number");
    }

    private function fromJson(mixed $v): mixed
    {
        if (! is_string($v)) {
            throw JqException::of(Values::typeName($v).' cannot be parsed as JSON');
        }
        if (! JsonDecoder::tryDecode($v, $decoded)) {
            throw JqException::of('Invalid JSON text passed to fromjson');
        }

        return $decoded;
    }

    /**
     * @return list<int>
     */
    private function explode(mixed $v): array
    {
        $s = $this->asString($v, 'explode');
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(fn ($c) => mb_ord($c), $chars);
    }

    private function implode(mixed $v): string
    {
        if (! is_array($v)) {
            throw JqException::of('Cannot implode '.Values::typeName($v));
        }
        $out = '';
        foreach ($v as $code) {
            if (! is_int($code) && ! is_float($code)) {
                throw JqException::of('Input to implode must be an array of codepoints');
            }
            $out .= mb_chr((int) $code);
        }

        return $out;
    }

    private function ltrimstr(mixed $v, mixed $prefix): mixed
    {
        if (is_string($v) && is_string($prefix) && str_starts_with($v, $prefix)) {
            return substr($v, strlen($prefix));
        }

        return $v;
    }

    private function rtrimstr(mixed $v, mixed $suffix): mixed
    {
        if (is_string($v) && is_string($suffix) && $suffix !== '' && str_ends_with($v, $suffix)) {
            return substr($v, 0, strlen($v) - strlen($suffix));
        }

        return $v;
    }

    private function startsWith(mixed $v, mixed $prefix): bool
    {
        return str_starts_with($this->asString($v, 'startswith'), $this->asString($prefix, 'startswith'));
    }

    private function endsWith(mixed $v, mixed $suffix): bool
    {
        return str_ends_with($this->asString($v, 'endswith'), $this->asString($suffix, 'endswith'));
    }

    /**
     * @return list<string>
     */
    private function split(mixed $v, mixed $sep): array
    {
        $s = $this->asString($v, 'split');
        $d = $this->asString($sep, 'split');
        if ($d === '') {
            return preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return explode($d, $s);
    }

    private function splitRegex(mixed $v, mixed $re, mixed $flags): Generator
    {
        $s = $this->asString($v, 'split');
        $pcre = $this->buildPcre($this->asString($re, 'split'), is_string($flags) ? $flags : '');
        $parts = preg_split($pcre, $s);
        yield $parts === false ? [] : array_values($parts);
    }

    private function reverse(mixed $v): mixed
    {
        if (is_array($v)) {
            return array_reverse($v);
        }
        if (is_string($v)) {
            $chars = preg_split('//u', $v, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            return implode('', array_reverse($chars));
        }
        if ($v === null) {
            return [];
        }
        throw JqException::of('Cannot reverse '.Values::typeName($v));
    }

    private function sort(mixed $v): array
    {
        if (! is_array($v)) {
            throw JqException::of(Values::typeName($v).' ('.Values::encode($v, false).') cannot be sorted, as it is not an array');
        }
        $copy = $v;
        usort($copy, fn ($a, $b) => Values::compare($a, $b));

        return $copy;
    }

    private function sortBy(Interpreter $i, Node $f, mixed $v, Environment $env): array
    {
        $arr = $this->asArrayInput($v, 'sort_by');
        $keyed = array_map(fn ($item) => [iterator_to_array($i->eval($f, $item, $env), false), $item], $arr);
        usort($keyed, fn ($a, $b) => Values::compare($a[0], $b[0]));

        return array_map(fn ($pair) => $pair[1], $keyed);
    }

    private function groupBy(Interpreter $i, Node $f, mixed $v, Environment $env): array
    {
        $arr = $this->asArrayInput($v, 'group_by');
        $keyed = array_map(fn ($item) => [$i->first($f, $item, $env), $item], $arr);
        usort($keyed, fn ($a, $b) => Values::compare($a[0], $b[0]));
        $groups = [];
        $current = null;
        $hasCurrent = false;
        foreach ($keyed as [$k, $item]) {
            if (! $hasCurrent || ! Values::equals($k, $current)) {
                $groups[] = [];
                $current = $k;
                $hasCurrent = true;
            }
            $groups[count($groups) - 1][] = $item;
        }

        return $groups;
    }

    private function unique(mixed $v): array
    {
        $sorted = $this->sort($v);
        $out = [];
        foreach ($sorted as $item) {
            if ($out === [] || ! Values::equals($out[count($out) - 1], $item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    private function uniqueBy(Interpreter $i, Node $f, mixed $v, Environment $env): array
    {
        $arr = $this->asArrayInput($v, 'unique_by');
        $keyed = array_map(fn ($item) => [$i->first($f, $item, $env), $item], $arr);
        usort($keyed, fn ($a, $b) => Values::compare($a[0], $b[0]));
        $out = [];
        $lastKey = null;
        $has = false;
        foreach ($keyed as [$k, $item]) {
            if (! $has || ! Values::equals($lastKey, $k)) {
                $out[] = $item;
                $lastKey = $k;
                $has = true;
            }
        }

        return $out;
    }

    private function minMax(mixed $v, bool $min): mixed
    {
        $arr = $this->asArrayInput($v, $min ? 'min' : 'max');
        if ($arr === []) {
            return null;
        }
        $best = $arr[0];
        foreach ($arr as $item) {
            $c = Values::compare($item, $best);
            if (($min && $c < 0) || (! $min && $c >= 0)) {
                $best = $item;
            }
        }

        return $best;
    }

    private function minMaxBy(Interpreter $i, Node $f, mixed $v, Environment $env, bool $min): mixed
    {
        $arr = $this->asArrayInput($v, $min ? 'min_by' : 'max_by');
        if ($arr === []) {
            return null;
        }
        $best = $arr[0];
        $bestKey = $i->first($f, $best, $env);
        foreach ($arr as $item) {
            $k = $i->first($f, $item, $env);
            $c = Values::compare($k, $bestKey);
            if (($min && $c < 0) || (! $min && $c >= 0)) {
                $best = $item;
                $bestKey = $k;
            }
        }

        return $best;
    }

    private function mathUnary(mixed $v, string $fn): int|float
    {
        $n = $this->asNumber($v, $fn);

        return match ($fn) {
            'floor' => (int) floor($n),
            'ceil' => (int) ceil($n),
            'round' => (int) round($n),
            default => $n,
        };
    }

    /**
     * @return list<mixed>
     */
    private function asPath(mixed $p): array
    {
        if (! is_array($p)) {
            throw JqException::of('Path must be specified as an array');
        }

        return array_values($p);
    }

    private function delpaths(mixed $v, mixed $paths): mixed
    {
        if (! is_array($paths)) {
            throw JqException::of('delpaths needs an array of paths');
        }
        $list = array_map(fn ($p) => $this->asPath($p), $paths);

        return Paths::delete($v, $list);
    }

    private function pathFilter(Interpreter $i, Node $f, mixed $input, Environment $env): Generator
    {
        foreach ($i->evalPaths($f, $input, $env) as $path) {
            yield array_values($path);
        }
    }

    private function range(Interpreter $i, array $args, mixed $input, Environment $env): Generator
    {
        $n = count($args);
        if ($n === 1) {
            foreach ($i->eval($args[0], $input, $env) as $to) {
                for ($x = 0; $x < $to; $x++) {
                    yield $x;
                }
            }

            return;
        }
        if ($n === 2) {
            foreach ($i->eval($args[0], $input, $env) as $from) {
                foreach ($i->eval($args[1], $input, $env) as $to) {
                    for ($x = $from; $x < $to; $x++) {
                        yield $x;
                    }
                }
            }

            return;
        }
        foreach ($i->eval($args[0], $input, $env) as $from) {
            foreach ($i->eval($args[1], $input, $env) as $to) {
                foreach ($i->eval($args[2], $input, $env) as $by) {
                    if ($by == 0) {
                        continue;
                    }
                    if ($by > 0) {
                        for ($x = $from; $x < $to; $x += $by) {
                            yield $x;
                        }
                    } else {
                        for ($x = $from; $x > $to; $x += $by) {
                            yield $x;
                        }
                    }
                }
            }
        }
    }

    // ----- errors ----------------------------------------------------------

    private function errorFilter(mixed $input): Generator
    {
        throw new JqException($input);
        yield;
    }

    private function raise(mixed $value): mixed
    {
        throw new JqException($value);
    }

    // ----- regex -----------------------------------------------------------

    private function buildPcre(string $re, string $flags): string
    {
        $modifiers = 'u';
        if (str_contains($flags, 'i')) {
            $modifiers .= 'i';
        }
        if (str_contains($flags, 'x')) {
            $modifiers .= 'x';
        }
        if (str_contains($flags, 's')) {
            $modifiers .= 's';
        }
        if (str_contains($flags, 'm')) {
            $modifiers .= 'm';
        }
        $escaped = str_replace('/', '\\/', $re);

        return '/'.$escaped.'/'.$modifiers;
    }

    private function regexTest(mixed $input, Interpreter $i, array $args, Environment $env): bool
    {
        [$re, $flags] = $this->regexArgs($i, $args, $input, $env);
        $s = $this->asString($input, 'test');

        return (bool) preg_match($this->buildPcre($re, $flags), $s);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function regexArgs(Interpreter $i, array $args, mixed $input, Environment $env): array
    {
        $first = $i->first($args[0], $input, $env);
        if (is_array($first)) {
            $re = (string) ($first[0] ?? '');
            $flags = (string) ($first[1] ?? '');

            return [$re, $flags];
        }
        $re = $this->asString($first, 'regex');
        $flags = isset($args[1]) ? (string) $i->first($args[1], $input, $env) : '';

        return [$re, $flags];
    }

    private function regexMatch(mixed $input, Interpreter $i, array $args, Environment $env): Generator
    {
        [$re, $flags] = $this->regexArgs($i, $args, $input, $env);
        $s = $this->asString($input, 'match');
        $global = str_contains($flags, 'g');
        $pcre = $this->buildPcre($re, $flags);

        if ($global) {
            preg_match_all($pcre, $s, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($matches as $m) {
                yield $this->buildMatchObject($m, $s);
            }

            return;
        }
        if (preg_match($pcre, $s, $m, PREG_OFFSET_CAPTURE)) {
            yield $this->buildMatchObject($m, $s);
        }
    }

    private function buildMatchObject(array $m, string $subject): JsonObject
    {
        $whole = $m[0];
        $obj = new JsonObject;
        $obj->props['offset'] = $this->byteToCodepoint($subject, $whole[1]);
        $obj->props['length'] = mb_strlen($whole[0]);
        $obj->props['string'] = $whole[0];
        $captures = [];
        $count = count($m);
        for ($idx = 1; $idx < $count; $idx++) {
            $cap = $m[$idx];
            $capObj = new JsonObject;
            if ($cap[1] === -1) {
                $capObj->props['offset'] = -1;
                $capObj->props['length'] = 0;
                $capObj->props['string'] = null;
            } else {
                $capObj->props['offset'] = $this->byteToCodepoint($subject, $cap[1]);
                $capObj->props['length'] = mb_strlen($cap[0]);
                $capObj->props['string'] = $cap[0];
            }
            $capObj->props['name'] = null;
            $captures[] = $capObj;
        }
        $obj->props['captures'] = $captures;

        return $obj;
    }

    private function byteToCodepoint(string $subject, int $byteOffset): int
    {
        if ($byteOffset <= 0) {
            return 0;
        }

        return mb_strlen(substr($subject, 0, $byteOffset));
    }

    private function regexCapture(mixed $input, Interpreter $i, array $args, Environment $env): Generator
    {
        [$re, $flags] = $this->regexArgs($i, $args, $input, $env);
        $s = $this->asString($input, 'capture');
        $pcre = $this->buildPcre($re, $flags);
        if (preg_match($pcre, $s, $m)) {
            $obj = new JsonObject;
            foreach ($m as $k => $v) {
                if (is_string($k)) {
                    $obj->props[$k] = $v === '' && ! isset($m[$k]) ? null : $v;
                }
            }
            yield $obj;
        }
    }

    private function regexScan(mixed $input, Interpreter $i, array $args, Environment $env): Generator
    {
        [$re, $flags] = $this->regexArgs($i, $args, $input, $env);
        $s = $this->asString($input, 'scan');
        $pcre = $this->buildPcre($re, $flags.(str_contains($flags, 'g') ? '' : 'g'));
        preg_match_all($this->buildPcre($re, $flags), $s, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            if (count($m) > 1) {
                yield array_slice($m, 1);
            } else {
                yield $m[0];
            }
        }
    }

    private function regexSub(mixed $input, Interpreter $i, array $args, Environment $env, bool $global): Generator
    {
        $re = $this->asString($i->first($args[0], $input, $env), 'sub');
        $replNode = $args[1];
        $flags = isset($args[2]) ? (string) $i->first($args[2], $input, $env) : '';
        $s = $this->asString($input, 'sub');
        $pcre = $this->buildPcre($re, $flags);

        $limit = ($global || str_contains($flags, 'g')) ? -1 : 1;
        $result = preg_replace_callback($pcre, function ($m) use ($i, $replNode, $env) {
            $obj = new JsonObject;
            foreach ($m as $k => $v) {
                if (is_string($k)) {
                    $obj->props[$k] = $v;
                }
            }

            return (string) $i->first($replNode, $obj, $env);
        }, $s, $limit);

        yield $result ?? $s;
    }

    // ----- dates -----------------------------------------------------------

    private function nowSeconds(): float
    {
        return microtime(true);
    }

    private function gmtime(mixed $v): array
    {
        $t = (int) $this->asNumber($v, 'gmtime');
        $g = gmdate('s,i,H,d,m,Y,w,z', $t);
        [$s, $min, $h, $mday, $mon, $year, $wday, $yday] = array_map('intval', explode(',', $g));

        return [$s, $min, $h, $mday, $mon - 1, $year - 1900, $wday, $yday];
    }

    private function mktime(mixed $v): int
    {
        if (! is_array($v) || count($v) < 6) {
            throw JqException::of('mktime requires array of 6 elements');
        }
        [$s, $min, $h, $mday, $mon, $year] = $v;

        return gmmktime((int) $h, (int) $min, (int) $s, (int) $mon + 1, (int) $mday, (int) $year + 1900);
    }

    private function todate(mixed $v): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', (int) $this->asNumber($v, 'todate'));
    }

    private function fromdate(mixed $v): int
    {
        $s = $this->asString($v, 'fromdate');
        $t = strtotime($s);
        if ($t === false) {
            throw JqException::of("date \"$s\" does not match format");
        }

        return $t;
    }

    private function strftime(mixed $v, mixed $fmt): string
    {
        $t = is_array($v) ? $this->mktime($v) : (int) $this->asNumber($v, 'strftime');
        $format = $this->asString($fmt, 'strftime');

        return $this->doStrftime($format, $t);
    }

    private function doStrftime(string $format, int $timestamp): string
    {
        $map = [
            '%Y' => gmdate('Y', $timestamp), '%m' => gmdate('m', $timestamp),
            '%d' => gmdate('d', $timestamp), '%H' => gmdate('H', $timestamp),
            '%M' => gmdate('i', $timestamp), '%S' => gmdate('s', $timestamp),
            '%A' => gmdate('l', $timestamp), '%a' => gmdate('D', $timestamp),
            '%B' => gmdate('F', $timestamp), '%b' => gmdate('M', $timestamp),
            '%j' => gmdate('z', $timestamp), '%Z' => 'UTC', '%%' => '%',
        ];

        return strtr($format, $map);
    }

    private function envObject(): JsonObject
    {
        $obj = new JsonObject;
        foreach ($_ENV as $k => $v) {
            $obj->props[(string) $k] = (string) $v;
        }
        if ($obj->count() === 0) {
            foreach (getenv() as $k => $v) {
                $obj->props[(string) $k] = (string) $v;
            }
        }

        return $obj;
    }

    // ----- input / debug ---------------------------------------------------

    public ?\Closure $inputPuller = null;

    private function input(): Generator
    {
        if ($this->inputPuller === null) {
            throw JqException::of('No more inputs');
        }
        [$has, $value] = ($this->inputPuller)();
        if (! $has) {
            throw JqException::of('No more inputs');
        }
        yield $value;
    }

    private function inputs(): Generator
    {
        if ($this->inputPuller === null) {
            return;
        }
        while (true) {
            [$has, $value] = ($this->inputPuller)();
            if (! $has) {
                return;
            }
            yield $value;
        }
    }

    private function debug(mixed $input, mixed $message): Generator
    {
        $payload = $message === null ? ['debug', $input] : ['debug', $message];
        fwrite(STDERR, Values::encode($payload, false)."\n");
        yield $input;
    }

    private function stderr(mixed $input): Generator
    {
        fwrite(STDERR, is_string($input) ? $input : Values::encode($input, false));
        yield $input;
    }

    /**
     * @return list<int>
     */
    public function indices(mixed $haystack, mixed $needle): array
    {
        $out = [];
        if (is_string($haystack) && is_string($needle) && $needle !== '') {
            $offset = 0;
            while (($pos = mb_strpos($haystack, $needle, $offset)) !== false) {
                $out[] = $pos;
                $offset = $pos + 1;
            }

            return $out;
        }
        if (is_array($haystack)) {
            if (is_array($needle) && $needle !== []) {
                $n = count($needle);
                for ($idx = 0; $idx + $n <= count($haystack); $idx++) {
                    $match = true;
                    for ($j = 0; $j < $n; $j++) {
                        if (! Values::equals($haystack[$idx + $j], $needle[$j])) {
                            $match = false;
                            break;
                        }
                    }
                    if ($match) {
                        $out[] = $idx;
                    }
                }

                return $out;
            }
            foreach ($haystack as $idx => $item) {
                if (Values::equals($item, $needle)) {
                    $out[] = $idx;
                }
            }
        }

        return $out;
    }

    // ----- @formats --------------------------------------------------------

    public function applyFormat(string $name, mixed $value): string
    {
        return match ($name) {
            'text' => $this->toString($value),
            'json' => Values::encode($value, false),
            'base64' => base64_encode($this->toString($value)),
            'base64d' => $this->base64decode($this->toString($value)),
            'uri' => rawurlencode($this->toString($value)),
            'csv' => $this->csvRow($value, ','),
            'tsv' => $this->csvRow($value, "\t"),
            'html' => htmlspecialchars($this->toString($value), ENT_QUOTES | ENT_HTML401, 'UTF-8'),
            'sh' => $this->shFormat($value),
            default => throw JqException::of("$name is not a valid format"),
        };
    }

    private function base64decode(string $v): string
    {
        $decoded = base64_decode($v, false);

        return $decoded === false ? '' : $decoded;
    }

    private function csvRow(mixed $value, string $sep): string
    {
        if (! is_array($value)) {
            throw JqException::of('@csv/@tsv input must be an array');
        }
        $cells = [];
        foreach ($value as $cell) {
            $cells[] = $this->csvCell($cell, $sep);
        }

        return implode($sep, $cells);
    }

    private function csvCell(mixed $cell, string $sep): string
    {
        if ($cell === null) {
            return '';
        }
        if (is_bool($cell)) {
            return $cell ? 'true' : 'false';
        }
        if (is_int($cell) || is_float($cell)) {
            return Values::encode($cell, false);
        }
        if (is_string($cell)) {
            if ($sep === ',') {
                return '"'.str_replace('"', '""', $cell).'"';
            }

            return str_replace(['\\', "\t", "\n", "\r"], ['\\\\', '\\t', '\\n', '\\r'], $cell);
        }
        throw JqException::of('Not valid in a csv row');
    }

    private function shFormat(mixed $value): string
    {
        if (is_array($value)) {
            return implode(' ', array_map(fn ($v) => $this->shQuote($v), $value));
        }

        return $this->shQuote($value);
    }

    private function shQuote(mixed $v): string
    {
        if (is_string($v)) {
            return "'".str_replace("'", "'\\''", $v)."'";
        }
        if (is_int($v) || is_float($v) || is_bool($v) || $v === null) {
            return Values::encode($v, false);
        }
        throw JqException::of('Not valid in a shell command');
    }

    // ----- coercion helpers ------------------------------------------------

    private function asString(mixed $v, string $fn): string
    {
        if (! is_string($v)) {
            throw JqException::of(Values::typeName($v).' ('.Values::encode($v, false).') cannot be used with '.$fn);
        }

        return $v;
    }

    private function asNumber(mixed $v, string $fn): int|float
    {
        if (! is_int($v) && ! is_float($v)) {
            throw JqException::of(Values::typeName($v).' ('.Values::encode($v, false).') number required for '.$fn);
        }

        return $v;
    }

    /**
     * @return list<mixed>
     */
    private function asArrayInput(mixed $v, string $fn): array
    {
        if (! is_array($v)) {
            throw JqException::of(Values::typeName($v).' ('.Values::encode($v, false).') cannot be used with '.$fn);
        }

        return $v;
    }
}

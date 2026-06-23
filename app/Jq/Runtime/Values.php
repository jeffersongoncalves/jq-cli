<?php

declare(strict_types=1);

namespace App\Jq\Runtime;

/**
 * Helpers for jq value semantics: type names, the total ordering between types,
 * equality, truthiness, and JSON encoding that matches jq's output rules.
 */
final class Values
{
    public static function typeName(mixed $v): string
    {
        return match (true) {
            $v === null => 'null',
            is_bool($v) => 'boolean',
            is_int($v), is_float($v) => 'number',
            is_string($v) => 'string',
            $v instanceof JsonObject => 'object',
            is_array($v) => 'array',
            default => 'unknown',
        };
    }

    public static function isObject(mixed $v): bool
    {
        return $v instanceof JsonObject;
    }

    public static function isArray(mixed $v): bool
    {
        return is_array($v);
    }

    public static function truthy(mixed $v): bool
    {
        return $v !== null && $v !== false;
    }

    /** Rank used for jq's total ordering between distinct types. */
    private static function rank(mixed $v): int
    {
        return match (true) {
            $v === null => 0,
            $v === false => 1,
            $v === true => 2,
            is_int($v), is_float($v) => 3,
            is_string($v) => 4,
            is_array($v) => 5,
            $v instanceof JsonObject => 6,
            default => 7,
        };
    }

    /**
     * Total ordering: null < false < true < numbers < strings < arrays < objects.
     * Returns -1, 0 or 1.
     */
    public static function compare(mixed $a, mixed $b): int
    {
        $ra = self::rank($a);
        $rb = self::rank($b);
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        return match ($ra) {
            3 => self::numCompare($a, $b),
            4 => strcmp((string) $a, (string) $b),
            5 => self::compareArrays($a, $b),
            6 => self::compareObjects($a, $b),
            default => 0, // null/false/true are equal within their rank
        };
    }

    private static function numCompare(int|float $a, int|float $b): int
    {
        return $a <=> $b;
    }

    /**
     * @param  list<mixed>  $a
     * @param  list<mixed>  $b
     */
    private static function compareArrays(array $a, array $b): int
    {
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $c = self::compare($a[$i], $b[$i]);
            if ($c !== 0) {
                return $c;
            }
        }

        return count($a) <=> count($b);
    }

    private static function compareObjects(JsonObject $a, JsonObject $b): int
    {
        $ka = $a->keys();
        $kb = $b->keys();
        sort($ka);
        sort($kb);

        // compare sorted key lists first
        $c = self::compareArrays($ka, $kb);
        if ($c !== 0) {
            return $c;
        }
        // then values in sorted-key order
        foreach ($ka as $k) {
            $c = self::compare($a->get($k), $b->get($k));
            if ($c !== 0) {
                return $c;
            }
        }

        return 0;
    }

    public static function equals(mixed $a, mixed $b): bool
    {
        return self::compare($a, $b) === 0;
    }

    // ----- encoding --------------------------------------------------------

    /**
     * @param  array{indent?: int|null, tab?: bool, sortKeys?: bool, ascii?: bool}  $opts
     */
    public static function encode(mixed $v, bool $pretty = true, array $opts = []): string
    {
        $indent = $opts['indent'] ?? ($pretty ? 2 : null);
        $tab = $opts['tab'] ?? false;
        $sortKeys = $opts['sortKeys'] ?? false;
        $ascii = $opts['ascii'] ?? false;

        return self::encodeValue($v, $indent, $tab, $sortKeys, $ascii, 0);
    }

    private static function encodeValue(mixed $v, ?int $indent, bool $tab, bool $sortKeys, bool $ascii, int $depth): string
    {
        if ($v === null) {
            return 'null';
        }
        if ($v === true) {
            return 'true';
        }
        if ($v === false) {
            return 'false';
        }
        if (is_int($v)) {
            return (string) $v;
        }
        if (is_float($v)) {
            return self::encodeFloat($v);
        }
        if (is_string($v)) {
            return self::encodeString($v, $ascii);
        }
        if (is_array($v)) {
            return self::encodeArray($v, $indent, $tab, $sortKeys, $ascii, $depth);
        }
        if ($v instanceof JsonObject) {
            return self::encodeObject($v, $indent, $tab, $sortKeys, $ascii, $depth);
        }

        return 'null';
    }

    private static function encodeFloat(float $v): string
    {
        if (is_nan($v)) {
            return 'null';
        }
        if (is_infinite($v)) {
            return $v > 0 ? '1.7976931348623157e+308' : '-1.7976931348623157e+308';
        }
        if ($v === floor($v) && abs($v) < 1e15) {
            return (string) (int) $v;
        }

        $s = json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        return $s === false ? '0' : $s;
    }

    private static function encodeString(string $v, bool $ascii): string
    {
        $out = '"';
        $len = strlen($v);

        if ($ascii) {
            // iterate by unicode codepoint
            $chars = preg_split('//u', $v, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($chars as $ch) {
                $out .= self::escapeChar($ch, true);
            }

            return $out.'"';
        }

        for ($i = 0; $i < $len; $i++) {
            $out .= self::escapeByte($v[$i]);
        }

        return $out.'"';
    }

    private static function escapeByte(string $c): string
    {
        return match ($c) {
            '"' => '\\"',
            '\\' => '\\\\',
            "\x08" => '\\b',
            "\x0C" => '\\f',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
            default => ord($c) < 0x20 ? sprintf('\\u%04x', ord($c)) : $c,
        };
    }

    private static function escapeChar(string $ch, bool $ascii): string
    {
        // multi-byte char
        if (strlen($ch) === 1) {
            $byte = self::escapeByte($ch);
            if ($byte !== $ch) {
                return $byte;
            }
            $code = ord($ch);
            if ($ascii && $code > 0x7F) {
                return sprintf('\\u%04x', $code);
            }

            return $ch;
        }

        if (! $ascii) {
            return $ch;
        }

        $code = self::codepointOf($ch);
        if ($code <= 0xFFFF) {
            return sprintf('\\u%04x', $code);
        }
        $code -= 0x10000;
        $high = 0xD800 + ($code >> 10);
        $low = 0xDC00 + ($code & 0x3FF);

        return sprintf('\\u%04x\\u%04x', $high, $low);
    }

    private static function codepointOf(string $ch): int
    {
        $bytes = unpack('N', mb_convert_encoding($ch, 'UTF-32BE', 'UTF-8'));

        return $bytes ? (int) $bytes[1] : 0;
    }

    /**
     * @param  list<mixed>  $v
     */
    private static function encodeArray(array $v, ?int $indent, bool $tab, bool $sortKeys, bool $ascii, int $depth): string
    {
        if ($v === []) {
            return '[]';
        }
        $parts = [];
        foreach ($v as $item) {
            $parts[] = self::encodeValue($item, $indent, $tab, $sortKeys, $ascii, $depth + 1);
        }

        return self::wrap('[', ']', $parts, $indent, $tab, $depth);
    }

    private static function encodeObject(JsonObject $v, ?int $indent, bool $tab, bool $sortKeys, bool $ascii, int $depth): string
    {
        if ($v->count() === 0) {
            return '{}';
        }
        $keys = $v->keys();
        if ($sortKeys) {
            sort($keys);
        }
        $colon = $indent === null ? ':' : ': ';
        $parts = [];
        foreach ($keys as $k) {
            $parts[] = self::encodeString($k, $ascii).$colon.self::encodeValue($v->get($k), $indent, $tab, $sortKeys, $ascii, $depth + 1);
        }

        return self::wrap('{', '}', $parts, $indent, $tab, $depth);
    }

    /**
     * @param  list<string>  $parts
     */
    private static function wrap(string $open, string $close, array $parts, ?int $indent, bool $tab, int $depth): string
    {
        if ($indent === null) {
            return $open.implode(',', $parts).$close;
        }
        $unit = $tab ? "\t" : str_repeat(' ', $indent);
        $pad = str_repeat($unit, $depth + 1);
        $padEnd = str_repeat($unit, $depth);

        return $open."\n".$pad.implode(",\n".$pad, $parts)."\n".$padEnd.$close;
    }
}

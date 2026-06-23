<?php

declare(strict_types=1);

namespace App\Jq\Runtime;

/**
 * Arithmetic operators with jq's overloaded semantics for +, -, *, /, %.
 */
final class Operators
{
    public static function apply(string $op, mixed $a, mixed $b): mixed
    {
        return match ($op) {
            '+' => self::add($a, $b),
            '-' => self::subtract($a, $b),
            '*' => self::multiply($a, $b),
            '/' => self::divide($a, $b),
            '%' => self::modulo($a, $b),
            default => throw JqException::of("unknown operator $op"),
        };
    }

    private static function add(mixed $a, mixed $b): mixed
    {
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }
        if (self::isNum($a) && self::isNum($b)) {
            return $a + $b;
        }
        if (is_string($a) && is_string($b)) {
            return $a.$b;
        }
        if (is_array($a) && is_array($b)) {
            return array_merge($a, $b);
        }
        if ($a instanceof JsonObject && $b instanceof JsonObject) {
            $r = $a->copy();
            foreach ($b->props as $k => $v) {
                $r->props[$k] = $v;
            }

            return $r;
        }

        throw JqException::of(self::typeMsg($a).' and '.self::typeMsg($b).' cannot be added');
    }

    private static function subtract(mixed $a, mixed $b): mixed
    {
        if (self::isNum($a) && self::isNum($b)) {
            return $a - $b;
        }
        if (is_array($a) && is_array($b)) {
            $out = [];
            foreach ($a as $item) {
                $found = false;
                foreach ($b as $rm) {
                    if (Values::equals($item, $rm)) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $out[] = $item;
                }
            }

            return $out;
        }

        throw JqException::of(self::typeMsg($a).' and '.self::typeMsg($b).' cannot be subtracted');
    }

    private static function multiply(mixed $a, mixed $b): mixed
    {
        if (self::isNum($a) && self::isNum($b)) {
            return $a * $b;
        }
        if (is_string($a) && self::isNum($b)) {
            return $b > 0 ? str_repeat($a, (int) $b) : null;
        }
        if (self::isNum($a) && is_string($b)) {
            return $a > 0 ? str_repeat($b, (int) $a) : null;
        }
        if ($a instanceof JsonObject && $b instanceof JsonObject) {
            return self::deepMerge($a, $b);
        }

        throw JqException::of(self::typeMsg($a).' and '.self::typeMsg($b).' cannot be multiplied');
    }

    private static function deepMerge(JsonObject $a, JsonObject $b): JsonObject
    {
        $r = $a->copy();
        foreach ($b->props as $k => $v) {
            $existing = $r->props[$k] ?? null;
            if ($existing instanceof JsonObject && $v instanceof JsonObject) {
                $r->props[$k] = self::deepMerge($existing, $v);
            } else {
                $r->props[$k] = $v;
            }
        }

        return $r;
    }

    private static function divide(mixed $a, mixed $b): mixed
    {
        if (self::isNum($a) && self::isNum($b)) {
            if ($b == 0) {
                throw JqException::of(self::typeMsg($a).' and '.self::typeMsg($b).' cannot be divided because the divisor is zero');
            }

            return $a / $b;
        }
        if (is_string($a) && is_string($b)) {
            if ($b === '') {
                $chars = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY);

                return $chars === false ? [] : $chars;
            }

            return explode($b, $a);
        }

        throw JqException::of(self::typeMsg($a).' and '.self::typeMsg($b).' cannot be divided');
    }

    private static function modulo(mixed $a, mixed $b): mixed
    {
        if (self::isNum($a) && self::isNum($b)) {
            $bi = (int) $b;
            if ($bi === 0) {
                throw JqException::of(self::typeMsg($a).' and '.self::typeMsg($b).' cannot be divided because the divisor is zero');
            }

            $res = abs((int) $a) % abs($bi);

            return $a < 0 ? -$res : $res;
        }

        throw JqException::of(self::typeMsg($a).' and '.self::typeMsg($b).' cannot be divided');
    }

    private static function isNum(mixed $v): bool
    {
        return is_int($v) || is_float($v);
    }

    private static function typeMsg(mixed $v): string
    {
        $type = Values::typeName($v);
        $repr = Values::encode($v, false);
        if (strlen($repr) > 11) {
            $repr = substr($repr, 0, 8).'...';
        }

        return "$type ($repr)";
    }
}

<?php

declare(strict_types=1);

namespace App\Jq\Runtime;

/**
 * getpath / setpath / delpaths — the primitives that update operators
 * (`=`, `|=`, `+=`, `del`, ...) are built on. Path components are strings
 * (object keys), ints (array indices) or ['start'=>, 'end'=>] slice maps.
 */
final class Paths
{
    /**
     * @param  list<mixed>  $path
     */
    public static function get(mixed $value, array $path): mixed
    {
        foreach ($path as $component) {
            if ($value === null) {
                return null;
            }
            if (is_array($component)) {
                // slice
                $value = self::sliceGet($value, $component);

                continue;
            }
            if (is_string($component)) {
                if ($value instanceof JsonObject) {
                    $value = $value->get($component);
                } else {
                    throw JqException::of('Cannot index '.Values::typeName($value)." with \"$component\"");
                }
            } else {
                $idx = (int) $component;
                if (is_array($value)) {
                    if ($idx < 0) {
                        $idx += count($value);
                    }
                    $value = $value[$idx] ?? null;
                } else {
                    throw JqException::of('Cannot index '.Values::typeName($value).' with number');
                }
            }
        }

        return $value;
    }

    private static function sliceGet(mixed $value, array $component): mixed
    {
        $start = $component['start'] ?? null;
        $end = $component['end'] ?? null;
        if (is_array($value)) {
            [$s, $e] = self::sliceBounds($start, $end, count($value));

            return array_slice($value, $s, $e - $s);
        }
        if (is_string($value)) {
            $len = mb_strlen($value);
            [$s, $e] = self::sliceBounds($start, $end, $len);

            return mb_substr($value, $s, $e - $s);
        }
        if ($value === null) {
            return null;
        }
        throw JqException::of('Cannot index '.Values::typeName($value).' with object');
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function sliceBounds(mixed $start, mixed $end, int $len): array
    {
        $s = $start === null ? 0 : (int) floor((float) $start);
        $e = $end === null ? $len : (int) floor((float) $end);
        if ($s < 0) {
            $s += $len;
        }
        if ($e < 0) {
            $e += $len;
        }
        $s = max(0, min($s, $len));
        $e = max(0, min($e, $len));
        if ($e < $s) {
            $e = $s;
        }

        return [$s, $e];
    }

    /**
     * @param  list<mixed>  $path
     */
    public static function set(mixed $value, array $path, mixed $newValue): mixed
    {
        if ($path === []) {
            return $newValue;
        }

        $component = $path[0];
        $rest = array_slice($path, 1);

        if (is_array($component)) {
            return self::setSlice($value, $component, $rest, $newValue);
        }

        if (is_string($component)) {
            $obj = $value instanceof JsonObject ? $value->copy() : ($value === null ? new JsonObject : throw JqException::of('Cannot index '.Values::typeName($value)." with \"$component\""));
            $obj->props[$component] = self::set($obj->props[$component] ?? null, $rest, $newValue);

            return $obj;
        }

        // integer index
        if ($value !== null && ! is_array($value)) {
            throw JqException::of('Cannot index '.Values::typeName($value).' with number');
        }
        $arr = is_array($value) ? $value : [];
        $idx = (int) $component;
        if ($idx < 0) {
            $idx += count($arr);
            if ($idx < 0) {
                throw JqException::of('Out of bounds negative array index');
            }
        }
        while (count($arr) <= $idx) {
            $arr[] = null;
        }
        $arr[$idx] = self::set($arr[$idx] ?? null, $rest, $newValue);

        return array_values($arr);
    }

    private static function setSlice(mixed $value, array $component, array $rest, mixed $newValue): mixed
    {
        $arr = is_array($value) ? $value : [];
        [$s, $e] = self::sliceBounds($component['start'] ?? null, $component['end'] ?? null, count($arr));
        $replacement = self::set(array_slice($arr, $s, $e - $s), $rest, $newValue);
        if (! is_array($replacement)) {
            throw JqException::of('A slice of an array can only be assigned another array');
        }

        return array_values(array_merge(array_slice($arr, 0, $s), $replacement, array_slice($arr, $e)));
    }

    /**
     * @param  list<list<mixed>>  $paths
     */
    public static function delete(mixed $value, array $paths): mixed
    {
        // delete deepest / largest indices first so earlier deletions don't shift
        usort($paths, fn ($a, $b) => Values::compare($b, $a));
        foreach ($paths as $p) {
            $value = self::deleteOne($value, $p);
        }

        return $value;
    }

    /**
     * @param  list<mixed>  $path
     */
    private static function deleteOne(mixed $value, array $path): mixed
    {
        if ($path === []) {
            return null;
        }
        if ($value === null) {
            return null;
        }

        $component = $path[0];
        $rest = array_slice($path, 1);

        if (count($path) === 1) {
            if (is_array($component)) {
                $arr = is_array($value) ? $value : [];
                [$s, $e] = self::sliceBounds($component['start'] ?? null, $component['end'] ?? null, count($arr));

                return array_values(array_merge(array_slice($arr, 0, $s), array_slice($arr, $e)));
            }
            if (is_string($component)) {
                if (! $value instanceof JsonObject) {
                    throw JqException::of('Cannot delete field of '.Values::typeName($value));
                }
                $obj = $value->copy();
                unset($obj->props[$component]);

                return $obj;
            }
            if (! is_array($value)) {
                throw JqException::of('Cannot delete element of '.Values::typeName($value));
            }
            $idx = (int) $component;
            if ($idx < 0) {
                $idx += count($value);
            }
            if ($idx < 0 || $idx >= count($value)) {
                return $value;
            }
            $copy = $value;
            array_splice($copy, $idx, 1);

            return $copy;
        }

        // recurse
        if (is_string($component) && $value instanceof JsonObject) {
            if (! $value->has($component)) {
                return $value;
            }
            $obj = $value->copy();
            $obj->props[$component] = self::deleteOne($obj->props[$component], $rest);

            return $obj;
        }
        if (! is_string($component) && is_array($value)) {
            $idx = (int) $component;
            if ($idx < 0) {
                $idx += count($value);
            }
            if ($idx < 0 || $idx >= count($value)) {
                return $value;
            }
            $copy = $value;
            $copy[$idx] = self::deleteOne($copy[$idx], $rest);

            return array_values($copy);
        }

        return $value;
    }
}

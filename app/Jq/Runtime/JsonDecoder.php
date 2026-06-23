<?php

declare(strict_types=1);

namespace App\Jq\Runtime;

use JsonException;

/**
 * Decodes a single JSON document into the runtime value model: JSON objects
 * become {@see JsonObject} (ordered), JSON arrays become PHP lists, preserving
 * the object/array distinction that json_decode($assoc) loses.
 */
final class JsonDecoder
{
    /**
     * @throws JsonException on malformed JSON
     */
    public static function decode(string $json): mixed
    {
        $raw = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

        return self::convert($raw);
    }

    public static function tryDecode(string $json, mixed &$out): bool
    {
        try {
            $out = self::decode($json);

            return true;
        } catch (JsonException) {
            return false;
        }
    }

    private static function convert(mixed $value): mixed
    {
        if (is_object($value)) {
            $obj = new JsonObject;
            foreach (get_object_vars($value) as $k => $v) {
                $obj->props[(string) $k] = self::convert($v);
            }

            return $obj;
        }
        if (is_array($value)) {
            return array_map(self::convert(...), $value);
        }

        return $value;
    }
}

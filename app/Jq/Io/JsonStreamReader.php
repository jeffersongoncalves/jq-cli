<?php

declare(strict_types=1);

namespace App\Jq\Io;

use App\Jq\Cli\CliConfig;
use App\Jq\Runtime\JqException;
use App\Jq\Runtime\JsonDecoder;
use Generator;

/**
 * Reads the input text into a stream of jq values, supporting concatenated
 * JSON documents, NDJSON, --slurp and --raw-input.
 */
final class JsonStreamReader
{
    /**
     * The base input stream honouring --raw-input / --slurp. Both the main loop
     * and the input/inputs builtins pull from this single iterator.
     */
    public function inputIterator(CliConfig $cfg, string $text): Generator
    {
        if ($cfg->rawInput) {
            if ($cfg->slurp) {
                yield $text;

                return;
            }
            yield from $this->rawLines($text);

            return;
        }

        if ($cfg->slurp) {
            yield iterator_to_array($this->readDocuments($text), false);

            return;
        }

        yield from $this->readDocuments($text);
    }

    /**
     * @return Generator<string>
     */
    private function rawLines(string $text): Generator
    {
        if ($text === '') {
            return;
        }
        $normalized = str_replace("\r\n", "\n", $text);
        $lines = explode("\n", $normalized);
        // a trailing newline produces an empty final element jq does not emit
        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }
        foreach ($lines as $line) {
            yield $line;
        }
    }

    /**
     * Splits concatenated/whitespace-separated JSON documents and decodes each.
     *
     * @return Generator<mixed>
     */
    public function readDocuments(string $text): Generator
    {
        $len = strlen($text);
        $i = 0;

        while ($i < $len) {
            $i = $this->skipWhitespace($text, $i);
            if ($i >= $len) {
                break;
            }
            $start = $i;
            $end = $this->scanValue($text, $i);
            $chunk = substr($text, $start, $end - $start);
            if (! JsonDecoder::tryDecode($chunk, $value)) {
                throw new JqException('Invalid JSON in input: '.substr($chunk, 0, 40));
            }
            yield $value;
            $i = $end;
        }
    }

    private function skipWhitespace(string $s, int $i): int
    {
        $len = strlen($s);
        while ($i < $len) {
            $c = $s[$i];
            if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") {
                $i++;

                continue;
            }
            break;
        }

        return $i;
    }

    private function scanValue(string $s, int $i): int
    {
        $c = $s[$i];
        if ($c === '{' || $c === '[') {
            return $this->scanContainer($s, $i);
        }
        if ($c === '"') {
            return $this->scanString($s, $i);
        }

        return $this->scanScalar($s, $i);
    }

    private function scanContainer(string $s, int $i): int
    {
        $len = strlen($s);
        $depth = 0;
        $inStr = false;
        while ($i < $len) {
            $ch = $s[$i];
            if ($inStr) {
                if ($ch === '\\') {
                    $i += 2;

                    continue;
                }
                if ($ch === '"') {
                    $inStr = false;
                }
                $i++;

                continue;
            }
            if ($ch === '"') {
                $inStr = true;
                $i++;

                continue;
            }
            if ($ch === '{' || $ch === '[') {
                $depth++;
            } elseif ($ch === '}' || $ch === ']') {
                $depth--;
                if ($depth === 0) {
                    return $i + 1;
                }
            }
            $i++;
        }

        return $len;
    }

    private function scanString(string $s, int $i): int
    {
        $len = strlen($s);
        $i++; // opening quote
        while ($i < $len) {
            $ch = $s[$i];
            if ($ch === '\\') {
                $i += 2;

                continue;
            }
            if ($ch === '"') {
                return $i + 1;
            }
            $i++;
        }

        return $len;
    }

    private function scanScalar(string $s, int $i): int
    {
        $len = strlen($s);
        while ($i < $len) {
            $ch = $s[$i];
            if ($ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r"
                || $ch === '{' || $ch === '[' || $ch === '}' || $ch === ']'
                || $ch === '"' || $ch === ',') {
                break;
            }
            $i++;
        }

        return $i;
    }
}

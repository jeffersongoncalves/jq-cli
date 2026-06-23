<?php

declare(strict_types=1);

namespace App\Jq\Cli;

use App\Jq\Engine;

/**
 * Standalone jq entry point. Parses raw argv (jq-style, bypassing Symfony's
 * option parser), gathers input, and drives the {@see Engine}.
 */
final class Runner
{
    /**
     * @param  list<string>  $args  argv without the script name
     * @param  resource|null  $out
     * @param  resource|null  $err
     */
    public static function main(array $args, $out = null, $err = null): int
    {
        $out ??= STDOUT;
        $err ??= STDERR;

        $parser = new ArgvParser;
        try {
            $cfg = $parser->parse($args);
        } catch (CliUsageException $e) {
            fwrite($err, 'jq: error: '.$e->getMessage()."\n");

            return Engine::EXIT_USAGE;
        }

        $program = self::resolveProgram($cfg, $err);
        if ($program === null) {
            fwrite($err, "jq: error: no filter given\nUsage: jq [OPTIONS] FILTER [FILES...]\n");

            return Engine::EXIT_USAGE;
        }

        try {
            $inputText = self::readInput($cfg);
        } catch (CliUsageException $e) {
            fwrite($err, 'jq: error: '.$e->getMessage()."\n");

            return Engine::EXIT_IO;
        }

        return (new Engine($out, $err))->run($cfg, $program, $inputText);
    }

    private static function resolveProgram(CliConfig $cfg, $err): ?string
    {
        if ($cfg->programFile !== null) {
            $content = @file_get_contents($cfg->programFile);
            if ($content === false) {
                fwrite($err, "jq: error: could not open program file: {$cfg->programFile}\n");

                return null;
            }

            return $content;
        }

        return $cfg->program;
    }

    private static function readInput(CliConfig $cfg): string
    {
        if ($cfg->files !== []) {
            $text = '';
            foreach ($cfg->files as $file) {
                $content = @file_get_contents($file);
                if ($content === false) {
                    throw new CliUsageException("could not open input file: $file");
                }
                $text .= $content;
            }

            return $text;
        }

        // avoid blocking on an interactive terminal when input is not needed
        if ($cfg->nullInput && self::stdinIsTty()) {
            return '';
        }

        $stdin = stream_get_contents(STDIN);

        return $stdin === false ? '' : $stdin;
    }

    private static function stdinIsTty(): bool
    {
        if (function_exists('stream_isatty')) {
            return @stream_isatty(STDIN);
        }

        return defined('STDIN') && function_exists('posix_isatty') && @posix_isatty(STDIN);
    }
}

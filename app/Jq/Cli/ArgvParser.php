<?php

declare(strict_types=1);

namespace App\Jq\Cli;

use App\Jq\Io\JsonStreamReader;
use App\Jq\Runtime\JsonDecoder;

/**
 * Maps a raw argv list (jq-style) into a {@see CliConfig}. The first bare
 * argument is the filter program (unless -f/--from-file is given); subsequent
 * bare arguments are input files.
 */
final class ArgvParser
{
    /**
     * @param  list<string>  $argv
     */
    public function parse(array $argv): CliConfig
    {
        $cfg = new CliConfig;
        $n = count($argv);
        $i = 0;
        $programTaken = false;

        while ($i < $n) {
            $arg = $argv[$i];

            if ($arg === '--') {
                $i++;
                break;
            }

            if ($arg === '--args') {
                $i++;
                while ($i < $n) {
                    $cfg->positional[] = $argv[$i++];
                }
                break;
            }

            if ($arg === '--jsonargs') {
                $i++;
                while ($i < $n) {
                    $cfg->positional[] = $this->decodeJson($argv[$i++], '--jsonargs');
                }
                break;
            }

            if ($this->isOption($arg)) {
                $i = $this->parseOption($cfg, $argv, $i);

                continue;
            }

            // bare argument: program first, then files
            if (! $programTaken && $cfg->programFile === null) {
                $cfg->program = $arg;
                $programTaken = true;
            } else {
                $cfg->files[] = $arg;
            }
            $i++;
        }

        // trailing files after -- separator
        while ($i < $n) {
            if (! $programTaken && $cfg->programFile === null) {
                $cfg->program = $argv[$i];
                $programTaken = true;
            } else {
                $cfg->files[] = $argv[$i];
            }
            $i++;
        }

        return $cfg;
    }

    private function isOption(string $arg): bool
    {
        return strlen($arg) >= 2 && $arg[0] === '-' && $arg !== '-';
    }

    /**
     * @param  list<string>  $argv
     */
    private function parseOption(CliConfig $cfg, array $argv, int $i): int
    {
        $arg = $argv[$i];

        // combined short flags like -rn
        if (strlen($arg) > 2 && $arg[1] !== '-') {
            foreach (str_split(substr($arg, 1)) as $ch) {
                $this->applyShort($cfg, '-'.$ch);
            }

            return $i + 1;
        }

        switch ($arg) {
            case '-n':
            case '--null-input':
                $cfg->nullInput = true;

                return $i + 1;
            case '-r':
            case '--raw-output':
                $cfg->rawOutput = true;

                return $i + 1;
            case '-j':
            case '--join-output':
                $cfg->rawOutput = true;
                $cfg->joinOutput = true;

                return $i + 1;
            case '-c':
            case '--compact-output':
                $cfg->compact = true;

                return $i + 1;
            case '-s':
            case '--slurp':
                $cfg->slurp = true;

                return $i + 1;
            case '-R':
            case '--raw-input':
                $cfg->rawInput = true;

                return $i + 1;
            case '-a':
            case '--ascii-output':
                $cfg->asciiOutput = true;

                return $i + 1;
            case '-S':
            case '--sort-keys':
                $cfg->sortKeys = true;

                return $i + 1;
            case '-e':
            case '--exit-status':
                $cfg->exitStatus = true;

                return $i + 1;
            case '--tab':
                $cfg->tab = true;

                return $i + 1;
            case '--indent':
                $cfg->indent = (int) $this->requireValue($argv, $i, '--indent');

                return $i + 2;
            case '-f':
            case '--from-file':
                $cfg->programFile = $this->requireValue($argv, $i, '--from-file');

                return $i + 2;
            case '--arg':
                $name = $this->requireValue($argv, $i, '--arg');
                $value = $this->requireValue($argv, $i + 1, '--arg');
                $cfg->namedArgs[$name] = $value;

                return $i + 3;
            case '--argjson':
                $name = $this->requireValue($argv, $i, '--argjson');
                $value = $this->requireValue($argv, $i + 1, '--argjson');
                $cfg->namedArgs[$name] = $this->decodeJson($value, '--argjson');

                return $i + 3;
            case '--slurpfile':
                $name = $this->requireValue($argv, $i, '--slurpfile');
                $file = $this->requireValue($argv, $i + 1, '--slurpfile');
                $cfg->namedArgs[$name] = $this->slurpFile($file);

                return $i + 3;
            case '--rawfile':
                $name = $this->requireValue($argv, $i, '--rawfile');
                $file = $this->requireValue($argv, $i + 1, '--rawfile');
                $cfg->namedArgs[$name] = $this->readFile($file);

                return $i + 3;
            case '-C':
            case '--color-output':
            case '-M':
            case '--monochrome-output':
            case '--stream':
            case '--seq':
            case '--unbuffered':
                // accepted but currently no-ops
                return $i + 1;
            default:
                throw new CliUsageException("Unknown option: $arg");
        }
    }

    private function applyShort(CliConfig $cfg, string $flag): void
    {
        match ($flag) {
            '-n' => $cfg->nullInput = true,
            '-r' => $cfg->rawOutput = true,
            '-j' => [$cfg->rawOutput = true, $cfg->joinOutput = true],
            '-c' => $cfg->compact = true,
            '-s' => $cfg->slurp = true,
            '-R' => $cfg->rawInput = true,
            '-a' => $cfg->asciiOutput = true,
            '-S' => $cfg->sortKeys = true,
            '-e' => $cfg->exitStatus = true,
            default => throw new CliUsageException("Unknown option: $flag"),
        };
    }

    /**
     * @param  list<string>  $argv
     */
    private function requireValue(array $argv, int $i, string $opt): string
    {
        if (! isset($argv[$i + 1])) {
            throw new CliUsageException("$opt requires an argument");
        }

        return $argv[$i + 1];
    }

    private function decodeJson(string $value, string $opt): mixed
    {
        if (! JsonDecoder::tryDecode($value, $out)) {
            throw new CliUsageException("Invalid JSON for $opt: $value");
        }

        return $out;
    }

    private function readFile(string $path): string
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new CliUsageException("Could not open file: $path");
        }

        return $content;
    }

    /**
     * @return list<mixed>
     */
    private function slurpFile(string $path): array
    {
        $content = $this->readFile($path);
        $reader = new JsonStreamReader;

        return iterator_to_array($reader->readDocuments($content), false);
    }
}

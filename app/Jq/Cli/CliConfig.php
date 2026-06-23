<?php

declare(strict_types=1);

namespace App\Jq\Cli;

/**
 * Parsed command-line options, mirroring jq's flags.
 */
final class CliConfig
{
    public bool $nullInput = false;

    public bool $rawInput = false;

    public bool $rawOutput = false;

    public bool $joinOutput = false;

    public bool $compact = false;

    public bool $slurp = false;

    public bool $asciiOutput = false;

    public bool $sortKeys = false;

    public bool $exitStatus = false;

    public bool $tab = false;

    public ?int $indent = null;

    public ?string $programFile = null;

    public ?string $program = null;

    /** @var array<string, mixed> named args from --arg/--argjson/--slurpfile/--rawfile */
    public array $namedArgs = [];

    /** @var list<mixed> positional args from --args/--jsonargs */
    public array $positional = [];

    /** @var list<string> input file paths */
    public array $files = [];

    /**
     * @return array{indent?: int|null, tab?: bool, sortKeys?: bool, ascii?: bool}
     */
    public function encodeOptions(): array
    {
        $indent = $this->compact ? null : ($this->indent ?? 2);

        return [
            'indent' => $indent,
            'tab' => $this->tab,
            'sortKeys' => $this->sortKeys,
            'ascii' => $this->asciiOutput,
        ];
    }
}

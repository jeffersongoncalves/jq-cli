<?php

declare(strict_types=1);

namespace App\Jq\Io;

use App\Jq\Cli\CliConfig;
use App\Jq\Runtime\Values;

/**
 * Encodes output values according to the CLI flags (pretty/compact/tab/indent,
 * raw, ascii, sort-keys).
 */
final class OutputEncoder
{
    public function __construct(private readonly CliConfig $cfg) {}

    public function encode(mixed $value): string
    {
        if ($this->cfg->rawOutput && is_string($value)) {
            return $value;
        }

        return Values::encode($value, ! $this->cfg->compact, $this->cfg->encodeOptions());
    }

    public function separator(): string
    {
        return $this->cfg->joinOutput ? '' : "\n";
    }
}

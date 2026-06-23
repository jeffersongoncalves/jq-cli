<?php

declare(strict_types=1);

namespace App\Jq;

use RuntimeException;

/**
 * Thrown for lexer/parser errors. Maps to jq exit code 3 (program compile error).
 */
final class JqParseException extends RuntimeException
{
    public readonly int $errorLine;

    public readonly int $errorColumn;

    public function __construct(
        string $message,
        int $line = 0,
        int $col = 0,
    ) {
        $this->errorLine = $line;
        $this->errorColumn = $col;
        $location = $line > 0 ? " at {$line}:{$col}" : '';
        parent::__construct("jq: error: {$message}{$location}");
    }
}

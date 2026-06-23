<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/**
 * Update/assignment operators built on path expressions:
 * `=`, `|=`, `+=`, `-=`, `*=`, `/=`, `%=`, `//=`.
 * $op holds the raw operator token text.
 */
final class Assignment implements Node
{
    public function __construct(
        public readonly string $op,
        public readonly Node $path,
        public readonly Node $value,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** `label $name | body` — establishes a break target. */
final class Label implements Node
{
    public function __construct(
        public readonly string $name,
        public readonly Node $body,
    ) {}
}

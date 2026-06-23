<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** Pipe: `left | right`. */
final class Pipe implements Node
{
    public function __construct(
        public readonly Node $left,
        public readonly Node $right,
    ) {}
}

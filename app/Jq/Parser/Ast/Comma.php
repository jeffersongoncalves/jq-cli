<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** Comma: `left , right` — concatenates the two output streams. */
final class Comma implements Node
{
    public function __construct(
        public readonly Node $left,
        public readonly Node $right,
    ) {}
}

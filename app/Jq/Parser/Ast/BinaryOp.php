<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** Binary operator: arithmetic, comparison, and/or. */
final class BinaryOp implements Node
{
    public function __construct(
        public readonly string $op,
        public readonly Node $left,
        public readonly Node $right,
    ) {}
}

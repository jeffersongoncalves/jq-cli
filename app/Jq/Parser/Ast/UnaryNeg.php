<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** Unary negation: `-expr`. */
final class UnaryNeg implements Node
{
    public function __construct(public readonly Node $operand) {}
}

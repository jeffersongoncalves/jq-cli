<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** Destructuring leaf: `$name`. */
final class VarPattern implements Pattern
{
    public function __construct(public readonly string $name) {}
}

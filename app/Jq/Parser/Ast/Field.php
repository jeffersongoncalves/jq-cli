<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** Field access: `.foo` (operating on the input value). */
final class Field implements Node
{
    public function __construct(public readonly string $name) {}
}

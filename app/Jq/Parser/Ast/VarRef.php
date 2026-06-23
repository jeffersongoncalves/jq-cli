<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** Variable reference: `$name` (also `$ENV`, `$__loc__`, `$__prog_args`). */
final class VarRef implements Node
{
    public function __construct(public readonly string $name) {}
}

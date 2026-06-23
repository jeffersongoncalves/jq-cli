<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** Index access: `.[expr]` — index by the value(s) produced by $index. */
final class Index implements Node
{
    public function __construct(public readonly Node $index) {}
}

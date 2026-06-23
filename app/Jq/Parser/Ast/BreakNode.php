<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** `break $name` — unwinds to the matching label. */
final class BreakNode implements Node
{
    public function __construct(public readonly string $name) {}
}

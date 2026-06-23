<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** Array construction: `[ body ]` collects the body's output stream. */
final class ArrayConstruct implements Node
{
    public function __construct(public readonly ?Node $body) {}
}

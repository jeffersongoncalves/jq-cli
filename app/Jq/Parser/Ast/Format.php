<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** A bare format filter used as a function: `@base64`, `@csv`, ... */
final class Format implements Node
{
    public function __construct(public readonly string $name) {}
}

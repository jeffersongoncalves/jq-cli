<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** A literal JSON value: number, plain string, true, false, null. */
final class Literal implements Node
{
    public function __construct(public readonly mixed $value) {}
}

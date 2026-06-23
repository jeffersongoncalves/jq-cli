<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** Alternative / default operator: `left // right`. */
final class Alternative implements Node
{
    public function __construct(
        public readonly Node $left,
        public readonly Node $right,
    ) {}
}

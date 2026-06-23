<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** Array/string slice: `.[from:to]` (either bound may be null). */
final class Slice implements Node
{
    public function __construct(
        public readonly ?Node $from,
        public readonly ?Node $to,
    ) {}
}

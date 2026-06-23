<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** `foreach source as $pattern (init; update; extract?)`. */
final class ForeachNode implements Node
{
    public function __construct(
        public readonly Node $source,
        public readonly Pattern $pattern,
        public readonly Node $init,
        public readonly Node $update,
        public readonly ?Node $extract,
    ) {}
}

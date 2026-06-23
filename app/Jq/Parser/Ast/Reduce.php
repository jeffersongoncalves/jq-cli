<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** `reduce source as $pattern (init; update)`. */
final class Reduce implements Node
{
    public function __construct(
        public readonly Node $source,
        public readonly Pattern $pattern,
        public readonly Node $init,
        public readonly Node $update,
    ) {}
}

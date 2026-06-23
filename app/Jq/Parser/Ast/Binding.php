<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/**
 * Variable binding with destructuring: `source as pat ?// pat2 | body`.
 *
 * @property list<Pattern> $patterns
 */
final class Binding implements Node
{
    /**
     * @param  list<Pattern>  $patterns
     */
    public function __construct(
        public readonly Node $source,
        public readonly array $patterns,
        public readonly Node $body,
    ) {}
}

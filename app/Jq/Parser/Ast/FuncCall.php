<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/**
 * Function/builtin call: `name` or `name(arg1; arg2)`.
 * Arguments are filters (nodes), not pre-evaluated values.
 *
 * @property list<Node> $args
 */
final class FuncCall implements Node
{
    /**
     * @param  list<Node>  $args
     */
    public function __construct(
        public readonly string $name,
        public readonly array $args = [],
    ) {}

    public function arity(): int
    {
        return count($this->args);
    }
}

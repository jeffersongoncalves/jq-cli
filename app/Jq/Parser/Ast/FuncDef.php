<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/**
 * Function definition: `def name(p1; p2): body; rest`.
 * Params prefixed with `$` are value-params (bound to the arg's value);
 * plain params are filter-params.
 *
 * @property list<string> $params
 */
final class FuncDef implements Node
{
    /**
     * @param  list<string>  $params
     */
    public function __construct(
        public readonly string $name,
        public readonly array $params,
        public readonly Node $body,
        public readonly Node $rest,
    ) {}
}

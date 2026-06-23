<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/**
 * Object construction: `{ a: .x, "b": 1, $v, (expr): val }`.
 * Each entry is [keyNode, valueNode]; both produce streams (cartesian product).
 *
 * @property list<array{0: Node, 1: Node}> $entries
 */
final class ObjectConstruct implements Node
{
    /**
     * @param  list<array{0: Node, 1: Node}>  $entries
     */
    public function __construct(public readonly array $entries) {}
}

<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/**
 * `if cond then a (elif cond then b)* (else c)? end`.
 *
 * @property list<array{0: Node, 1: Node}> $elifs
 */
final class IfThenElse implements Node
{
    /**
     * @param  list<array{0: Node, 1: Node}>  $elifs
     */
    public function __construct(
        public readonly Node $cond,
        public readonly Node $then,
        public readonly array $elifs,
        public readonly ?Node $else,
    ) {}
}

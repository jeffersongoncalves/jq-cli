<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/**
 * Array destructuring: `[$a, $b, {c: $c}]`.
 *
 * @property list<Pattern> $elements
 */
final class ArrayPattern implements Pattern
{
    /**
     * @param  list<Pattern>  $elements
     */
    public function __construct(public readonly array $elements) {}
}

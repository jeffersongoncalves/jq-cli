<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/**
 * Object destructuring: `{a: $x, $y, (expr): $z, "k": $w}`.
 * Each entry: ['key' => keySpec, 'value' => Pattern].
 * keySpec is one of:
 *   ['ident' => string]   plain key `a:`
 *   ['var' => string]     shorthand `$y` (binds $y to .y)
 *   ['expr' => Node]      computed key `(expr):`
 *   ['string' => Node]    string/interp key `"k":`
 *
 * @property list<array{key: array<string, mixed>, value: Pattern}> $entries
 */
final class ObjectPattern implements Pattern
{
    /**
     * @param  list<array{key: array<string, mixed>, value: Pattern}>  $entries
     */
    public function __construct(public readonly array $entries) {}
}

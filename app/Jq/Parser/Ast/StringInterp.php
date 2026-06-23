<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/**
 * Interpolated string: `"a \(expr) b"`. Parts are literal strings or Nodes.
 * $format, when set (e.g. "base64"), is applied to each interpolated value.
 *
 * @property list<string|Node> $parts
 */
final class StringInterp implements Node
{
    /**
     * @param  list<string|Node>  $parts
     */
    public function __construct(
        public readonly array $parts,
        public readonly ?string $format = null,
    ) {}
}

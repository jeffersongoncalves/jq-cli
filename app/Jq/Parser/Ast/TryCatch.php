<?php

declare(strict_types=1);

namespace App\Jq\Parser\Ast;

/** `try body catch handler` (handler optional; `body?` is try with no handler). */
final class TryCatch implements Node
{
    public function __construct(
        public readonly Node $body,
        public readonly ?Node $handler,
    ) {}
}

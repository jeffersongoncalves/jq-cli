<?php

declare(strict_types=1);

namespace App\Jq\Lexer;

final class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly mixed $value,
        public readonly int $line,
        public readonly int $col,
    ) {}

    public function is(TokenType $type): bool
    {
        return $this->type === $type;
    }

    public function __toString(): string
    {
        return $this->type->value.'('.(is_scalar($this->value) ? (string) $this->value : gettype($this->value)).')';
    }
}

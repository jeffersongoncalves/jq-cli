<?php

declare(strict_types=1);

namespace App\Jq\Runtime;

use RuntimeException;

/**
 * A jq runtime error. Carries an arbitrary jq value (usually a string) so that
 * `try ... catch e` and `error(v)` can round-trip non-string payloads.
 */
final class JqException extends RuntimeException
{
    public function __construct(public readonly mixed $value)
    {
        parent::__construct(is_string($value) ? $value : Values::encode($value, false));
    }

    public static function of(string $message): self
    {
        return new self($message);
    }
}

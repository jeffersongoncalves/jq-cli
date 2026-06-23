<?php

declare(strict_types=1);

namespace App\Jq\Runtime;

use RuntimeException;

/** Internal control-flow signal for `break $label`. */
final class BreakException extends RuntimeException
{
    public function __construct(public readonly string $label)
    {
        parent::__construct("break $label");
    }
}

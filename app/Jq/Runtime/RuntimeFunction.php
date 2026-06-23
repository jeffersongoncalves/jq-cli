<?php

declare(strict_types=1);

namespace App\Jq\Runtime;

use App\Jq\Parser\Ast\Node;
use Closure;

/**
 * A callable jq function. Either a user/prelude definition (params + body +
 * the environment it closed over) or a native PHP closure used for filter
 * arguments and engine builtins.
 */
final class RuntimeFunction
{
    /**
     * @param  list<string>|null  $params
     * @param  (Closure(mixed): \Generator)|null  $native
     */
    public function __construct(
        public readonly ?array $params = null,
        public readonly ?Node $body = null,
        public readonly ?Environment $closure = null,
        public readonly ?Closure $native = null,
    ) {}

    public static function user(array $params, Node $body, Environment $closure): self
    {
        return new self(params: $params, body: $body, closure: $closure);
    }

    public static function fromClosure(Closure $native): self
    {
        return new self(native: $native);
    }
}

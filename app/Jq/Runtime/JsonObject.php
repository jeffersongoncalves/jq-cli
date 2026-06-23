<?php

declare(strict_types=1);

namespace App\Jq\Runtime;

/**
 * Represents a JSON object with preserved insertion order. Distinct from PHP
 * lists (which model JSON arrays) so that `{}` and `[]` never collide and key
 * ordering is honoured exactly like jq.
 */
final class JsonObject
{
    /**
     * @param  array<array-key, mixed>  $props  ordered map of key => value
     */
    public function __construct(public array $props = []) {}

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->props);
    }

    public function get(string $key): mixed
    {
        return $this->props[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->props[$key] = $value;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map('strval', array_keys($this->props));
    }

    public function count(): int
    {
        return count($this->props);
    }

    public function copy(): self
    {
        return new self($this->props);
    }
}

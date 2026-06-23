<?php

declare(strict_types=1);

namespace App\Jq\Runtime;

/**
 * Lexical scope: variables ($x) and function definitions (name/arity), chained
 * to a parent scope. Lookups walk the parent chain.
 */
final class Environment
{
    /** @var array<string, mixed> */
    private array $vars = [];

    /** @var array<string, RuntimeFunction> keyed by "name/arity" */
    private array $funcs = [];

    public function __construct(public readonly ?Environment $parent = null) {}

    public function child(): self
    {
        return new self($this);
    }

    public function setVar(string $name, mixed $value): void
    {
        $this->vars[$name] = $value;
    }

    public function hasVar(string $name): bool
    {
        if (array_key_exists($name, $this->vars)) {
            return true;
        }

        return $this->parent?->hasVar($name) ?? false;
    }

    public function getVar(string $name): mixed
    {
        if (array_key_exists($name, $this->vars)) {
            return $this->vars[$name];
        }
        if ($this->parent !== null) {
            return $this->parent->getVar($name);
        }
        throw JqException::of("\$$name is not defined");
    }

    public function defineFunc(string $name, int $arity, RuntimeFunction $fn): void
    {
        $this->funcs["$name/$arity"] = $fn;
    }

    public function resolveFunc(string $name, int $arity): ?RuntimeFunction
    {
        $key = "$name/$arity";
        if (isset($this->funcs[$key])) {
            return $this->funcs[$key];
        }

        return $this->parent?->resolveFunc($name, $arity);
    }
}

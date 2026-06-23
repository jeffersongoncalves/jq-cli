<?php

declare(strict_types=1);

namespace App\Jq\Runtime;

use App\Jq\Parser\Ast\Alternative;
use App\Jq\Parser\Ast\ArrayConstruct;
use App\Jq\Parser\Ast\ArrayPattern;
use App\Jq\Parser\Ast\Assignment;
use App\Jq\Parser\Ast\BinaryOp;
use App\Jq\Parser\Ast\Binding;
use App\Jq\Parser\Ast\BreakNode;
use App\Jq\Parser\Ast\Comma;
use App\Jq\Parser\Ast\Field;
use App\Jq\Parser\Ast\ForeachNode;
use App\Jq\Parser\Ast\Format;
use App\Jq\Parser\Ast\FuncCall;
use App\Jq\Parser\Ast\FuncDef;
use App\Jq\Parser\Ast\Identity;
use App\Jq\Parser\Ast\IfThenElse;
use App\Jq\Parser\Ast\Index;
use App\Jq\Parser\Ast\Iterate;
use App\Jq\Parser\Ast\Label;
use App\Jq\Parser\Ast\Literal;
use App\Jq\Parser\Ast\Node;
use App\Jq\Parser\Ast\ObjectConstruct;
use App\Jq\Parser\Ast\ObjectPattern;
use App\Jq\Parser\Ast\Pattern;
use App\Jq\Parser\Ast\Pipe;
use App\Jq\Parser\Ast\Recurse;
use App\Jq\Parser\Ast\Reduce;
use App\Jq\Parser\Ast\Slice;
use App\Jq\Parser\Ast\StringInterp;
use App\Jq\Parser\Ast\TryCatch;
use App\Jq\Parser\Ast\UnaryNeg;
use App\Jq\Parser\Ast\VarPattern;
use App\Jq\Parser\Ast\VarRef;
use Closure;
use Generator;

/**
 * Tree-walking interpreter. Every expression evaluates to a {@see Generator}
 * of output values, honouring jq's stream semantics (0..N outputs per filter).
 */
final class Interpreter
{
    private int $labelCounter = 0;

    private readonly Builtins $builtins;

    /** @var Closure(): array{0: bool, 1: mixed}|null */
    public ?Closure $inputPuller = null;

    public function __construct()
    {
        $this->builtins = new Builtins;
    }

    public function setInputPuller(Closure $puller): void
    {
        $this->inputPuller = $puller;
        $this->builtins->inputPuller = $puller;
    }

    /**
     * Registers leading `def`s (e.g. the prelude) into $env and returns the
     * innermost remaining program node.
     */
    public function installDefs(Node $program, Environment $env): Node
    {
        while ($program instanceof FuncDef) {
            $env->defineFunc(
                $program->name,
                count($program->params),
                RuntimeFunction::user($program->params, $program->body, $env),
            );
            $program = $program->rest;
        }

        return $program;
    }

    /**
     * Evaluates a node against an input value, yielding the output stream.
     */
    public function eval(Node $node, mixed $input, Environment $env): Generator
    {
        switch ($node::class) {
            case Identity::class:
                yield $input;

                return;

            case Recurse::class:
                yield from $this->recurseAll($input);

                return;

            case Literal::class:
                yield $node->value;

                return;

            case Field::class:
                yield $this->fieldAccess($input, $node->name);

                return;

            case Index::class:
                foreach ($this->eval($node->index, $input, $env) as $key) {
                    yield $this->indexValue($input, $key);
                }

                return;

            case Iterate::class:
                yield from $this->iterate($input);

                return;

            case Slice::class:
                yield from $this->evalSlice($node, $input, $env);

                return;

            case Pipe::class:
                foreach ($this->eval($node->left, $input, $env) as $lv) {
                    yield from $this->eval($node->right, $lv, $env);
                }

                return;

            case Comma::class:
                yield from $this->eval($node->left, $input, $env);
                yield from $this->eval($node->right, $input, $env);

                return;

            case ArrayConstruct::class:
                if ($node->body === null) {
                    yield [];

                    return;
                }
                yield iterator_to_array($this->eval($node->body, $input, $env), false);

                return;

            case ObjectConstruct::class:
                yield from $this->evalObject($node, $input, $env);

                return;

            case StringInterp::class:
                yield from $this->evalStringInterp($node, $input, $env);

                return;

            case BinaryOp::class:
                yield from $this->evalBinary($node, $input, $env);

                return;

            case UnaryNeg::class:
                foreach ($this->eval($node->operand, $input, $env) as $v) {
                    if (! is_int($v) && ! is_float($v)) {
                        throw JqException::of(Values::typeName($v).' cannot be negated');
                    }
                    yield -$v;
                }

                return;

            case Alternative::class:
                yield from $this->evalAlternative($node, $input, $env);

                return;

            case IfThenElse::class:
                yield from $this->evalIf($node->cond, $node->then, $node->elifs, $node->else, $input, $env);

                return;

            case TryCatch::class:
                yield from $this->evalTry($node, $input, $env);

                return;

            case Binding::class:
                yield from $this->evalBinding($node, $input, $env);

                return;

            case Reduce::class:
                yield from $this->evalReduce($node, $input, $env);

                return;

            case ForeachNode::class:
                yield from $this->evalForeach($node, $input, $env);

                return;

            case VarRef::class:
                yield $this->evalVar($node, $env);

                return;

            case FuncDef::class:
                $child = $env->child();
                $child->defineFunc($node->name, count($node->params), RuntimeFunction::user($node->params, $node->body, $child));
                yield from $this->eval($node->rest, $input, $child);

                return;

            case FuncCall::class:
                yield from $this->evalCall($node, $input, $env);

                return;

            case Format::class:
                yield $this->builtins->applyFormat($node->name, $input);

                return;

            case Label::class:
                yield from $this->evalLabel($node, $input, $env);

                return;

            case BreakNode::class:
                throw new BreakException((string) $env->getVar('*label:'.$node->name));
            case Assignment::class:
                yield from $this->evalAssignment($node, $input, $env);

                return;

            default:
                throw JqException::of('cannot evaluate node '.$node::class);
        }
    }

    /**
     * Convenience: evaluate and return the first output (or null if none).
     */
    public function first(Node $node, mixed $input, Environment $env): mixed
    {
        foreach ($this->eval($node, $input, $env) as $v) {
            return $v;
        }

        return null;
    }

    // ----- value access ----------------------------------------------------

    private function fieldAccess(mixed $input, string $name): mixed
    {
        if ($input === null) {
            return null;
        }
        if ($input instanceof JsonObject) {
            return $input->get($name);
        }

        throw JqException::of('Cannot index '.Values::typeName($input)." with \"$name\"");
    }

    private function indexValue(mixed $input, mixed $key): mixed
    {
        if ($input === null) {
            return null;
        }
        if (is_string($key)) {
            if ($input instanceof JsonObject) {
                return $input->get($key);
            }
            throw JqException::of('Cannot index '.Values::typeName($input)." with \"$key\"");
        }
        if (is_int($key) || is_float($key)) {
            if (is_array($input)) {
                $idx = (int) $key;
                if ($idx < 0) {
                    $idx += count($input);
                }

                return $input[$idx] ?? null;
            }
            throw JqException::of('Cannot index '.Values::typeName($input).' with number');
        }
        if (is_array($key) && is_array($input)) {
            // array .[ [sub] ] => indices of sub-array. Rarely used; return indices.
            return $this->builtins->indices($input, $key);
        }
        if ($input instanceof JsonObject && $key === null) {
            throw JqException::of('Cannot index object with null');
        }

        throw JqException::of('Cannot index '.Values::typeName($input).' with '.Values::typeName($key));
    }

    private function iterate(mixed $input): Generator
    {
        if (is_array($input)) {
            foreach ($input as $v) {
                yield $v;
            }

            return;
        }
        if ($input instanceof JsonObject) {
            foreach ($input->props as $v) {
                yield $v;
            }

            return;
        }

        throw JqException::of('Cannot iterate over '.Values::typeName($input).' ('.Values::encode($input, false).')');
    }

    private function evalSlice(Slice $node, mixed $input, Environment $env): Generator
    {
        $froms = $node->from === null ? [null] : $this->eval($node->from, $input, $env);
        foreach ($froms as $from) {
            $tos = $node->to === null ? [null] : $this->eval($node->to, $input, $env);
            foreach ($tos as $to) {
                yield Paths::get($input, [['start' => $from, 'end' => $to]]);
            }
        }
    }

    private function recurseAll(mixed $value): Generator
    {
        yield $value;
        if (is_array($value)) {
            foreach ($value as $v) {
                yield from $this->recurseAll($v);
            }
        } elseif ($value instanceof JsonObject) {
            foreach ($value->props as $v) {
                yield from $this->recurseAll($v);
            }
        }
    }

    // ----- construction ----------------------------------------------------

    private function evalObject(ObjectConstruct $node, mixed $input, Environment $env): Generator
    {
        yield from $this->buildObject($node->entries, 0, new JsonObject, $input, $env);
    }

    /**
     * @param  list<array{0: Node, 1: Node}>  $entries
     */
    private function buildObject(array $entries, int $i, JsonObject $acc, mixed $input, Environment $env): Generator
    {
        if ($i >= count($entries)) {
            yield $acc->copy();

            return;
        }
        [$keyNode, $valueNode] = $entries[$i];
        foreach ($this->eval($keyNode, $input, $env) as $key) {
            if (! is_string($key)) {
                throw JqException::of('Object keys must be strings, got '.Values::typeName($key));
            }
            foreach ($this->eval($valueNode, $input, $env) as $value) {
                $next = $acc->copy();
                $next->props[$key] = $value;
                yield from $this->buildObject($entries, $i + 1, $next, $input, $env);
            }
        }
    }

    private function evalStringInterp(StringInterp $node, mixed $input, Environment $env): Generator
    {
        yield from $this->buildString($node->parts, 0, '', $node->format, $input, $env);
    }

    /**
     * @param  list<string|Node>  $parts
     */
    private function buildString(array $parts, int $i, string $acc, ?string $format, mixed $input, Environment $env): Generator
    {
        if ($i >= count($parts)) {
            yield $acc;

            return;
        }
        $part = $parts[$i];
        if (is_string($part)) {
            yield from $this->buildString($parts, $i + 1, $acc.$part, $format, $input, $env);

            return;
        }
        foreach ($this->eval($part, $input, $env) as $v) {
            $piece = $format !== null
                ? $this->builtins->applyFormat($format, $v)
                : (is_string($v) ? $v : Values::encode($v, false));
            yield from $this->buildString($parts, $i + 1, $acc.$piece, $format, $input, $env);
        }
    }

    // ----- operators -------------------------------------------------------

    private function evalBinary(BinaryOp $node, mixed $input, Environment $env): Generator
    {
        if ($node->op === 'and') {
            foreach ($this->eval($node->left, $input, $env) as $l) {
                if (! Values::truthy($l)) {
                    yield false;
                } else {
                    foreach ($this->eval($node->right, $input, $env) as $r) {
                        yield Values::truthy($r);
                    }
                }
            }

            return;
        }
        if ($node->op === 'or') {
            foreach ($this->eval($node->left, $input, $env) as $l) {
                if (Values::truthy($l)) {
                    yield true;
                } else {
                    foreach ($this->eval($node->right, $input, $env) as $r) {
                        yield Values::truthy($r);
                    }
                }
            }

            return;
        }

        $comparisons = ['==', '!=', '<', '<=', '>', '>='];
        // right operand is the outer loop (matches jq's binding order)
        foreach ($this->eval($node->right, $input, $env) as $r) {
            foreach ($this->eval($node->left, $input, $env) as $l) {
                if (in_array($node->op, $comparisons, true)) {
                    yield $this->compareOp($node->op, $l, $r);
                } else {
                    yield Operators::apply($node->op, $l, $r);
                }
            }
        }
    }

    private function compareOp(string $op, mixed $l, mixed $r): bool
    {
        $c = Values::compare($l, $r);

        return match ($op) {
            '==' => $c === 0,
            '!=' => $c !== 0,
            '<' => $c < 0,
            '<=' => $c <= 0,
            '>' => $c > 0,
            '>=' => $c >= 0,
            default => false,
        };
    }

    private function evalAlternative(Alternative $node, mixed $input, Environment $env): Generator
    {
        $emitted = false;
        try {
            foreach ($this->eval($node->left, $input, $env) as $lv) {
                if (Values::truthy($lv)) {
                    $emitted = true;
                    yield $lv;
                }
            }
        } catch (JqException) {
            // errors on the left are suppressed by //
        }
        if (! $emitted) {
            yield from $this->eval($node->right, $input, $env);
        }
    }

    /**
     * @param  list<array{0: Node, 1: Node}>  $elifs
     */
    private function evalIf(Node $cond, Node $then, array $elifs, ?Node $else, mixed $input, Environment $env): Generator
    {
        foreach ($this->eval($cond, $input, $env) as $c) {
            if (Values::truthy($c)) {
                yield from $this->eval($then, $input, $env);
            } elseif ($elifs !== []) {
                [$ec, $et] = $elifs[0];
                yield from $this->evalIf($ec, $et, array_slice($elifs, 1), $else, $input, $env);
            } elseif ($else !== null) {
                yield from $this->eval($else, $input, $env);
            } else {
                yield $input;
            }
        }
    }

    private function evalTry(TryCatch $node, mixed $input, Environment $env): Generator
    {
        $iterator = $this->eval($node->body, $input, $env);
        while (true) {
            try {
                if (! $iterator->valid()) {
                    break;
                }
                $value = $iterator->current();
                $iterator->next();
            } catch (JqException $e) {
                if ($node->handler !== null) {
                    yield from $this->eval($node->handler, $e->value, $env);
                }

                return;
            }
            yield $value;
        }
    }

    // ----- binding / destructuring -----------------------------------------

    private function evalBinding(Binding $node, mixed $input, Environment $env): Generator
    {
        foreach ($this->eval($node->source, $input, $env) as $sv) {
            foreach ($this->bindPatterns($node->patterns, $sv, $env) as $bound) {
                yield from $this->eval($node->body, $input, $bound);
            }
        }
    }

    /**
     * @param  list<Pattern>  $patterns
     */
    private function bindPatterns(array $patterns, mixed $value, Environment $env): Generator
    {
        $allVars = [];
        foreach ($patterns as $p) {
            $this->collectPatternVars($p, $allVars);
        }

        $lastError = null;
        foreach ($patterns as $idx => $pattern) {
            try {
                foreach ($this->bindPattern($pattern, $value, $env->child()) as $bound) {
                    foreach (array_keys($allVars) as $v) {
                        if (! $bound->hasVar($v)) {
                            $bound->setVar($v, null);
                        }
                    }
                    yield $bound;
                }

                return;
            } catch (JqException $e) {
                $lastError = $e;
                if ($idx === count($patterns) - 1) {
                    throw $e;
                }
            }
        }
        if ($lastError !== null) {
            throw $lastError;
        }
    }

    /**
     * @param  array<string, true>  $vars
     */
    private function collectPatternVars(Pattern $pattern, array &$vars): void
    {
        if ($pattern instanceof VarPattern) {
            $vars[$pattern->name] = true;
        } elseif ($pattern instanceof ArrayPattern) {
            foreach ($pattern->elements as $el) {
                $this->collectPatternVars($el, $vars);
            }
        } elseif ($pattern instanceof ObjectPattern) {
            foreach ($pattern->entries as $entry) {
                if (isset($entry['key']['var'])) {
                    $vars[$entry['key']['var']] = true;
                }
                $this->collectPatternVars($entry['value'], $vars);
            }
        }
    }

    private function bindPattern(Pattern $pattern, mixed $value, Environment $env): Generator
    {
        if ($pattern instanceof VarPattern) {
            $env->setVar($pattern->name, $value);
            yield $env;

            return;
        }

        if ($pattern instanceof ArrayPattern) {
            yield from $this->bindArrayPattern($pattern->elements, 0, $value, $env);

            return;
        }

        if ($pattern instanceof ObjectPattern) {
            yield from $this->bindObjectPattern($pattern->entries, 0, $value, $env);

            return;
        }

        yield $env;
    }

    /**
     * @param  list<Pattern>  $elements
     */
    private function bindArrayPattern(array $elements, int $i, mixed $value, Environment $env): Generator
    {
        if ($i >= count($elements)) {
            yield $env;

            return;
        }
        $element = $value === null ? null : $this->indexValue($value, $i);
        foreach ($this->bindPattern($elements[$i], $element, $env) as $env2) {
            yield from $this->bindArrayPattern($elements, $i + 1, $value, $env2);
        }
    }

    /**
     * @param  list<array{key: array<string, mixed>, value: Pattern}>  $entries
     */
    private function bindObjectPattern(array $entries, int $i, mixed $value, Environment $env): Generator
    {
        if ($i >= count($entries)) {
            yield $env;

            return;
        }
        $entry = $entries[$i];
        $key = $entry['key'];

        // resolve key(s) -> stream of [keyString, env]
        $keyStream = $this->resolvePatternKey($key, $value, $env);
        foreach ($keyStream as [$keyString, $env2]) {
            $sub = $value === null ? null : $this->indexValue($value, $keyString);
            foreach ($this->bindPattern($entry['value'], $sub, $env2) as $env3) {
                yield from $this->bindObjectPattern($entries, $i + 1, $value, $env3);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $key
     * @return Generator<array{0: string, 1: Environment}>
     */
    private function resolvePatternKey(array $key, mixed $value, Environment $env): Generator
    {
        if (isset($key['ident'])) {
            $name = (string) $key['ident'];
            $env2 = $env->child();
            if (array_key_exists('var', $key) || true) {
                // {$a} shorthand also binds $a to the field value
            }
            $env2->setVar($name, $value === null ? null : $this->indexValue($value, $name));
            yield [$name, $env2];

            return;
        }
        if (isset($key['string'])) {
            foreach ($this->eval($key['string'], $value, $env) as $k) {
                yield [(string) $k, $env];
            }

            return;
        }
        if (isset($key['expr'])) {
            foreach ($this->eval($key['expr'], $value, $env) as $k) {
                yield [(string) $k, $env];
            }

            return;
        }
        yield ['', $env];
    }

    // ----- reduce / foreach ------------------------------------------------

    private function evalReduce(Reduce $node, mixed $input, Environment $env): Generator
    {
        foreach ($this->eval($node->init, $input, $env) as $initial) {
            $state = $initial;
            foreach ($this->eval($node->source, $input, $env) as $sv) {
                foreach ($this->bindPatterns([$node->pattern], $sv, $env) as $bound) {
                    $last = null;
                    $any = false;
                    foreach ($this->eval($node->update, $state, $bound) as $u) {
                        $last = $u;
                        $any = true;
                    }
                    $state = $any ? $last : null;
                }
            }
            yield $state;
        }
    }

    private function evalForeach(ForeachNode $node, mixed $input, Environment $env): Generator
    {
        foreach ($this->eval($node->init, $input, $env) as $initial) {
            $state = $initial;
            foreach ($this->eval($node->source, $input, $env) as $sv) {
                foreach ($this->bindPatterns([$node->pattern], $sv, $env) as $bound) {
                    foreach ($this->eval($node->update, $state, $bound) as $u) {
                        $state = $u;
                        if ($node->extract !== null) {
                            yield from $this->eval($node->extract, $u, $bound);
                        } else {
                            yield $u;
                        }
                    }
                }
            }
        }
    }

    // ----- variables / labels ----------------------------------------------

    private function evalVar(VarRef $node, Environment $env): mixed
    {
        if ($node->name === '__loc__') {
            $loc = new JsonObject;
            $loc->props['file'] = '<stdin>';
            $loc->props['line'] = 1;

            return $loc;
        }

        return $env->getVar($node->name);
    }

    private function evalLabel(Label $node, mixed $input, Environment $env): Generator
    {
        $id = '#'.(++$this->labelCounter);
        $child = $env->child();
        $child->setVar('*label:'.$node->name, $id);
        try {
            yield from $this->eval($node->body, $input, $child);
        } catch (BreakException $e) {
            if ($e->label !== $id) {
                throw $e;
            }
        }
    }

    // ----- function calls --------------------------------------------------

    private function evalCall(FuncCall $node, mixed $input, Environment $env): Generator
    {
        $arity = count($node->args);
        $fn = $env->resolveFunc($node->name, $arity);
        if ($fn !== null) {
            yield from $this->invoke($fn, $node->args, $input, $env);

            return;
        }

        $builtin = $this->builtins->dispatch($this, $node->name, $node->args, $input, $env);
        if ($builtin === null) {
            throw JqException::of($node->name.'/'.$arity.' is not defined');
        }
        yield from $builtin;
    }

    /**
     * @param  list<Node>  $argNodes
     */
    public function invoke(RuntimeFunction $fn, array $argNodes, mixed $input, Environment $callerEnv): Generator
    {
        if ($fn->native !== null) {
            yield from ($fn->native)($input);

            return;
        }

        $newEnv = $fn->closure->child();
        $valueParams = [];
        foreach ($fn->params as $i => $param) {
            if (str_starts_with($param, '$')) {
                $valueParams[] = [substr($param, 1), $argNodes[$i]];
            } else {
                $newEnv->defineFunc($param, 0, RuntimeFunction::user([], $argNodes[$i], $callerEnv));
            }
        }

        yield from $this->bindValueParams($valueParams, 0, $fn->body, $input, $newEnv, $callerEnv);
    }

    /**
     * @param  list<array{0: string, 1: Node}>  $valueParams
     */
    private function bindValueParams(array $valueParams, int $i, Node $body, mixed $input, Environment $newEnv, Environment $callerEnv): Generator
    {
        if ($i >= count($valueParams)) {
            yield from $this->eval($body, $input, $newEnv);

            return;
        }
        [$name, $node] = $valueParams[$i];
        foreach ($this->eval($node, $input, $callerEnv) as $v) {
            $newEnv->setVar($name, $v);
            yield from $this->bindValueParams($valueParams, $i + 1, $body, $input, $newEnv, $callerEnv);
        }
    }

    // ----- assignment / update ---------------------------------------------

    private function evalAssignment(Assignment $node, mixed $input, Environment $env): Generator
    {
        $op = $node->op;

        if ($op === '=') {
            foreach ($this->eval($node->value, $input, $env) as $v) {
                $acc = $input;
                foreach ($this->evalPaths($node->path, $input, $env) as $path) {
                    $acc = Paths::set($acc, $path, $v);
                }
                yield $acc;
            }

            return;
        }

        if ($op === '|=') {
            $acc = $input;
            foreach ($this->evalPaths($node->path, $input, $env) as $path) {
                $current = Paths::get($acc, $path);
                $updated = $this->first($node->value, $current, $env);
                $acc = Paths::set($acc, $path, $updated);
            }
            yield $acc;

            return;
        }

        // arithmetic update operators: a OP= b  ≡  a |= (. OP b-against-root)
        $arithOp = rtrim($op, '=');
        foreach ($this->eval($node->value, $input, $env) as $rhs) {
            $acc = $input;
            foreach ($this->evalPaths($node->path, $input, $env) as $path) {
                $current = Paths::get($acc, $path);
                if ($op === '//=') {
                    $new = Values::truthy($current) ? $current : $rhs;
                } else {
                    $new = Operators::apply($arithOp, $current, $rhs);
                }
                $acc = Paths::set($acc, $path, $new);
            }
            yield $acc;
        }
    }

    /**
     * Evaluates a filter in "path mode", yielding the navigated paths.
     *
     * @return Generator<list<mixed>>
     */
    public function evalPaths(Node $node, mixed $input, Environment $env): Generator
    {
        switch ($node::class) {
            case Identity::class:
                yield [];

                return;

            case Recurse::class:
                yield from $this->recursePaths($input, []);

                return;

            case Field::class:
                yield [$node->name];

                return;

            case Index::class:
                foreach ($this->eval($node->index, $input, $env) as $key) {
                    yield [$key];
                }

                return;

            case Iterate::class:
                if (is_array($input)) {
                    foreach (array_keys($input) as $i) {
                        yield [$i];
                    }
                } elseif ($input instanceof JsonObject) {
                    foreach ($input->keys() as $k) {
                        yield [$k];
                    }
                } elseif ($input !== null) {
                    throw JqException::of('Cannot iterate over '.Values::typeName($input));
                }

                return;

            case Slice::class:
                $from = $node->from === null ? null : $this->first($node->from, $input, $env);
                $to = $node->to === null ? null : $this->first($node->to, $input, $env);
                yield [['start' => $from, 'end' => $to]];

                return;

            case Pipe::class:
                foreach ($this->evalPaths($node->left, $input, $env) as $lp) {
                    $sub = Paths::get($input, $lp);
                    foreach ($this->evalPaths($node->right, $sub, $env) as $rp) {
                        yield array_merge($lp, $rp);
                    }
                }

                return;

            case Comma::class:
                yield from $this->evalPaths($node->left, $input, $env);
                yield from $this->evalPaths($node->right, $input, $env);

                return;

            case TryCatch::class:
                try {
                    $paths = iterator_to_array($this->evalPaths($node->body, $input, $env), false);
                } catch (JqException) {
                    return;
                }
                yield from $paths;

                return;

            case IfThenElse::class:
                foreach ($this->eval($node->cond, $input, $env) as $c) {
                    if (Values::truthy($c)) {
                        yield from $this->evalPaths($node->then, $input, $env);
                    } elseif ($node->else !== null) {
                        yield from $this->evalPaths($node->else, $input, $env);
                    } else {
                        yield [];
                    }
                }

                return;

            case FuncCall::class:
                yield from $this->evalCallPaths($node, $input, $env);

                return;

            default:
                throw JqException::of('Invalid path expression near '.$node::class);
        }
    }

    private function evalCallPaths(FuncCall $node, mixed $input, Environment $env): Generator
    {
        // user-defined function: expand its body in path mode
        $fn = $env->resolveFunc($node->name, count($node->args));
        if ($fn !== null && $fn->body !== null) {
            $newEnv = $fn->closure->child();
            foreach ($fn->params as $i => $param) {
                if (str_starts_with($param, '$')) {
                    $newEnv->setVar(substr($param, 1), $this->first($node->args[$i], $input, $env));
                } else {
                    $newEnv->defineFunc($param, 0, RuntimeFunction::user([], $node->args[$i], $env));
                }
            }
            yield from $this->evalPaths($fn->body, $input, $newEnv);

            return;
        }

        switch ($node->name) {
            case 'select':
                if (count($node->args) === 1) {
                    foreach ($this->eval($node->args[0], $input, $env) as $c) {
                        if (Values::truthy($c)) {
                            yield [];
                        }
                    }
                }

                return;

            case 'empty':
                return;

            case 'getpath':
                foreach ($this->eval($node->args[0], $input, $env) as $p) {
                    yield is_array($p) ? array_values($p) : [];
                }

                return;

            case 'first':
                if (count($node->args) === 1) {
                    foreach ($this->evalPaths($node->args[0], $input, $env) as $p) {
                        yield $p;

                        return;
                    }
                }

                return;

            case 'last':
                if (count($node->args) === 1) {
                    $lastP = null;
                    $has = false;
                    foreach ($this->evalPaths($node->args[0], $input, $env) as $p) {
                        $lastP = $p;
                        $has = true;
                    }
                    if ($has) {
                        yield $lastP;
                    }
                }

                return;

            default:
                throw JqException::of('Invalid path expression with result from '.$node->name);
        }
    }

    /**
     * @param  list<mixed>  $prefix
     */
    private function recursePaths(mixed $value, array $prefix): Generator
    {
        yield $prefix;
        if (is_array($value)) {
            foreach ($value as $i => $v) {
                yield from $this->recursePaths($v, array_merge($prefix, [$i]));
            }
        } elseif ($value instanceof JsonObject) {
            foreach ($value->props as $k => $v) {
                yield from $this->recursePaths($v, array_merge($prefix, [(string) $k]));
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Jq;

use App\Jq\Cli\CliConfig;
use App\Jq\Io\JsonStreamReader;
use App\Jq\Io\OutputEncoder;
use App\Jq\Parser\Ast\Node;
use App\Jq\Parser\Parser;
use App\Jq\Runtime\BreakException;
use App\Jq\Runtime\Environment;
use App\Jq\Runtime\Interpreter;
use App\Jq\Runtime\JqException;
use App\Jq\Runtime\JsonObject;
use App\Jq\Runtime\Prelude;
use App\Jq\Runtime\Values;
use Closure;

/**
 * Drives a full jq run: parse program, prepare the environment, stream input
 * values through the interpreter, encode output, and compute the exit code.
 */
final class Engine
{
    public const EXIT_OK = 0;

    public const EXIT_FALSE_NULL = 1;

    public const EXIT_USAGE = 2;

    public const EXIT_COMPILE = 3;

    public const EXIT_NO_OUTPUT = 4;

    public const EXIT_IO = 5;

    private Interpreter $interp;

    private Environment $base;

    /**
     * @param  resource  $out
     * @param  resource  $err
     */
    public function __construct(private $out, private $err) {}

    public function run(CliConfig $cfg, string $programSource, string $inputText): int
    {
        $this->interp = new Interpreter;
        $this->base = new Environment;

        $preludeAst = Parser::fromSource(Prelude::SOURCE)->parseProgram();
        $this->interp->installDefs($preludeAst, $this->base);

        try {
            $programAst = Parser::fromSource($programSource)->parseProgram();
        } catch (JqParseException $e) {
            fwrite($this->err, $e->getMessage()."\n");

            return self::EXIT_COMPILE;
        }

        $env = $this->prepareEnv($cfg);

        $reader = new JsonStreamReader;
        $iterator = $reader->inputIterator($cfg, $inputText);
        $puller = $this->makePuller($iterator);
        $this->interp->setInputPuller($puller);

        $state = (object) ['hadOutput' => false, 'hadError' => false, 'last' => null];

        if ($cfg->nullInput) {
            $this->process($programAst, null, $env, $state);
        } else {
            while (true) {
                try {
                    [$has, $value] = $puller();
                } catch (JqException $e) {
                    fwrite($this->err, 'jq: error: '.$e->getMessage()."\n");

                    return self::EXIT_IO;
                }
                if (! $has) {
                    break;
                }
                $this->process($programAst, $value, $env, $state);
            }
        }

        if ($cfg->exitStatus && ! $state->hadError) {
            if (! $state->hadOutput) {
                return self::EXIT_NO_OUTPUT;
            }

            return Values::truthy($state->last) ? self::EXIT_OK : self::EXIT_FALSE_NULL;
        }

        return $state->hadError ? self::EXIT_IO : self::EXIT_OK;
    }

    private function process(Node $program, mixed $input, Environment $env, object $state): void
    {
        $encoder = new OutputEncoder($this->currentConfig);
        try {
            foreach ($this->interp->eval($program, $input, $env) as $value) {
                $state->hadOutput = true;
                $state->last = $value;
                fwrite($this->out, $encoder->encode($value).$encoder->separator());
            }
        } catch (JqException $e) {
            $state->hadError = true;
            fwrite($this->err, 'jq: error: '.$e->getMessage()."\n");
        } catch (BreakException) {
            $state->hadError = true;
            fwrite($this->err, "jq: error: break\n");
        }
    }

    private CliConfig $currentConfig;

    private function prepareEnv(CliConfig $cfg): Environment
    {
        $this->currentConfig = $cfg;
        $env = $this->base->child();

        $env->setVar('ENV', $this->envObject());
        $env->setVar('__prog_args', $cfg->positional);

        $named = new JsonObject;
        foreach ($cfg->namedArgs as $name => $value) {
            $env->setVar($name, $value);
            $named->props[$name] = $value;
        }

        $args = new JsonObject;
        $args->props['positional'] = array_values($cfg->positional);
        $args->props['named'] = $named;
        $env->setVar('ARGS', $args);

        return $env;
    }

    private function envObject(): JsonObject
    {
        $obj = new JsonObject;
        foreach (getenv() as $k => $v) {
            $obj->props[(string) $k] = (string) $v;
        }

        return $obj;
    }

    /**
     * @param  \Generator<mixed>  $iterator
     * @return Closure(): array{0: bool, 1: mixed}
     */
    private function makePuller(\Generator $iterator): Closure
    {
        return function () use ($iterator): array {
            if (! $iterator->valid()) {
                return [false, null];
            }
            $value = $iterator->current();
            $iterator->next();

            return [true, $value];
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Jq\Parser;

use App\Jq\JqParseException;
use App\Jq\Lexer\Lexer;
use App\Jq\Lexer\Token;
use App\Jq\Lexer\TokenType;
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

/**
 * Recursive-descent / precedence-climbing parser for the jq language.
 */
final class Parser
{
    private int $pos = 0;

    /**
     * @param  list<Token>  $tokens
     */
    public function __construct(private readonly array $tokens) {}

    public static function fromSource(string $source): self
    {
        return new self((new Lexer($source))->tokenize());
    }

    public function parseProgram(): Node
    {
        if ($this->check(TokenType::EOF)) {
            return new Identity;
        }
        $node = $this->parsePipe();
        if (! $this->check(TokenType::EOF)) {
            $t = $this->peek();
            throw new JqParseException("unexpected token '{$t->type->value}'", $t->line, $t->col);
        }

        return $node;
    }

    // ----- precedence levels -----------------------------------------------

    private function parsePipe(): Node
    {
        if ($this->check(TokenType::DEF)) {
            return $this->parseFuncDef();
        }

        $left = $this->parseComma();

        if ($this->check(TokenType::AS)) {
            $this->advance();
            $patterns = $this->parsePatternAlternatives();
            $this->expect(TokenType::PIPE);
            $body = $this->parsePipe();

            return new Binding($left, $patterns, $body);
        }

        if ($this->check(TokenType::PIPE)) {
            $this->advance();
            $right = $this->parsePipe();

            return new Pipe($left, $right);
        }

        return $left;
    }

    private function parseComma(): Node
    {
        $left = $this->parseAlternative();
        while ($this->check(TokenType::COMMA)) {
            $this->advance();
            $right = $this->parseAlternative();
            $left = new Comma($left, $right);
        }

        return $left;
    }

    private function parseAlternative(): Node
    {
        $left = $this->parseAssignment();
        if ($this->check(TokenType::ALT)) {
            $this->advance();
            $right = $this->parseAlternative();

            return new Alternative($left, $right);
        }

        return $left;
    }

    private function parseAssignment(): Node
    {
        $left = $this->parseOr();

        $op = match ($this->peek()->type) {
            TokenType::ASSIGN => '=',
            TokenType::PIPE_ASSIGN => '|=',
            TokenType::PLUS_ASSIGN => '+=',
            TokenType::MINUS_ASSIGN => '-=',
            TokenType::STAR_ASSIGN => '*=',
            TokenType::SLASH_ASSIGN => '/=',
            TokenType::PERCENT_ASSIGN => '%=',
            TokenType::ALT_ASSIGN => '//=',
            default => null,
        };

        if ($op !== null) {
            $this->advance();
            $right = $this->parseAlternative();

            return new Assignment($op, $left, $right);
        }

        return $left;
    }

    private function parseOr(): Node
    {
        $left = $this->parseAnd();
        while ($this->check(TokenType::OR)) {
            $this->advance();
            $right = $this->parseAnd();
            $left = new BinaryOp('or', $left, $right);
        }

        return $left;
    }

    private function parseAnd(): Node
    {
        $left = $this->parseComparison();
        while ($this->check(TokenType::AND)) {
            $this->advance();
            $right = $this->parseComparison();
            $left = new BinaryOp('and', $left, $right);
        }

        return $left;
    }

    private function parseComparison(): Node
    {
        $left = $this->parseAdditive();
        while (true) {
            $op = match ($this->peek()->type) {
                TokenType::EQ => '==', TokenType::NE => '!=',
                TokenType::LT => '<', TokenType::LE => '<=',
                TokenType::GT => '>', TokenType::GE => '>=',
                default => null,
            };
            if ($op === null) {
                break;
            }
            $this->advance();
            $right = $this->parseAdditive();
            $left = new BinaryOp($op, $left, $right);
        }

        return $left;
    }

    private function parseAdditive(): Node
    {
        $left = $this->parseMultiplicative();
        while ($this->check(TokenType::PLUS) || $this->check(TokenType::MINUS)) {
            $op = $this->check(TokenType::PLUS) ? '+' : '-';
            $this->advance();
            $right = $this->parseMultiplicative();
            $left = new BinaryOp($op, $left, $right);
        }

        return $left;
    }

    private function parseMultiplicative(): Node
    {
        $left = $this->parseUnary();
        while ($this->check(TokenType::STAR) || $this->check(TokenType::SLASH) || $this->check(TokenType::PERCENT)) {
            $op = match ($this->peek()->type) {
                TokenType::STAR => '*',
                TokenType::SLASH => '/',
                default => '%',
            };
            $this->advance();
            $right = $this->parseUnary();
            $left = new BinaryOp($op, $left, $right);
        }

        return $left;
    }

    private function parseUnary(): Node
    {
        if ($this->check(TokenType::MINUS)) {
            $this->advance();

            return new UnaryNeg($this->parsePostfix());
        }

        return $this->parsePostfix();
    }

    private function parsePostfix(): Node
    {
        $node = $this->parsePrimary();

        while (true) {
            $t = $this->peek();

            if ($t->is(TokenType::FIELD)) {
                $this->advance();
                $node = new Pipe($node, new Field((string) $t->value));

                continue;
            }

            if ($t->is(TokenType::DOT) && $this->peek(1)->is(TokenType::LBRACKET)) {
                $this->advance(); // dot
                $node = new Pipe($node, $this->parseBracketSuffix());

                continue;
            }

            if ($t->is(TokenType::DOT) && $this->peek(1)->is(TokenType::STRING)) {
                $this->advance(); // dot
                $s = $this->advance();
                $node = new Pipe($node, new Index($this->stringExpr($s->value)));

                continue;
            }

            if ($t->is(TokenType::LBRACKET)) {
                $node = new Pipe($node, $this->parseBracketSuffix());

                continue;
            }

            if ($t->is(TokenType::QUESTION)) {
                $this->advance();
                $node = new TryCatch($node, null);

                continue;
            }

            break;
        }

        return $node;
    }

    private function parseBracketSuffix(): Node
    {
        $this->expect(TokenType::LBRACKET);

        if ($this->check(TokenType::RBRACKET)) {
            $this->advance();

            return new Iterate;
        }

        if ($this->check(TokenType::COLON)) {
            $this->advance();
            $to = $this->parsePipe();
            $this->expect(TokenType::RBRACKET);

            return new Slice(null, $to);
        }

        $first = $this->parsePipe();

        if ($this->check(TokenType::COLON)) {
            $this->advance();
            $to = $this->check(TokenType::RBRACKET) ? null : $this->parsePipe();
            $this->expect(TokenType::RBRACKET);

            return new Slice($first, $to);
        }

        $this->expect(TokenType::RBRACKET);

        return new Index($first);
    }

    private function parsePrimary(): Node
    {
        $t = $this->peek();

        switch ($t->type) {
            case TokenType::NUMBER:
                $this->advance();

                return new Literal($t->value);

            case TokenType::STRING:
                $this->advance();

                return $this->stringExpr($t->value);

            case TokenType::FORMAT:
                $this->advance();
                if ($this->check(TokenType::STRING)) {
                    $s = $this->advance();

                    return $this->stringExpr($s->value, (string) $t->value);
                }

                return new Format((string) $t->value);

            case TokenType::VAR:
                $this->advance();

                return new VarRef((string) $t->value);

            case TokenType::FIELD:
                $this->advance();

                return new Field((string) $t->value);

            case TokenType::DOT:
                $this->advance();
                if ($this->check(TokenType::STRING)) {
                    $s = $this->advance();

                    return new Index($this->stringExpr($s->value));
                }

                return new Identity;

            case TokenType::DOTDOT:
                $this->advance();

                return new Recurse;

            case TokenType::LPAREN:
                $this->advance();
                $e = $this->parsePipe();
                $this->expect(TokenType::RPAREN);

                return $e;

            case TokenType::LBRACKET:
                $this->advance();
                if ($this->check(TokenType::RBRACKET)) {
                    $this->advance();

                    return new ArrayConstruct(null);
                }
                $body = $this->parsePipe();
                $this->expect(TokenType::RBRACKET);

                return new ArrayConstruct($body);

            case TokenType::LBRACE:
                return $this->parseObject();

            case TokenType::IF:
                return $this->parseIf();

            case TokenType::TRY:
                return $this->parseTry();

            case TokenType::REDUCE:
                return $this->parseReduce();

            case TokenType::FOREACH:
                return $this->parseForeach();

            case TokenType::LABEL:
                return $this->parseLabel();

            case TokenType::DEF:
                return $this->parseFuncDef();

            case TokenType::IDENT:
                return $this->parseIdentPrimary($t);

            default:
                throw new JqParseException("unexpected token '{$t->type->value}'", $t->line, $t->col);
        }
    }

    private function parseIdentPrimary(Token $t): Node
    {
        $name = (string) $t->value;
        $this->advance();

        if ($name === 'true') {
            return new Literal(true);
        }
        if ($name === 'false') {
            return new Literal(false);
        }
        if ($name === 'null') {
            return new Literal(null);
        }
        if ($name === 'break') {
            $var = $this->expect(TokenType::VAR);

            return new BreakNode((string) $var->value);
        }

        if ($this->check(TokenType::LPAREN)) {
            $args = $this->parseArgs();

            return new FuncCall($name, $args);
        }

        return new FuncCall($name, []);
    }

    /**
     * @return list<Node>
     */
    private function parseArgs(): array
    {
        $this->expect(TokenType::LPAREN);
        $args = [$this->parsePipe()];
        while ($this->check(TokenType::SEMICOLON)) {
            $this->advance();
            $args[] = $this->parsePipe();
        }
        $this->expect(TokenType::RPAREN);

        return $args;
    }

    private function parseObject(): Node
    {
        $this->expect(TokenType::LBRACE);
        $entries = [];

        if ($this->check(TokenType::RBRACE)) {
            $this->advance();

            return new ObjectConstruct([]);
        }

        while (true) {
            $entries[] = $this->parseObjectEntry();
            if ($this->check(TokenType::COMMA)) {
                $this->advance();
                if ($this->check(TokenType::RBRACE)) {
                    break;
                }

                continue;
            }
            break;
        }

        $this->expect(TokenType::RBRACE);

        return new ObjectConstruct($entries);
    }

    /**
     * @return array{0: Node, 1: Node}
     */
    private function parseObjectEntry(): array
    {
        $t = $this->peek();

        // $var shorthand / key
        if ($t->is(TokenType::VAR)) {
            $this->advance();
            $name = (string) $t->value;
            if ($this->check(TokenType::COLON)) {
                $this->advance();

                return [new Literal($name), $this->parseObjectValue()];
            }

            return [new Literal($name), new VarRef($name)];
        }

        // (expr): value
        if ($t->is(TokenType::LPAREN)) {
            $this->advance();
            $key = $this->parsePipe();
            $this->expect(TokenType::RPAREN);
            $this->expect(TokenType::COLON);

            return [$key, $this->parseObjectValue()];
        }

        // "string" key
        if ($t->is(TokenType::STRING)) {
            $this->advance();
            $key = $this->stringExpr($t->value);
            if ($this->check(TokenType::COLON)) {
                $this->advance();

                return [$key, $this->parseObjectValue()];
            }

            return [$key, new Index($key)];
        }

        // @format string key
        if ($t->is(TokenType::FORMAT)) {
            $this->advance();
            $s = $this->expect(TokenType::STRING);
            $key = $this->stringExpr($s->value, (string) $t->value);
            $this->expect(TokenType::COLON);

            return [$key, $this->parseObjectValue()];
        }

        // bare identifier (or keyword used as key)
        $name = $this->identifierLikeKey($t);
        $this->advance();
        if ($this->check(TokenType::COLON)) {
            $this->advance();

            return [new Literal($name), $this->parseObjectValue()];
        }

        return [new Literal($name), new Field($name)];
    }

    private function identifierLikeKey(Token $t): string
    {
        if ($t->is(TokenType::IDENT)) {
            return (string) $t->value;
        }
        // allow keywords as object keys
        if (is_string($t->value) && $t->value !== '') {
            return (string) $t->value;
        }
        throw new JqParseException("invalid object key '{$t->type->value}'", $t->line, $t->col);
    }

    private function parseObjectValue(): Node
    {
        $left = $this->parseAlternative();
        while ($this->check(TokenType::PIPE)) {
            $this->advance();
            $right = $this->parseAlternative();
            $left = new Pipe($left, $right);
        }

        return $left;
    }

    private function parseIf(): Node
    {
        $this->expect(TokenType::IF);
        $cond = $this->parsePipe();
        $this->expect(TokenType::THEN);
        $then = $this->parsePipe();

        $elifs = [];
        while ($this->check(TokenType::ELIF)) {
            $this->advance();
            $c = $this->parsePipe();
            $this->expect(TokenType::THEN);
            $b = $this->parsePipe();
            $elifs[] = [$c, $b];
        }

        $else = null;
        if ($this->check(TokenType::ELSE)) {
            $this->advance();
            $else = $this->parsePipe();
        }

        $this->expect(TokenType::END);

        return new IfThenElse($cond, $then, $elifs, $else);
    }

    private function parseTry(): Node
    {
        $this->expect(TokenType::TRY);
        $body = $this->parsePostfix();
        $handler = null;
        if ($this->check(TokenType::CATCH)) {
            $this->advance();
            $handler = $this->parsePostfix();
        }

        return new TryCatch($body, $handler);
    }

    private function parseReduce(): Node
    {
        $this->expect(TokenType::REDUCE);
        $source = $this->parsePostfix();
        $this->expect(TokenType::AS);
        $pattern = $this->parsePattern();
        $this->expect(TokenType::LPAREN);
        $init = $this->parsePipe();
        $this->expect(TokenType::SEMICOLON);
        $update = $this->parsePipe();
        $this->expect(TokenType::RPAREN);

        return new Reduce($source, $pattern, $init, $update);
    }

    private function parseForeach(): Node
    {
        $this->expect(TokenType::FOREACH);
        $source = $this->parsePostfix();
        $this->expect(TokenType::AS);
        $pattern = $this->parsePattern();
        $this->expect(TokenType::LPAREN);
        $init = $this->parsePipe();
        $this->expect(TokenType::SEMICOLON);
        $update = $this->parsePipe();
        $extract = null;
        if ($this->check(TokenType::SEMICOLON)) {
            $this->advance();
            $extract = $this->parsePipe();
        }
        $this->expect(TokenType::RPAREN);

        return new ForeachNode($source, $pattern, $init, $update, $extract);
    }

    private function parseLabel(): Node
    {
        $this->expect(TokenType::LABEL);
        $var = $this->expect(TokenType::VAR);
        $this->expect(TokenType::PIPE);
        $body = $this->parsePipe();

        return new Label((string) $var->value, $body);
    }

    private function parseFuncDef(): Node
    {
        $this->expect(TokenType::DEF);
        $nameTok = $this->expect(TokenType::IDENT);
        $name = (string) $nameTok->value;

        $params = [];
        if ($this->check(TokenType::LPAREN)) {
            $this->advance();
            $params[] = $this->parseParam();
            while ($this->check(TokenType::SEMICOLON)) {
                $this->advance();
                $params[] = $this->parseParam();
            }
            $this->expect(TokenType::RPAREN);
        }

        $this->expect(TokenType::COLON);
        $body = $this->parsePipe();
        $this->expect(TokenType::SEMICOLON);

        $rest = $this->canStartExpr() ? $this->parsePipe() : new Identity;

        return new FuncDef($name, $params, $body, $rest);
    }

    private function parseParam(): string
    {
        if ($this->check(TokenType::VAR)) {
            $t = $this->advance();

            return '$'.((string) $t->value);
        }
        $t = $this->expect(TokenType::IDENT);

        return (string) $t->value;
    }

    // ----- patterns --------------------------------------------------------

    /**
     * @return list<Pattern>
     */
    private function parsePatternAlternatives(): array
    {
        $patterns = [$this->parsePattern()];
        while ($this->check(TokenType::QUESTION_SLASHSLASH)) {
            $this->advance();
            $patterns[] = $this->parsePattern();
        }

        return $patterns;
    }

    private function parsePattern(): Pattern
    {
        $t = $this->peek();

        if ($t->is(TokenType::VAR)) {
            $this->advance();

            return new VarPattern((string) $t->value);
        }

        if ($t->is(TokenType::LBRACKET)) {
            $this->advance();
            $elements = [];
            if (! $this->check(TokenType::RBRACKET)) {
                $elements[] = $this->parsePattern();
                while ($this->check(TokenType::COMMA)) {
                    $this->advance();
                    $elements[] = $this->parsePattern();
                }
            }
            $this->expect(TokenType::RBRACKET);

            return new ArrayPattern($elements);
        }

        if ($t->is(TokenType::LBRACE)) {
            return $this->parseObjectPattern();
        }

        throw new JqParseException("invalid destructuring pattern '{$t->type->value}'", $t->line, $t->col);
    }

    private function parseObjectPattern(): Pattern
    {
        $this->expect(TokenType::LBRACE);
        $entries = [];

        while (true) {
            $t = $this->peek();

            if ($t->is(TokenType::VAR)) {
                $this->advance();
                $name = (string) $t->value;
                if ($this->check(TokenType::COLON)) {
                    $this->advance();
                    $value = $this->parsePattern();
                    $entries[] = ['key' => ['ident' => $name], 'value' => $value];
                } else {
                    $entries[] = ['key' => ['ident' => $name], 'value' => new VarPattern($name)];
                }
            } elseif ($t->is(TokenType::IDENT)) {
                $this->advance();
                $this->expect(TokenType::COLON);
                $value = $this->parsePattern();
                $entries[] = ['key' => ['ident' => (string) $t->value], 'value' => $value];
            } elseif ($t->is(TokenType::STRING)) {
                $this->advance();
                $key = $this->stringExpr($t->value);
                $this->expect(TokenType::COLON);
                $value = $this->parsePattern();
                $entries[] = ['key' => ['string' => $key], 'value' => $value];
            } elseif ($t->is(TokenType::LPAREN)) {
                $this->advance();
                $expr = $this->parsePipe();
                $this->expect(TokenType::RPAREN);
                $this->expect(TokenType::COLON);
                $value = $this->parsePattern();
                $entries[] = ['key' => ['expr' => $expr], 'value' => $value];
            } else {
                throw new JqParseException("invalid object pattern key '{$t->type->value}'", $t->line, $t->col);
            }

            if ($this->check(TokenType::COMMA)) {
                $this->advance();

                continue;
            }
            break;
        }

        $this->expect(TokenType::RBRACE);

        return new ObjectPattern($entries);
    }

    // ----- string building -------------------------------------------------

    /**
     * Builds a Literal (plain string) or StringInterp node from lexer segments.
     *
     * @param  list<string|array{interp: string}>  $segments
     */
    private function stringExpr(mixed $segments, ?string $format = null): Node
    {
        $hasInterp = false;
        foreach ($segments as $seg) {
            if (is_array($seg)) {
                $hasInterp = true;
                break;
            }
        }

        if (! $hasInterp && $format === null) {
            $text = '';
            foreach ($segments as $seg) {
                $text .= $seg;
            }

            return new Literal($text);
        }

        $parts = [];
        foreach ($segments as $seg) {
            if (is_array($seg)) {
                $parts[] = self::fromSource($seg['interp'])->parseProgram();
            } else {
                $parts[] = (string) $seg;
            }
        }

        return new StringInterp($parts, $format);
    }

    // ----- token cursor ----------------------------------------------------

    private function peek(int $offset = 0): Token
    {
        $i = $this->pos + $offset;

        return $this->tokens[$i] ?? $this->tokens[count($this->tokens) - 1];
    }

    private function check(TokenType $type): bool
    {
        return $this->peek()->type === $type;
    }

    private function advance(): Token
    {
        $t = $this->tokens[$this->pos] ?? $this->tokens[count($this->tokens) - 1];
        if ($this->pos < count($this->tokens) - 1) {
            $this->pos++;
        }

        return $t;
    }

    private function expect(TokenType $type): Token
    {
        if (! $this->check($type)) {
            $t = $this->peek();
            throw new JqParseException("expected {$type->value} but found '{$t->type->value}'", $t->line, $t->col);
        }

        return $this->advance();
    }

    private function canStartExpr(): bool
    {
        return ! in_array($this->peek()->type, [
            TokenType::EOF,
            TokenType::RPAREN,
            TokenType::RBRACKET,
            TokenType::RBRACE,
            TokenType::SEMICOLON,
        ], true);
    }
}

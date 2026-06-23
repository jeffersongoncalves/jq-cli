<?php

declare(strict_types=1);

namespace App\Jq\Lexer;

use App\Jq\JqParseException;

/**
 * Tokenizer for the jq language. Produces a flat list of tokens; string
 * interpolation segments are captured so the parser can recursively parse
 * the embedded expressions.
 */
final class Lexer
{
    private int $pos = 0;

    private int $line = 1;

    private int $col = 1;

    private readonly int $len;

    public function __construct(private readonly string $src)
    {
        $this->len = strlen($src);
    }

    /**
     * @return list<Token>
     */
    public function tokenize(): array
    {
        $tokens = [];

        while (true) {
            $this->skipTrivia();
            if ($this->pos >= $this->len) {
                break;
            }

            $tokens[] = $this->nextToken();
        }

        $tokens[] = new Token(TokenType::EOF, null, $this->line, $this->col);

        return $tokens;
    }

    private function nextToken(): Token
    {
        $line = $this->line;
        $col = $this->col;
        $c = $this->src[$this->pos];

        // numbers
        if (ctype_digit($c)) {
            return $this->number($line, $col);
        }

        // identifiers / keywords
        if (ctype_alpha($c) || $c === '_') {
            return $this->identifier($line, $col);
        }

        // variables
        if ($c === '$') {
            return $this->variable($line, $col);
        }

        // formats @base64 ...
        if ($c === '@') {
            return $this->format($line, $col);
        }

        // strings
        if ($c === '"') {
            return $this->string($line, $col);
        }

        return $this->operator($line, $col);
    }

    private function number(int $line, int $col): Token
    {
        $start = $this->pos;
        while ($this->pos < $this->len && ctype_digit($this->src[$this->pos])) {
            $this->advance();
        }
        if ($this->peek() === '.' && ctype_digit((string) $this->peek(1))) {
            $this->advance(); // .
            while ($this->pos < $this->len && ctype_digit($this->src[$this->pos])) {
                $this->advance();
            }
        }
        if (($this->peek() === 'e' || $this->peek() === 'E')) {
            $save = $this->pos;
            $this->advance();
            if ($this->peek() === '+' || $this->peek() === '-') {
                $this->advance();
            }
            if (ctype_digit((string) $this->peek())) {
                while ($this->pos < $this->len && ctype_digit($this->src[$this->pos])) {
                    $this->advance();
                }
            } else {
                $this->pos = $save; // not an exponent
            }
        }

        $raw = substr($this->src, $start, $this->pos - $start);
        $value = $raw + 0; // int|float

        return new Token(TokenType::NUMBER, $value, $line, $col);
    }

    private function identifier(int $line, int $col): Token
    {
        $start = $this->pos;
        while ($this->pos < $this->len) {
            $ch = $this->src[$this->pos];
            if (ctype_alnum($ch) || $ch === '_') {
                $this->advance();
            } elseif ($ch === ':' && ($this->peek(1) === ':')) {
                // module::name
                $this->advance();
                $this->advance();
            } else {
                break;
            }
        }
        $word = substr($this->src, $start, $this->pos - $start);

        $kw = TokenType::keyword($word);
        if ($kw !== null) {
            return new Token($kw, $word, $line, $col);
        }

        return new Token(TokenType::IDENT, $word, $line, $col);
    }

    private function variable(int $line, int $col): Token
    {
        $this->advance(); // $
        // $__loc__ and friends are just $ + ident chars
        if ($this->peek() === '_' && substr($this->src, $this->pos, 7) === '__loc__') {
            for ($i = 0; $i < 7; $i++) {
                $this->advance();
            }

            return new Token(TokenType::VAR, '__loc__', $line, $col);
        }
        $start = $this->pos;
        while ($this->pos < $this->len) {
            $ch = $this->src[$this->pos];
            if (ctype_alnum($ch) || $ch === '_') {
                $this->advance();
            } else {
                break;
            }
        }
        if ($this->pos === $start) {
            throw new JqParseException("expected variable name after '$'", $line, $col);
        }
        $name = substr($this->src, $start, $this->pos - $start);

        return new Token(TokenType::VAR, $name, $line, $col);
    }

    private function format(int $line, int $col): Token
    {
        $this->advance(); // @
        $start = $this->pos;
        while ($this->pos < $this->len) {
            $ch = $this->src[$this->pos];
            if (ctype_alnum($ch) || $ch === '_') {
                $this->advance();
            } else {
                break;
            }
        }
        if ($this->pos === $start) {
            throw new JqParseException("expected format name after '@'", $line, $col);
        }
        $name = substr($this->src, $start, $this->pos - $start);

        return new Token(TokenType::FORMAT, $name, $line, $col);
    }

    /**
     * Scans a double-quoted string into segments: literal strings and
     * interpolation parts (['interp' => rawExpressionSource]).
     */
    private function string(int $line, int $col): Token
    {
        $this->advance(); // opening "
        $segments = [];
        $buffer = '';

        while (true) {
            if ($this->pos >= $this->len) {
                throw new JqParseException('unterminated string literal', $line, $col);
            }
            $ch = $this->src[$this->pos];

            if ($ch === '"') {
                $this->advance();
                break;
            }

            if ($ch === '\\') {
                $this->advance();
                $esc = $this->peek();
                if ($esc === null) {
                    throw new JqParseException('unterminated escape in string', $this->line, $this->col);
                }
                if ($esc === '(') {
                    // interpolation \( expr )
                    if ($buffer !== '') {
                        $segments[] = $buffer;
                        $buffer = '';
                    }
                    $segments[] = ['interp' => $this->scanInterpolation()];

                    continue;
                }
                $buffer .= $this->decodeEscape($esc);

                continue;
            }

            $buffer .= $ch;
            $this->advance();
        }

        if ($buffer !== '' || $segments === []) {
            $segments[] = $buffer;
        }

        return new Token(TokenType::STRING, $segments, $line, $col);
    }

    private function decodeEscape(string $esc): string
    {
        $this->advance(); // consume escape char
        switch ($esc) {
            case '"': return '"';
            case '\\': return '\\';
            case '/': return '/';
            case 'b': return "\x08";
            case 'f': return "\x0C";
            case 'n': return "\n";
            case 'r': return "\r";
            case 't': return "\t";
            case 'u':
                $hex = substr($this->src, $this->pos, 4);
                if (strlen($hex) !== 4 || ! ctype_xdigit($hex)) {
                    throw new JqParseException('invalid \\u escape', $this->line, $this->col);
                }
                for ($i = 0; $i < 4; $i++) {
                    $this->advance();
                }
                $code = hexdec($hex);
                // surrogate pair
                if ($code >= 0xD800 && $code <= 0xDBFF && substr($this->src, $this->pos, 2) === '\\u') {
                    $this->advance();
                    $this->advance();
                    $hex2 = substr($this->src, $this->pos, 4);
                    if (strlen($hex2) === 4 && ctype_xdigit($hex2)) {
                        for ($i = 0; $i < 4; $i++) {
                            $this->advance();
                        }
                        $low = hexdec($hex2);
                        $code = 0x10000 + (($code - 0xD800) << 10) + ($low - 0xDC00);
                    }
                }

                return $this->codepointToUtf8((int) $code);
            default:
                throw new JqParseException("invalid escape \\{$esc}", $this->line, $this->col);
        }
    }

    private function codepointToUtf8(int $code): string
    {
        return mb_convert_encoding(pack('N', $code), 'UTF-8', 'UTF-32BE');
    }

    /**
     * Called right after the '(' of \(. Returns the raw expression source up to
     * (but not including) the matching ')'. Consumes the ')'.
     */
    private function scanInterpolation(): string
    {
        $this->advance(); // consume '('
        $start = $this->pos;
        $depth = 1;

        while ($this->pos < $this->len) {
            $ch = $this->src[$this->pos];
            if ($ch === '"') {
                $this->skipNestedString();

                continue;
            }
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    $raw = substr($this->src, $start, $this->pos - $start);
                    $this->advance(); // consume ')'

                    return $raw;
                }
            }
            $this->advance();
        }

        throw new JqParseException('unterminated string interpolation', $this->line, $this->col);
    }

    private function skipNestedString(): void
    {
        $this->advance(); // opening quote
        while ($this->pos < $this->len) {
            $ch = $this->src[$this->pos];
            if ($ch === '\\') {
                $this->advance();
                if ($this->peek() === '(') {
                    // nested interpolation inside nested string
                    $this->scanInterpolation();

                    continue;
                }
                $this->advance();

                continue;
            }
            if ($ch === '"') {
                $this->advance();

                return;
            }
            $this->advance();
        }
        throw new JqParseException('unterminated nested string', $this->line, $this->col);
    }

    private function operator(int $line, int $col): Token
    {
        $two = substr($this->src, $this->pos, 2);
        $three = substr($this->src, $this->pos, 3);

        // 3-char
        if ($three === '?//') {
            $this->advanceBy(3);

            return new Token(TokenType::QUESTION_SLASHSLASH, '?//', $line, $col);
        }
        if ($three === '//=') {
            $this->advanceBy(3);

            return new Token(TokenType::ALT_ASSIGN, '//=', $line, $col);
        }

        // 2-char
        $map2 = [
            '..' => TokenType::DOTDOT,
            '//' => TokenType::ALT,
            '==' => TokenType::EQ,
            '!=' => TokenType::NE,
            '<=' => TokenType::LE,
            '>=' => TokenType::GE,
            '|=' => TokenType::PIPE_ASSIGN,
            '+=' => TokenType::PLUS_ASSIGN,
            '-=' => TokenType::MINUS_ASSIGN,
            '*=' => TokenType::STAR_ASSIGN,
            '/=' => TokenType::SLASH_ASSIGN,
            '%=' => TokenType::PERCENT_ASSIGN,
        ];
        if (isset($map2[$two])) {
            $this->advanceBy(2);

            return new Token($map2[$two], $two, $line, $col);
        }

        $c = $this->src[$this->pos];

        // .foo field
        if ($c === '.') {
            $next = $this->peek(1);
            if ($next !== null && (ctype_alpha($next) || $next === '_')) {
                $this->advance(); // .
                $fstart = $this->pos;
                while ($this->pos < $this->len) {
                    $ch = $this->src[$this->pos];
                    if (ctype_alnum($ch) || $ch === '_') {
                        $this->advance();
                    } else {
                        break;
                    }
                }
                $name = substr($this->src, $fstart, $this->pos - $fstart);

                return new Token(TokenType::FIELD, $name, $line, $col);
            }
        }

        $map1 = [
            '.' => TokenType::DOT,
            '[' => TokenType::LBRACKET,
            ']' => TokenType::RBRACKET,
            '{' => TokenType::LBRACE,
            '}' => TokenType::RBRACE,
            '(' => TokenType::LPAREN,
            ')' => TokenType::RPAREN,
            '|' => TokenType::PIPE,
            ',' => TokenType::COMMA,
            ':' => TokenType::COLON,
            ';' => TokenType::SEMICOLON,
            '?' => TokenType::QUESTION,
            '+' => TokenType::PLUS,
            '-' => TokenType::MINUS,
            '*' => TokenType::STAR,
            '/' => TokenType::SLASH,
            '%' => TokenType::PERCENT,
            '=' => TokenType::ASSIGN,
            '<' => TokenType::LT,
            '>' => TokenType::GT,
        ];
        if (isset($map1[$c])) {
            $this->advance();

            return new Token($map1[$c], $c, $line, $col);
        }

        throw new JqParseException("unexpected character '{$c}'", $line, $col);
    }

    private function skipTrivia(): void
    {
        while ($this->pos < $this->len) {
            $c = $this->src[$this->pos];
            if ($c === ' ' || $c === "\t" || $c === "\r" || $c === "\n") {
                $this->advance();

                continue;
            }
            if ($c === '#') {
                while ($this->pos < $this->len && $this->src[$this->pos] !== "\n") {
                    $this->advance();
                }

                continue;
            }
            break;
        }
    }

    private function peek(int $offset = 0): ?string
    {
        $i = $this->pos + $offset;

        return $i < $this->len ? $this->src[$i] : null;
    }

    private function advance(): void
    {
        if ($this->pos < $this->len) {
            if ($this->src[$this->pos] === "\n") {
                $this->line++;
                $this->col = 1;
            } else {
                $this->col++;
            }
            $this->pos++;
        }
    }

    private function advanceBy(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $this->advance();
        }
    }
}

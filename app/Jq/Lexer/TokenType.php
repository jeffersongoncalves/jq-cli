<?php

declare(strict_types=1);

namespace App\Jq\Lexer;

enum TokenType: string
{
    case NUMBER = 'NUMBER';
    case STRING = 'STRING';        // value = list of segments (literal string | ['interp' => rawExpr])
    case FORMAT = 'FORMAT';        // @base64 etc. (value without @)
    case IDENT = 'IDENT';          // function / builtin / object key
    case FIELD = 'FIELD';          // .foo  (value = foo)
    case VAR = 'VAR';              // $name (value = name)

    case DOT = 'DOT';              // .
    case DOTDOT = 'DOTDOT';        // ..

    case LBRACKET = 'LBRACKET';
    case RBRACKET = 'RBRACKET';
    case LBRACE = 'LBRACE';
    case RBRACE = 'RBRACE';
    case LPAREN = 'LPAREN';
    case RPAREN = 'RPAREN';

    case PIPE = 'PIPE';            // |
    case COMMA = 'COMMA';          // ,
    case COLON = 'COLON';          // :
    case SEMICOLON = 'SEMICOLON';  // ;

    case QUESTION = 'QUESTION';                 // ?
    case QUESTION_SLASHSLASH = 'QUESTION_SLASHSLASH'; // ?//

    case PLUS = 'PLUS';
    case MINUS = 'MINUS';
    case STAR = 'STAR';
    case SLASH = 'SLASH';
    case PERCENT = 'PERCENT';

    case EQ = 'EQ';   // ==
    case NE = 'NE';   // !=
    case LT = 'LT';
    case LE = 'LE';
    case GT = 'GT';
    case GE = 'GE';

    case ASSIGN = 'ASSIGN';        // =
    case PIPE_ASSIGN = 'PIPE_ASSIGN';   // |=
    case PLUS_ASSIGN = 'PLUS_ASSIGN';   // +=
    case MINUS_ASSIGN = 'MINUS_ASSIGN'; // -=
    case STAR_ASSIGN = 'STAR_ASSIGN';   // *=
    case SLASH_ASSIGN = 'SLASH_ASSIGN'; // /=
    case PERCENT_ASSIGN = 'PERCENT_ASSIGN'; // %=
    case ALT_ASSIGN = 'ALT_ASSIGN';     // //=

    case ALT = 'ALT';  // //  (alternative / default)

    // keywords
    case DEF = 'DEF';
    case IF = 'IF';
    case THEN = 'THEN';
    case ELIF = 'ELIF';
    case ELSE = 'ELSE';
    case END = 'END';
    case AS = 'AS';
    case REDUCE = 'REDUCE';
    case FOREACH = 'FOREACH';
    case TRY = 'TRY';
    case CATCH = 'CATCH';
    case LABEL = 'LABEL';
    case AND = 'AND';
    case OR = 'OR';
    case IMPORT = 'IMPORT';
    case INCLUDE = 'INCLUDE';
    case THASH = 'THASH'; // __loc__ marker handled as keyword

    case EOF = 'EOF';

    /** @var array<string, TokenType> */
    public const KEYWORDS = [];

    public static function keyword(string $word): ?self
    {
        return match ($word) {
            'def' => self::DEF,
            'if' => self::IF,
            'then' => self::THEN,
            'elif' => self::ELIF,
            'else' => self::ELSE,
            'end' => self::END,
            'as' => self::AS,
            'reduce' => self::REDUCE,
            'foreach' => self::FOREACH,
            'try' => self::TRY,
            'catch' => self::CATCH,
            'label' => self::LABEL,
            'and' => self::AND,
            'or' => self::OR,
            'import' => self::IMPORT,
            'include' => self::INCLUDE,
            default => null,
        };
    }
}

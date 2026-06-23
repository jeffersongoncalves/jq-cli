# Changelog

All notable changes to `jq-cli` will be documented in this file.

## 1.0.0 - 2026-06-23

Initial release of jq-cli — a jq clone written in pure PHP on Laravel Zero.

- Full jq-language interpreter: lexer, precedence-climbing parser, and a generator-based evaluator honouring jq's stream semantics.
- Native and jq-defined (prelude) builtins, PCRE regex (test/match/capture/scan/sub/gsub), @-formats, update operators, and path expressions.
- jq-compatible CLI flags and exit codes; concatenated JSON / NDJSON input, --slurp, --raw-input, input/inputs.
- Windows-first distribution: PHAR plus a jq.bat wrapper.

Install: composer global require jeffersongoncalves/jq-cli

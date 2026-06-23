# Changelog

All notable changes to `jq-cli` will be documented in this file.

## 1.0.0 - 2026-06-23

### Added

- Initial release: a `jq` clone written in pure PHP on Laravel Zero.
- Own lexer, precedence-climbing parser and generator-based interpreter for the jq language (paths, pipes, comma, construction, string interpolation, `if`/`try`/`reduce`/`foreach`/`label`, bindings and destructuring, function definitions with filter and value parameters).
- Native and jq-defined builtins, regex (`test`/`match`/`capture`/`scan`/`sub`/`gsub`), `@`-formats, update operators (`=`, `|=`, `+=`, ...), and path expressions (`path`, `getpath`, `setpath`, `delpaths`, `del`).
- jq-compatible CLI flags (`-n -r -j -c -s -R -a -S -e --tab --indent --arg --argjson --slurpfile --rawfile --args --jsonargs -f`) and exit codes.
- Concatenated JSON / NDJSON input, `--slurp`, `--raw-input`, `input`/`inputs`.
- Windows-first distribution: PHAR plus a `jq.bat` wrapper.

# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2026-11-04

### Bug Fixes

- Pin PHP floor to 8.3 for Laravel Zero 13

### CI/CD

- Pass release version input through env
- Standardize dependabot config
- Standardize tests workflow
- Remove run-tests.yml (replaced by tests.yml)
- Standardize tests workflow
- Auto-merge dependabot github-actions minor/patch

### Dependencies

- **deps:** Bump orhun/git-cliff-action in the actions-deps group (#13)
- **deps:** Bump shivammathur/setup-php (#14)
- **deps:** Bump stefanzweifel/git-auto-commit-action (#16)

### Documentation

- Point tests badge to tests.yml

### Miscellaneous Tasks

- **deps-dev:** Bump the composer-deps group with 4 updates

### Other

- Auto-assign issues and pull requests

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>

## [1.0.2] - 2026-09-08

### Bug Fixes

- **deps:** Update guzzlehttp/guzzle to patch security advisories
- **ci:** Publish release as draft until PHAR asset is attached
- **ci:** Use the correct resolve-version output in the publish step

### CI/CD

- Pin actions to commit SHA, add dependabot cooldown/composer, trim dist archive
- **release:** Generate CHANGELOG.md and release notes with git-cliff

### Dependencies

- **deps:** Bump actions/checkout from 6.1.0 to 7.0.1
- **deps:** Bump actions/cache from 5.1.0 to 6.1.0
- **deps:** Bump shivammathur/setup-php
- **deps:** Bump orhun/git-cliff-action from 4.8.0 to 4.9.0

### Documentation

- Add Buy Me a Coffee sponsor link
- Standardize README section structure

### Miscellaneous Tasks

- Bump guzzlehttp/guzzle and guzzlehttp/psr7 for security advisories
- Add GitHub Sponsors to FUNDING.yml

## [1.0.1] - 2026-07-24

### CI/CD

- Replace split build/changelog/publish-phar workflows with a single release job

## [1.0.0] - 2026-06-23

### Documentation

- Adicionar instalação via composer global e remover referências a phpjq

### Features

- Implementar jq-cli, clone do jq em PHP puro sobre Laravel Zero

### Testing

- Adicionar testes unitários e corrigir CI (tests/Unit ausente)



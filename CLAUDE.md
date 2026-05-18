# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A CodeIgniter 4 package skeleton/template. The placeholder strings `YourVendor`, `YourPackage`, and `vendor/package` throughout the codebase must be replaced before publishing a real package.

## General Guidelines
- Follow PSR-12 coding style (enforced by `php-cs-fixer`).
- Use strict types and type declarations.
- Use PHPDoc comments for all public methods and properties.
- Follow CI4 conventions for Models, Services, and Config.
- Write unit tests for all new features and bug fixes.
- When adding new dependencies, update `composer.json` and ensure they are compatible with CI4 and PHP 8.2+.
- When modifying CI4 config or services, update `Registrar.php` and `Services.php` accordingly.
- When adding new features, consider how they will be tested and whether they require new database tables or config options.

## Commands

### Testing
```bash
composer test                   # run PHPUnit locally
composer test:coverage          # HTML coverage report → build/phpunit/html/
composer docker:test            # run PHPUnit inside Docker
composer docker:test:coverage   # coverage inside Docker
```

Run a single test file:
```bash
./vendor/bin/phpunit tests/ExampleTest.php
```

### Code Quality
```bash
composer cs          # check coding style (php-cs-fixer, dry-run)
composer cs-fix      # auto-fix coding style
composer analyze     # PHPStan (level 5) + Rector dry-run
composer rector      # apply Rector changes
composer deduplicate # phpcpd duplicate detection
composer ci          # run all checks: cs → deduplicate → analyze → test
```

Docker equivalents: prefix any command above with `docker:` (e.g., `composer docker:ci`).

### Docker
```bash
docker compose up         # start dev server at http://localhost:8080
composer docker:build     # rebuild image after Dockerfile changes
composer docker:shell     # bash shell inside container
```

## Architecture

**CI4 Auto-Discovery** — CI4 discovers this package automatically via Composer autoload. No manual wiring is needed in the host app.

- `src/Config/Registrar.php` — registers filter aliases and other CI4 config hooks; CI4 calls static methods on this class during bootstrap
- `src/Config/Services.php` — extends `BaseService` to register package services available via `service('name')`
- `src/Exceptions/PackageException.php` — base exception class for the package

**Namespace**: `YourVendor\YourPackage\` maps to `src/`. Test namespace `Tests\` maps to `tests/`, `Tests\Support\` maps to `tests/_support/`.

**PHPUnit bootstrap**: uses `vendor/codeigniter4/framework/system/Test/bootstrap.php` — this is required for CI4 test helpers and must remain in `phpunit.xml.dist`.

## Pre-commit Hook

`composer install` / `composer update` installs a pre-commit hook (`admin/pre-commit → .git/hooks/pre-commit`) that:
1. Lints PHP syntax on staged `.php` files
2. Auto-runs `php-cs-fixer` on staged files and re-stages the fixes

## CI Workflows

Workflows run on `develop` branch PRs/pushes. PHPUnit runs against PHP 8.2–8.5 × MySQL/SQLite/PostgreSQL/SQLSRV/OCI8.

## PHPStan

Level 5 with strict rules enabled (`phpstan.neon.dist`). When adding new Config namespaces or Services, register them under `parameters.codeigniter.additionalConfigNamespaces` / `additionalServices` in `phpstan.neon.dist`.

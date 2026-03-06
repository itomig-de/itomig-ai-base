# PHPStan — Standalone Static Analysis

This is the **standalone** PHPStan configuration for `itomig-ai-base`. It uses stub files to provide iTop class definitions, so no full iTop installation is required.

## Usage

From the extension root directory:

```bash
composer install
vendor/bin/phpstan analyse
```

PHPStan will automatically find `phpstan.neon` in the project root.

## How It Works

The stub files in `tests/phpstan/stubs/` provide minimal class definitions for iTop classes used by this extension (e.g. `IssueLog`, `MetaModel`, `Dict`). This allows PHPStan to resolve all type references without needing the actual iTop codebase.

## iTop-Integrated Variant

For deeper analysis using real iTop classes (e.g. to verify method signatures against the actual iTop version), see `tests/php-static-analysis/`.

# PHPStan — Static Analysis for itomig-ai-base

This extension supports two PHPStan variants:

## Variant A: Standalone (recommended for CI/CD and local development)

Uses stub files instead of real iTop classes. No iTop installation required.

```bash
# From the extension root directory:
composer install
vendor/bin/phpstan analyse
```

See also: `tests/phpstan/README.md`

## Variant B: iTop-Integrated (for deep analysis with real classes)

Runs within a fully installed iTop instance, using the real iTop bootstrap. This catches issues that stubs cannot detect (e.g. wrong method signatures against a specific iTop version).

### Setup

1. Install phpstan in iTop's `tests/php-static-analysis/` directory:

```bash
cd /path/to/itop/tests/php-static-analysis
composer install
```

2. Run the analysis from the iTop root directory:

```bash
./tests/php-static-analysis/vendor/bin/phpstan analyse \
  -c ./env-production/itomig-ai-base/tests/php-static-analysis/config/itomig-ai-base.neon
```

## When to Use Which Variant

| Scenario | Variant |
|----------|---------|
| CI/CD pipeline | Standalone |
| Quick local check | Standalone |
| Pre-commit verification | Standalone |
| Verify against specific iTop version | iTop-Integrated |
| Debug iTop API compatibility issues | iTop-Integrated |

# Contributing to SampleHQ Request Form

## Requirements

- PHP 8.0+
- Composer 2.x
- Node.js 18+ and npm
- Docker (for wp-env local environment)

## Setup

```bash
git clone git@github.com:samplehq/samplehq-request-form.git
cd samplehq-request-form
composer install
npm install
npm run build
```

## Local Development Environment

The plugin uses [@wordpress/env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
for local development. Docker must be running.

```bash
npx wp-env start          # Start WordPress at http://localhost:8888
npx wp-env stop           # Stop the environment
npx wp-env clean all      # Reset database and uploads
npx wp-env destroy        # Remove everything
```

Admin login: `admin` / `password`

## Running Tests

```bash
# Unit tests (no WordPress needed)
composer test

# Unit tests with coverage report
composer test:coverage

# Single test file or filter
php -d memory_limit=512M vendor/bin/phpunit --filter "MailerTest" --testdox

# Integration tests (needs wp-env running)
composer test:integration

# E2E tests (needs wp-env running)
npx playwright test

# E2E with browser visible
npx playwright test --headed
```

## Linting and Static Analysis

```bash
# Run everything
composer check             # PHP lint + PHPStan + unit tests

# Individual tools
composer lint              # PHPCS
composer lint:fix           # PHPCBF auto-fix
composer analyze           # PHPStan level 6
npm run lint:js            # ESLint
npm run lint:css           # Stylelint
```

## Building Assets

```bash
npm run build              # Production build
npm run start              # Development mode with watch
```

Built assets go to `assets/build/` (git-ignored). The source lives in `assets/src/`.

## Coding Standards

- PHP: WordPress Coding Standards (WPCS) via PHPCS
- JavaScript: `@wordpress/eslint-plugin`
- CSS: `@wordpress/stylelint-config`
- PHP 8.0+ strict types: every file starts with `declare(strict_types=1)`
- PSR-4 autoloading: `SampleHQForm\` maps to `src/`

## Pull Request Process

1. Create a feature branch from `main`
2. Make your changes
3. Run `composer check` and `npm run check` -- both must pass
4. Write or update tests for your changes
5. Push and open a PR against `main`
6. CI must pass before merge

## Project Structure

```
src/
  Admin/         # Admin pages, list tables, settings
  Api/           # REST API endpoints
  Blocks/        # Gutenberg block
  Database/      # Table classes, migrations
  Email/         # Mailer service
  Elementor/     # Elementor widget
  Export/        # CSV/JSON import/export
  Fields/        # Form field types
  Forms/         # Form rendering, processing, validation
  Helpers/       # Asset loading
  Privacy/       # GDPR data export/erasure
  Spam/          # Honeypot, rate limiting, Turnstile
tests/
  Unit/          # PHPUnit unit tests (mocked WordPress)
  Integration/   # PHPUnit integration tests (real WordPress)
  e2e/           # Playwright browser tests
assets/
  src/           # Source JS/CSS
  build/         # Built output (git-ignored)
```

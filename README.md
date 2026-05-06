# SampleHQ Request Form

[![CI](https://github.com/codeverbojan/samplehq-request-form/actions/workflows/ci.yml/badge.svg)](https://github.com/codeverbojan/samplehq-request-form/actions/workflows/ci.yml)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)](https://www.php.net/)
[![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A complete sample request management system for WordPress. Includes a sample library, visual form builder with 15+ field types, submissions dashboard, and optional WooCommerce integration.

## Features

- **Sample Library** — manage product samples with categories, images, and descriptions
- **Visual Form Builder** — drag-and-drop builder with 15+ field types including a unique sample picker
- **Submissions Dashboard** — view, search, filter, star, and export submissions
- **Multiple Embed Options** — Gutenberg block, Elementor widget, shortcode
- **WooCommerce Integration** — use WooCommerce products as samples, add request buttons to product pages
- **Spam Protection** — honeypot, Cloudflare Turnstile CAPTCHA, IP-based rate limiting
- **Privacy & GDPR** — consent fields, configurable IP retention, WP Privacy API export/erasure
- **Accessible** — WCAG 2.2 Level AA compliant forms with keyboard navigation and screen reader support

## Requirements

- PHP 8.0+
- WordPress 6.0+
- WooCommerce 8.0+ (optional, for product integration)

## Installation

Download the latest release zip from the [Releases page](https://github.com/codeverbojan/samplehq-request-form/releases) and upload via **Plugins > Add New > Upload Plugin** in your WordPress admin.

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md) for setup instructions, coding standards, and test commands.

```bash
composer install && npm install && npm run build
npx wp-env start  # http://localhost:8888 (admin/password)
```

## Testing

```bash
composer test              # 647 unit tests
composer test:integration  # 29 integration tests (needs wp-env)
npx playwright test        # 19 E2E browser tests (needs wp-env)
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

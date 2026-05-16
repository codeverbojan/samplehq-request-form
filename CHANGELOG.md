# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.2] - 2026-05-16

### Added

- Platform connection with submission sync and data migration engine
- Connection flow tests, migration engine tests, and submission sync tests

### Fixed

- PHP 8.0 compatibility: replaced `true` standalone return type with `bool` in ConnectionManager

## [1.0.1] - 2026-05-07

### Added

- Release procedure with CI changelog validation

## [1.0.0] - 2026-05-06

### Added

- Sample library with categories, images, SKUs, and descriptions
- Visual drag-and-drop form builder with 17 field types
- Sample picker field with product grid, category filters, and search
- Multi-step wizard form layout with step indicator and validation
- Submissions dashboard with search, filter, star, and CSV export
- Gutenberg block, Elementor widget, and shortcode embedding
- Spam protection: honeypot, Cloudflare Turnstile, rate limiting
- Email notifications with customizable templates
- File upload field with server-side MIME validation and protected storage
- WooCommerce integration: product page button, modal form, loop badge
- Privacy controls: consent field, IP retention settings, WP Privacy API
- Conditional field visibility with rule builder
- Undo/redo in form builder
- RTL stylesheet support
- Accessibility: WCAG 2.2 Level AA compliance

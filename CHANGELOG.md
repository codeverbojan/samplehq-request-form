# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.6] - 2026-05-18

### Fixed

- Moved inline JavaScript event handlers (`onclick`, `onchange`) to enqueued `admin-utils.js` file
- Replaced upsell language in migration wizard plan-limit message

## [1.0.5] - 2026-05-18

### Fixed

- Scoped admin notices (Turnstile failure, upload exposure) to plugin pages only (guideline 11)
- Removed `sslverify => false` from upload protection self-check
- Include `assets/src/` in distribution zip so source code ships alongside built files

## [1.0.4] - 2026-05-18

### Fixed

- Restored auto-login redirect after platform connection (was never committed)
- Replaced `wp_redirect()` with `wp_safe_redirect()` + `allowed_redirect_hosts` filter for all external redirects
- Sanitized `$_SERVER` inputs in connection callback handler
- Prefixed template variables and global plugin instance for WP.org guideline compliance
- Corrected `auto_login_url` type annotation in `ConnectionManager::validate_callback()` PHPDoc
- Replaced `wp_http_validate_url()` with scheme/host validation for auto-login URL (SSRF check was blocking local dev)

## [1.0.3] - 2026-05-18

### Fixed

- Removed arbitrary CSS injection feature flagged by wp.org review
- Moved inline `<script>` tags to external enqueued JS files (SampleEditPage, SettingsPage)
- Added file upload validation: extension check, finfo MIME check, `is_uploaded_file()` guard
- Added recursive sanitization for imported JSON form configs (`sanitize_config_recursive`)
- Sanitized `connection_token` input with `sanitize_text_field()`
- Removed redundant `wpApiSettings` localization (wp-api-fetch already provides it)
- Fixed React Hook `exhaustive-deps` warnings in FormBuilder.js

### Added

- Source code section in readme.txt linking to public GitHub repository
- Source/license banner in all built JS files via webpack BannerPlugin
- TerserPlugin override to preserve `/*!` comments through minification
- 14 new unit tests for `sanitize_config_recursive` edge cases

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

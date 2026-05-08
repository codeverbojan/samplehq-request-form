# SampleHQ Request Form

[![CI](https://github.com/codeverbojan/samplehq-request-form/actions/workflows/ci.yml/badge.svg)](https://github.com/codeverbojan/samplehq-request-form/actions/workflows/ci.yml)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)](https://www.php.net/)
[![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A WordPress plugin for collecting product sample requests. Sample library, form builder with a sample picker field, and submissions dashboard -- all local to your WordPress site.

The [SampleHQ platform](https://samplehq.io) adds fulfillment tracking, CRM, team workflows, and analytics. The plugin works fully without it.

---

## Current Status

SampleHQ Request Form is in final review before WordPress.org submission. The plugin is suitable for local testing and developer review, but a packaged public release has not been published yet.

Do not use this on production sites with real customer data until a tagged release has been published and tested in your environment.

---

## At a Glance

- **Purpose:** Collect structured product sample requests in WordPress
- **Best for:** B2B manufacturers, packaging companies, material suppliers, flooring companies, and WooCommerce stores
- **Embeds:** Shortcode, Gutenberg block, Elementor widget
- **Data:** Stored locally in WordPress -- no external service required
- **SaaS required:** No
- **Status:** Final review before WordPress.org submission

---

## How It Works

1. Add products to the **sample library** (name, SKU, images, categories, description).
2. Build a **request form** with the drag-and-drop builder. Add a sample picker field -- it pulls from your library.
3. Visitors browse samples, select what they want with quantities, fill in their details, and submit.
4. Manage requests in the **submissions dashboard** -- filter, search, star, export CSV.

---

## Who Is This For

- Packaging and labeling manufacturers
- Material suppliers (textiles, metals, wood, composites)
- Flooring companies (hardwood, tile, vinyl, carpet)
- Building material suppliers (countertops, stone, insulation)
- WooCommerce stores that offer product samples
- Any B2B company that ships physical samples as part of their sales process

---

## Why Not a Normal Form Plugin?

Contact Form 7, WPForms, and Gravity Forms are built around text fields. Sample requests are product-based -- you need images, SKUs, categories, quantities, and structured selections that tie back to a catalog.

This plugin has the catalog built in.

---

## Key Features

### Sample Library
Each sample has a name, SKU, rich text description, categories, multiple images, and custom key-value fields (weight, dimensions, material, etc.). Bulk import via CSV.

### Visual Form Builder
Drag-and-drop builder with 17 field types. Multi-column rows, conditional logic (show/hide fields based on values), multi-step wizard layout, and per-form email notification settings.

**Field types:** Text, Email, Phone, Paragraph, Number, Date, URL, Dropdown, Radio, Checkbox, Name (first + last), Address (composite), File Upload, Hidden, HTML Content, Consent/GDPR, and **Sample Picker**.

### Sample Picker Field
Reads directly from your sample library (or WooCommerce products when enabled) and renders as:
- **Card grid** -- product cards with images, descriptions, and category tabs
- **Checklist** -- compact list with checkboxes and descriptions
- **List view** -- searchable list with category filtering

Visitors select samples, optionally set quantities, and their choices are stored as structured data -- not free text in a textarea.

### Submissions Dashboard
Filter by form, date range, or status. Search by email or name. Star, mark read/unread, bulk actions. Export filtered results to CSV (UTF-8, Excel-compatible, formula injection prevention).

### Embedding
- **Gutenberg block** -- search "SampleHQ Form" in the block inserter, pick a form, server-side preview in the editor
- **Elementor widget** -- appears when Elementor is active
- **Shortcode** -- `[samplehq_form id="123"]` works in any page builder

### WooCommerce Integration
When WooCommerce is active, a new settings tab appears. Enable it and the sample picker uses your WooCommerce products instead of the built-in library. A "Request a Sample" button is added to product pages. Filter which product categories are available for sampling.

### Spam Protection
Active by default: honeypot field, IP-based rate limiting (10 per form per hour, filterable), CSRF token validation. Optional Cloudflare Turnstile (free) for an additional layer.

### Privacy and GDPR
- Consent checkbox field type for GDPR compliance
- IP address collection is toggleable (off = no IP stored at all)
- Configurable IP retention period with automatic purging via wp-cron
- Full WordPress Privacy API integration: personal data export and erasure by email

### Accessibility
Proper label associations, keyboard navigation, visible focus states, ARIA announcements, `prefers-reduced-motion` support. Targets WCAG 2.2 Level AA.

---

## Screenshots

<p align="center">
  <img src=".wordpress-org/screenshot-1.png" alt="Visual Form Builder" width="800" /><br />
  <strong>Visual Form Builder</strong> -- drag-and-drop with field palette, sample picker, multi-column rows, form settings panel
</p>

<p align="center">
  <img src=".wordpress-org/screenshot-2.png" alt="Frontend Form (Card Grid)" width="800" /><br />
  <strong>Frontend Form (Card Grid)</strong> -- sample picker with product images, category tabs, and descriptions
</p>

<p align="center">
  <img src=".wordpress-org/screenshot-3.png" alt="Dashboard" width="800" /><br />
  <strong>Dashboard</strong> -- stat cards, quick actions, recent forms, recent submissions
</p>

<p align="center">
  <img src=".wordpress-org/screenshot-7.png" alt="Frontend Form (List View)" width="800" /><br />
  <strong>Frontend Form (List View)</strong> -- checklist layout with category tabs and search
</p>

<p align="center">
  <img src=".wordpress-org/screenshot-5.png" alt="Submissions Dashboard" width="800" /><br />
  <strong>Submissions Dashboard</strong> -- filter, search, star, bulk actions, CSV export
</p>

<p align="center">
  <img src=".wordpress-org/screenshot-6.png" alt="Sample Library" width="800" /><br />
  <strong>Sample Library</strong> -- products with images, SKUs, categories, request counts, and status
</p>

<p align="center">
  <img src=".wordpress-org/screenshot-8.png" alt="Settings" width="800" /><br />
  <strong>Settings</strong> -- tabs for General, Spam Protection, Email, SampleHQ Connection, and WooCommerce
</p>

---

## Installation

This plugin has not been published to WordPress.org yet. It is in final review before submission.

**For local testing:**

```bash
git clone https://github.com/codeverbojan/samplehq-request-form.git
cd samplehq-request-form
composer install
npm install && npm run build
```

Then place the plugin folder in `wp-content/plugins/` and activate "SampleHQ Request Form" from the WordPress admin.

### Requirements
- WordPress 6.0+
- PHP 8.0+
- WooCommerce 8.0+ (optional, for product integration)

---

## Quick Start

**Step 1: Add samples to your library**
Go to **SampleHQ Forms > Sample Library > Add Sample**. Enter a name, SKU, description, category, and upload an image. Repeat for each product. Or use **Import CSV** to bulk import.

**Step 2: Create a form**
Go to **SampleHQ Forms > Forms > Create Form**. Choose a template (Wizard, Grid, Checklist, or Blank). The form builder opens with a field palette on the left and a canvas in the center. Drag fields onto the canvas. Add a **Sample Picker** field -- it automatically pulls from your library. Configure form settings (layout, submit button text, success message) in the right panel. Click **Save**.

**Step 3: Embed the form on a page**
Use any of the three embed methods:
- **Gutenberg:** Add a block, search "SampleHQ Form", select your form
- **Elementor:** Add the SampleHQ Form widget, select your form
- **Shortcode:** Paste `[samplehq_form id="123"]` (replace 123 with your form ID, visible in the form list)

**Step 4: Receive and manage submissions**
Submissions appear under **SampleHQ Forms > Submissions**. You'll also get email notifications (configurable under **Settings > Email**). Filter, search, star, and export as needed.

---

## Shortcode Usage

```
[samplehq_form id="123"]
```

| Attribute | Required | Description |
|-----------|----------|-------------|
| `id` | Yes | The form ID (shown in the Forms list table and in the form builder sidebar under "Info") |

The shortcode works in posts, pages, widgets, and any page builder that supports WordPress shortcodes. Frontend CSS and JS are loaded only on pages where the shortcode is present.

---

## WooCommerce Integration

When WooCommerce 8.0+ is active:

1. A **WooCommerce** tab appears under **SampleHQ Forms > Settings**
2. Enable "Use WooCommerce products as sample source"
3. Optionally filter by product categories (only show specific categories in the sample picker)
4. A **"Request a Sample"** button is added to WooCommerce single product pages
5. The sample picker field in your forms now shows WooCommerce products with their images and descriptions

Each form's sample picker can use either the built-in library or WooCommerce products.

---

## Email Notifications

1. **Admin notification** -- all submitted data + link to the submission. Reply-To set to the submitter's email.
2. **Submitter confirmation** (optional) -- confirms the request was received with a summary.

Global defaults under **Settings > Email**. Per-form overrides in the form builder.

### Email Hooks for Developers

```php
// Customize admin notification content
add_filter( 'shqf_admin_notification_content', function( $body, $submission, $meta, $form ) {
    return $body;
}, 10, 4 );

// Customize admin notification subject
add_filter( 'shqf_admin_notification_subject', function( $subject, $submission, $form ) {
    return $subject;
}, 10, 3 );

// Customize submitter confirmation content
add_filter( 'shqf_confirmation_email_content', function( $body, $submission, $meta, $form ) {
    return $body;
}, 10, 4 );
```

---

## Privacy and GDPR

| Setting | Location | Default |
|---------|----------|---------|
| IP address collection | Settings > General | On |
| IP retention period | Settings > General | 90 days |
| Consent field | Form builder (add a Consent field) | -- |
| Personal data export | Tools > Export Personal Data | Automatic |
| Personal data erasure | Tools > Erase Personal Data | Automatic |

Privacy requests find all submissions by email and include them in the export or delete them (including meta, rate limits, and uploads). Disable IP collection to store NULL instead.

---

## Developer Hooks

The plugin provides filters for customization without modifying core files:

| Hook | Type | Description |
|------|------|-------------|
| `shqf_admin_notification_content` | filter | Customize admin email body |
| `shqf_admin_notification_subject` | filter | Customize admin email subject |
| `shqf_confirmation_email_content` | filter | Customize confirmation email body |
| `shqf_client_ip` | filter | Override client IP detection (useful behind proxies/CDNs) |
| `shqf_rate_limit` | filter | Change rate limit threshold (default: 10) |
| `shqf_rate_window` | filter | Change rate limit window in seconds (default: 3600) |
| `shqf_load_google_fonts` | filter | Disable Google Fonts loading (return `false`) |
| `shqf_woo_show_variations` | filter | Show WooCommerce product variations in sample picker |

---

## Developer Setup

### Requirements
- PHP 8.0+, Composer 2.x, Node.js 18+, Docker

### Setup
```bash
git clone git@github.com:codeverbojan/samplehq-request-form.git
cd samplehq-request-form
composer install
npm install
npm run build
```

### Local Environment
```bash
npx wp-env start          # WordPress at http://localhost:8888 (admin / password)
npx wp-env stop           # Stop
npx wp-env clean all      # Reset database
```

### Building Assets
```bash
npm run build             # Production build -> assets/build/
npm run start             # Development mode with file watching
```

---

## Testing

The plugin has 729 unit tests, 29 integration tests, and 27 Playwright spec files.

```bash
# Unit tests (no WordPress needed, fast)
composer test

# Integration tests (needs wp-env running)
composer test:integration

# E2E browser tests (needs wp-env running)
npx playwright test

# Linting and static analysis
composer lint              # PHPCS (WordPress Coding Standards)
composer analyze           # PHPStan level 6
npm run lint:js            # ESLint (@wordpress/eslint-plugin)
npm run lint:css           # Stylelint (@wordpress/stylelint-config)

# Run everything
composer check             # PHP lint + PHPStan + unit tests
```

### Code Quality
- PHPCS (WordPress Coding Standards) + PHPStan level 6
- ESLint (`@wordpress/eslint-plugin`) + Stylelint (`@wordpress/stylelint-config`)
- `declare(strict_types=1)` in every PHP file
- PSR-4 autoloading: `SampleHQForm\` → `src/`

See [CONTRIBUTING.md](CONTRIBUTING.md) for the pull request process.

---

## Project Structure

```
src/                       75 PHP files
  Admin/                   Admin pages, list tables, settings, dashboard
  Api/                     REST API (public submission + admin CRUD endpoints)
  Blocks/                  Gutenberg block (block.json, server-side render)
  Connection/              Platform connection (state token, HMAC signing, encryption)
  Database/                8 custom tables, versioned migrations
  Elementor/               Elementor widget (conditional registration)
  Email/                   Mailer (admin notification + submitter confirmation)
  Export/                  CSV export/import, JSON form export/import
  Fields/                  17 field types including Sample Picker
  Forms/                   Rendering, validation, submission processing
  Helpers/                 Asset loading
  Privacy/                 WordPress Privacy API (export + erasure)
  Spam/                    Honeypot, rate limiting, Turnstile
  WooCommerce/             Product button, modal form, product source
tests/
  Unit/                    729 PHPUnit tests (mocked WordPress, fast)
  Integration/             29 PHPUnit tests (real WordPress via wp-env)
  e2e/                     27 Playwright spec files
```

---

## Roadmap

### v1.0 (shipped)
Sample library, form builder (17 field types), sample picker, conditional logic, multi-step wizard, file uploads, Gutenberg block, Elementor widget, shortcode, WooCommerce integration, submissions dashboard, email notifications, spam protection, Privacy API, CSV export/import, JSON form export/import, form duplication.

### v1.x (in progress)
- SampleHQ platform connection -- sync submissions to the cloud platform
- Data migration wizard (samples, categories, submission history)

### v2 (planned)
- Import from Gravity Forms, WPForms, Contact Form 7, Ninja Forms

---

## SampleHQ Request Form vs. SampleHQ

| | Request Form (this plugin) | SampleHQ (platform) |
|---|---|---|
| Sample library | Yes | Yes |
| Form builder | Yes | Yes |
| Submissions | Local WordPress DB | Cloud with team workflows |
| CRM integration | No | Salesforce, HubSpot |
| Shipping labels | No | Shippo |
| Analytics | No | Yes |
| Requires account | No | Yes |
| Price | Free | Paid |

The plugin works standalone. Connecting to SampleHQ is optional and never restricts plugin functionality.

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

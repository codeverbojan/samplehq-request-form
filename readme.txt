=== SampleHQ Request Form ===
Contributors: josifoskibojan
Tags: sample request, form builder, product samples, sample management, request form
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A complete sample request management system with a sample library, visual form builder, and submissions dashboard.

== Description ==

SampleHQ Request Form is a free WordPress plugin purpose-built for collecting product
sample requests. It includes a local sample library, a drag-and-drop form builder,
and a full submissions dashboard -- all without requiring any external account.

**Key Features:**

* **Sample Library** -- Manage your product samples with categories, images, and descriptions
* **Visual Form Builder** -- Drag-and-drop builder with 15+ field types
* **Sample Picker Field** -- Unique field that lets visitors select products with quantities
* **Submissions Dashboard** -- View, search, filter, star, and export submissions
* **Gutenberg Block** -- Native block editor integration
* **Elementor Widget** -- Works with Elementor page builder
* **Shortcode** -- Works with any page builder via `[samplehq_form]`
* **Accessible** -- Built following WCAG 2.2 Level AA guidelines
* **Spam Protection** -- Honeypot, Cloudflare Turnstile, rate limiting
* **Privacy Controls** -- Consent fields, IP retention settings, WP Privacy API integration

**Optional: Connect to SampleHQ**

This plugin can optionally connect to [SampleHQ](https://samplehq.io), a cloud platform
for sample request management. Connecting syncs your submissions to the platform, where
you can manage them alongside CRM integrations, shipping, and team workflows. The plugin
works fully without connecting -- all features listed above are free and unlimited.

**Third-Party Service: SampleHQ**

This plugin can optionally connect to [SampleHQ](https://samplehq.io), a sample
management platform. When connected, form submissions and sample data are sent to
your SampleHQ workspace for processing. No data is sent unless you explicitly
configure the connection.

* Service URL: [https://samplehq.io](https://samplehq.io)
* Terms of Service: [https://samplehq.io/terms](https://samplehq.io/terms)
* Privacy Policy: [https://samplehq.io/privacy](https://samplehq.io/privacy)

**Third-Party Service: Cloudflare Turnstile**

This plugin can optionally use [Cloudflare Turnstile](https://www.cloudflare.com/products/turnstile/)
for spam protection. When enabled in Settings > Spam Protection, form submissions are
verified against the Turnstile API. No data is sent to Cloudflare unless you configure
a Turnstile site key.

* Service URL: [https://www.cloudflare.com/products/turnstile/](https://www.cloudflare.com/products/turnstile/)
* Terms of Service: [https://www.cloudflare.com/website-terms/](https://www.cloudflare.com/website-terms/)
* Privacy Policy: [https://www.cloudflare.com/privacypolicy/](https://www.cloudflare.com/privacypolicy/)

== Installation ==

1. Upload the `samplehq-request-form` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **SampleHQ Forms > Sample Library** to add your products
4. Go to **SampleHQ Forms > Forms > Add New** to create a request form
5. Embed the form using the Gutenberg block, Elementor widget, or shortcode

== Frequently Asked Questions ==

= Do I need a SampleHQ account? =

No. The plugin works completely standalone. A SampleHQ account is optional and adds
advanced features like approval workflows, CRM sync, and shipping integration.

= Is this plugin free? =

Yes. All features listed in the description are free and will remain free. There are
no time limits, submission quotas, or locked features.

= How do I create a form? =

Go to SampleHQ Forms > Forms > Create Form. The drag-and-drop builder lets you add
fields from the palette on the left, arrange them in columns, and configure settings
on the right. Save when done, then embed the form on any page.

= How do I embed a form on a page? =

Three options: use the native **Gutenberg block** (search for "SampleHQ Form" in the
block inserter), the **Elementor widget**, or the **shortcode** `[samplehq_form id="123"]`
(replace 123 with your form ID). All three work with any theme.

= How does WooCommerce integration work? =

Install and activate WooCommerce, then go to SampleHQ Forms > Settings > WooCommerce
(the tab only appears when WooCommerce is active). When enabled, the sample picker
field uses your WooCommerce products instead of the built-in sample library. A
"Request a Sample" button is added to product pages, and you can filter which product
categories are available for sampling.

= How do I configure the sample picker? =

Add a "Sample Picker" field in the form builder. Choose between card grid, list, or
checklist display modes. Set a maximum selection count, enable category filtering, and
optionally allow quantity selection. The picker supports search and category tabs.

= How do I set up email notifications? =

Go to SampleHQ Forms > Settings > Email. Configure the admin notification address and
sender name. Enable submitter confirmation emails to send an automatic reply with the
submitted data. Individual forms can override the global email settings.

= How do I export submissions? =

Open SampleHQ Forms > Submissions and click the "Export CSV" button. The export includes
all submission data, custom field values, and metadata. Files are UTF-8 encoded with
Excel compatibility and formula injection prevention built in.

= What spam protection is available? =

Three layers are always active: a honeypot field, rate limiting (10 submissions per form
per IP per hour), and CSRF token validation. You can optionally add Cloudflare Turnstile
(free invisible CAPTCHA) under Settings > Spam Protection.

= Does the plugin support conditional logic? =

Yes. Any field can have conditional visibility rules. Fields hidden by conditions are
automatically excluded from validation and sanitization, so users are never blocked by
fields they cannot see.

= How does the multi-step wizard work? =

Set the form layout to "Wizard (Multi-step)" in the form builder settings. The form
renders as a multi-step flow with a step indicator, step labels, and navigation buttons.
Each step validates its fields before allowing the user to proceed.

= Is the plugin GDPR compliant? =

The plugin integrates with the WordPress Privacy API for personal data export and
erasure requests. It includes a consent field type for collecting GDPR consent, IP
address collection can be disabled, and IP retention can be configured with automatic
purging after a set number of days.

= Is the plugin accessible? =

Yes. All forms are built following WCAG 2.2 Level AA guidelines, including proper
labels, keyboard navigation, screen reader support, and focus management.

== Screenshots ==

1. **Visual Form Builder** -- Drag-and-drop builder with 15+ field types, multi-column layouts, and the unique sample picker field.
2. **Sample Library** -- Manage your product samples with categories, images, SKUs, and descriptions.
3. **Submissions Dashboard** -- View, search, filter, star, and export form submissions with real-time status tracking.
4. **Frontend Form** -- Multi-step wizard form with sample picker, category filters, and search.
5. **Settings** -- Configure spam protection (Cloudflare Turnstile, honeypot, rate limiting), email notifications, and WooCommerce integration.
6. **Sample Editor** -- Rich editor for sample details with custom fields, categories, and images.
7. **Product Grid** -- Frontend sample picker with product cards, images, and category filtering.
8. **Email Settings** -- Configure admin notifications, sender name, and submitter confirmation emails.

== Changelog ==

= 1.0.0 =
* Sample library with categories, images, SKUs, and descriptions
* Visual drag-and-drop form builder with 15+ field types
* Sample picker field with product grid, category filters, and search
* Multi-step wizard form layout with step indicator and validation
* Submissions dashboard with search, filter, star, and CSV export
* Gutenberg block, Elementor widget, and shortcode embedding
* Spam protection: honeypot, Cloudflare Turnstile, rate limiting
* Email notifications with customizable templates
* File upload field with server-side MIME validation
* WooCommerce integration: product page button, modal form
* Privacy controls: consent field, IP retention, WP Privacy API
* Conditional field visibility with rule builder
* Undo/redo in form builder
* WCAG 2.2 Level AA accessibility

== Upgrade Notice ==

= 1.0.0 =
Initial release.

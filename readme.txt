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

= Is the plugin accessible? =

Yes. All forms are built following WCAG 2.2 Level AA guidelines, including proper
labels, keyboard navigation, screen reader support, and focus management.

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.

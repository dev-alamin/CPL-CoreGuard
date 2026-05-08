=== FatalFlow – WordPress Fatal Error Recovery & SEO Protection ===

Contributors:      coderalamin
Tags:              fatal-error, database-error, recovery, seo, white-screen-of-death
Requires at least: 5.9
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Protect your WordPress website from fatal errors, database crashes, and SEO damage with an instant recovery maintenance UI.

== Description ==

> Professional-grade fatal error protection for WordPress websites.

When a plugin update fails or your database server goes offline, most WordPress sites collapse into a broken white screen or generic server error. This hurts customer trust, interrupts sales, and can negatively impact SEO crawling.

**FatalFlow** intercepts fatal PHP and database failures *before WordPress fully loads*, displaying a clean branded recovery page instead of a broken website.

Perfect for agencies, WooCommerce stores, business websites, and production environments.

---

## Why FatalFlow?

### Protect Your SEO Rankings

FatalFlow returns a proper:

`503 Service Unavailable`

response with a `Retry-After` header — helping search engines understand that your site is temporarily unavailable instead of permanently broken.

This helps reduce the risk of:
- Google de-indexing
- SEO ranking drops
- Crawling issues caused by 500 errors

### Prevent the "White Screen of Death"

Instead of exposing visitors to raw PHP fatal errors or blank pages, FatalFlow shows:
- a professional maintenance screen,
- loading indicator,
- branded messaging,
- modern glassmorphism UI.

### Works Even When WordPress Fails

FatalFlow operates using:
- MU-plugin recovery logic
- `db-error.php` drop-ins
- shutdown fatal handlers

This means protection still works when:
- plugins crash,
- themes break,
- database connections fail,
- WordPress core cannot fully boot.

---

## Features

| Feature | Details |
|---|---|
| Fatal error protection | Handles `E_ERROR`, `E_PARSE`, `E_CORE_ERROR`, `E_COMPILE_ERROR` |
| Database outage recovery | Automatic `db-error.php` deployment |
| SEO-safe maintenance mode | Sends proper `503` headers |
| Zero dependency architecture | Works without database or active plugins |
| Lightweight performance | No frontend slowdown during normal operation |
| Beautiful recovery UI | Modern dark glassmorphism maintenance screen |
| WP-CLI aware | Automatically skips handlers during CLI operations |
| Safe cleanup | Removes generated files on deactivation |

---

## Technical Highlights

- System-level recovery architecture
- Early bootstrap interception
- Automatic MU-plugin deployment
- Database-independent rendering
- Clean activation & deactivation flow
- Minimal runtime overhead

---

## Installation

1. Upload the `fatalflow` folder to `/wp-content/plugins/`
2. Activate the plugin from the WordPress admin dashboard
3. Navigate to:
   `Settings → FatalFlow`
4. Configure:
   - Brand name
   - Accent color
   - Recovery message

---

## Frequently Asked Questions

### Does FatalFlow slow down my website?

No. FatalFlow only activates its recovery logic during fatal failures. During normal requests, performance impact is negligible.

### Can FatalFlow protect WooCommerce stores?

Yes. FatalFlow is especially useful for WooCommerce and business-critical websites where downtime affects sales and trust.

### Does this replace a backup solution?

No. FatalFlow is designed for graceful failure handling and SEO protection — not backups or disaster recovery.

### Will Google index the maintenance page?

FatalFlow sends proper `503 Service Unavailable` headers with retry instructions, which tells search engines the outage is temporary.

### Does it work if WordPress is completely broken?

Yes. FatalFlow uses MU-plugin and drop-in mechanisms that load before most of WordPress.

---

== Changelog ==

= 1.0.0 – 2026-05-08 =
* Initial public release
* Added fatal PHP error recovery handler
* Added database failure recovery UI
* Added SEO-safe 503 maintenance responses
* Added automatic MU-plugin and db-error deployment
* Added branded glassmorphism recovery screen
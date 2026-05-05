=== FatalFlow – Instant Recovery UI, SEO Shield & Fatal Error Handler ===

Contributors:      coderalamin
Tags:              fatal-error, database-error, recovery, mu-plugin
Requires at least: 5.9
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

The ultimate safety net for professional WordPress websites.

== Description ==

> The ultimate safety net for professional WordPress websites.

When a plugin update fails or your database goes offline, most sites show a broken "White Screen of Death" — scaring away customers and triggering Google de-indexing. **FatalFlow** intercepts these failures *before* WordPress even boots, replacing broken code and database errors with a polished, branded recovery UI that keeps your site looking professional no matter what.

---

## Protect Your SEO & Business Reputation

**Keep Search Crawlers Happy**
Instead of a broken 404 or 500 error, FatalFlow sends a proper `503 Service Unavailable` status — telling Google your site is temporarily down for maintenance and saving your SEO rankings.

**Instant Recovery UI**
Deploys a beautiful glassmorphism maintenance page that works even when WordPress core is completely inaccessible.

**Zero-Dependency Logic**
Operates as a system-level drop-in. No database, no active plugins required — it's there when nothing else is.

**Enterprise-Grade Reliability**
Built for small businesses that cannot afford a single minute of broken appearance.

---

## Technical Highlights

- Automatic deployment of the `db-error.php` drop-in
- High-priority MU-plugin handler for PHP fatals (`E_ERROR`, `E_PARSE`)
- **Lightweight & fast** — zero impact on site speed during normal operation
- **Clean deactivation** — removes all system modifications automatically

---

## Features

| Feature | Details |
|---|---|
| Fatal error coverage | `E_ERROR`, `E_PARSE`, `E_CORE_ERROR`, `E_COMPILE_ERROR` |
| Database failure coverage | Via `db-error.php` drop-in |
| SEO-safe response | `503 Service Unavailable` with `Retry-After` header |
| Zero WP dependencies | Works even when WordPress core is broken |
| Branded maintenance UI | Animated progress bar, dark glassmorphism design |
| WP-CLI safe | Shutdown handler skips during CLI operations |
| Clean removal | No files left behind on deactivation |

== Installation ==

1. Upload the `fatalflow` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Go to **Settings → FatalFlow** to set your brand name, color, and message.

== Changelog ==

= 1.1.0 =
* Full rewrite to WP coding standards.
* All dynamic output escaped with `htmlspecialchars`.
* Settings page rebuilt using the Settings API with `register_setting` and sanitize callbacks.
* `deploy_assets()` refactored into discrete, testable methods.
* `wp-config.php` injection made idempotent; handles non-standard ABSPATH-parent location.
* db-error.php drop-in only removed on deactivation if our marker is present.
* UI redesigned: dark glassmorphism with animated progress bar, system font stack typography.
* Added `LOCK_EX` to all `file_put_contents` calls.
* Added `wp_delete_file()` instead of bare `@unlink()`.
* Added `languages/` directory support.

= 1.0.0 =
* Initial release.
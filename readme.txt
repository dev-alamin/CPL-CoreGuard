== CPL CoreGuard ==

Contributors:      coderalamin
Tags:              maintenance, fatal-error, database-error, recovery, mu-plugin
Requires at least: 5.9
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Enterprise-grade instant recovery UI for WordPress fatal errors and database failures.

== Description ==

CPL CoreGuard deploys a self-contained MU-plugin and `db-error.php` drop-in that intercepts PHP fatal errors and database connection failures **before** WordPress finishes loading — displaying a polished, branded maintenance screen instead of a blank white page or PHP stack trace.

**How it works:**

1. On **activation** the plugin copies `shield-logic.php` into `wp-content/mu-plugins/` and writes a static config file with your brand settings.
2. A minimal `require_once` is injected at the top of `wp-config.php` so the shutdown handler fires as early as possible.
3. A `db-error.php` WordPress drop-in is written to `wp-content/` for database failure coverage.
4. On **deactivation** all three files are removed and `wp-config.php` is restored.

**Features:**

* Catches `E_ERROR`, `E_PARSE`, `E_CORE_ERROR`, `E_COMPILE_ERROR` fatals.
* Covers database connection failures via `db-error.php` drop-in.
* Correct **503** HTTP status with `Retry-After` header (good for SEO).
* Zero WordPress dependencies at render time — works even when WP core is broken.
* Branded maintenance page with animated progress bar (dark, glassmorphism UI).
* WP CLI–safe: shutdown handler skips during CLI operations.
* Clean deactivation: no files left behind.

== Installation ==

1. Upload the `cpl-coreguard` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Go to **Settings → CPL CoreGuard** to set your brand name, color, and message.

== Changelog ==

= 1.1.0 =
* Full rewrite to WP coding standards.
* All dynamic output escaped with `htmlspecialchars`.
* Settings page rebuilt using the Settings API with `register_setting` and sanitize callbacks.
* `deploy_assets()` refactored into discrete, testable methods.
* `wp-config.php` injection made idempotent; handles non-standard ABSPATH-parent location.
* db-error.php drop-in only removed on deactivation if our marker is present.
* UI redesigned: dark glassmorphism with animated progress bar, DM Sans + DM Serif Display typography.
* Added `LOCK_EX` to all `file_put_contents` calls.
* Added `wp_delete_file()` instead of bare `@unlink()`.
* Added `languages/` directory support.

= 1.0.0 =
* Initial release.
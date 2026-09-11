=== Content Freshness Reminder ===
Contributors: farhanalidev
Donate link: https://farhanali.me/
Tags: content, stale content, reminder, dashboard, agency
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Flags pages and posts that have gone stale for 6+ months and nudges the owner to update them — before a client's site looks abandoned.

== Description ==

**Content Freshness Reminder** solves a problem every agency knows: a client site launches, looks great, and then the "About" page quietly goes three years without an update.

This lightweight plugin keeps an eye on your published posts and pages and flags anything that hasn't been touched in a while.

**Core Features:**

- Dashboard widget listing stale pages/posts with a direct edit link.
- Dismissible admin notice on the Dashboard when stale content is found.
- A "Freshness" column on the Posts and Pages list screens with a Fresh/Stale badge.
- Optional weekly email digest to the site admin.
- Configurable staleness threshold (default 6 months).
- Choose which public post types to monitor.

No external services, no tracking, no bloat — just a nudge to keep content current.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/content-freshness-reminder` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Settings > Content Freshness to set your staleness threshold and choose which post types to monitor.

== Frequently Asked Questions ==

= Does this modify or touch my content? =

No. The plugin only reads `post_modified` dates — it never edits posts.

= Can I change the 6-month threshold? =

Yes, from Settings > Content Freshness, anywhere from 1 to 60 months.

= Does it work with custom post types? =

Yes, any public post type can be selected on the settings page.

== Changelog ==

= 1.0.0 =
* Initial release.

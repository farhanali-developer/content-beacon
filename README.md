# Content Beacon

A lightweight WordPress plugin that flags pages and posts nobody has touched in 6+ months and nudges the owner to update them.

Every agency knows this problem: a client site launches, looks great, and then the About page quietly goes years without an update. Content Beacon catches that before it becomes embarrassing.

## Features

- **Dashboard widget** — lists stale pages/posts with a direct edit link and "hasn't been updated since March 2025" style messaging
- **Dismissible admin notice** — a heads-up on the WordPress Dashboard when stale content is found, dismissible for a week at a time
- **Freshness column** — a Fresh/Stale badge added to the Posts and Pages list screens, alongside the last-modified date
- **Weekly email digest** — optional summary emailed to the site admin every week
- **Configurable threshold** — default is 6 months, adjustable from 1–60
- **Post type selection** — monitor any public post type, not just posts and pages
- Zero external dependencies, no tracking, no bloat — reads `post_modified` dates and nothing else

## Requirements

- WordPress 5.8+
- PHP 7.2+

## Installation

### From WordPress.org

Search for "Content Beacon" in **Plugins → Add New**, install, and activate.

### Manual / from source

```bash
git clone https://github.com/farhanali-developer/content-beacon.git
```

Copy (or symlink) the `content-beacon` folder into `wp-content/plugins/`, then activate it from **Plugins → Installed Plugins**.

## Usage

After activating, go to **Settings → Content Beacon** to set the staleness threshold and choose which post types to monitor.

Stale content shows up in two places automatically:

- A **Content Beacon** widget on the WordPress Dashboard, listing the oldest content first
- A **Freshness** column on the Posts and Pages list tables, with a Fresh/Stale badge next to the last-modified date

If a dismissible notice appears on the Dashboard, dismissing it snoozes it for that user for a week — it reappears if stale content is still there after that.

### Email digest

Enable "Weekly email digest" on the settings page to have a summary of stale content emailed to the site's admin address once a week, via a `weekly` WP-Cron schedule the plugin registers.

The digest is sent through WordPress's built-in `wp_mail()` — the plugin doesn't configure SMTP itself, so delivery depends on your site's mail setup. If digest emails aren't arriving (common on hosts that block PHP's default `mail()`), install an SMTP plugin such as WP Mail SMTP to route mail through a real provider.

## License

GPLv2 or later — see [LICENSE.txt](LICENSE.txt) or https://www.gnu.org/licenses/gpl-2.0.html

# WP Bulk Post Scheduler

Schedule a batch of draft posts in one go — pick the drafts, set a start time and an interval, and each post goes live in sequence. Perfect for drip-feeding a content queue instead of publishing everything at once or scheduling posts one by one.

## How it works

1. Go to **Tools → Bulk Scheduler**.
2. Pick a post type and tick the drafts you want to schedule (oldest draft goes first).
3. Set when the first post should go live and how far apart posts should publish (every N hours or days).
4. Click **Schedule selected posts** — each draft becomes a scheduled (`future`) post at its slot.

Example: 10 drafts, start tomorrow 09:00, one every day → post 1 goes live tomorrow 09:00, post 2 the day after, and so on.

## Features

- Works with posts, pages, and any public custom post type.
- Validates the start time (must be in the future) and the interval before scheduling anything.
- Select-all / deselect-all checkbox; links straight to each draft's editor.
- No options or tables stored — uninstalling never un-schedules your posts.
- Filter hooks for developers:
  - `wbps_post_types` — change which post types appear in the dropdown.
  - `wbps_interval_seconds` — adjust the computed interval in seconds.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it from **Plugins**.
3. Open **Tools → Bulk Scheduler**.

## Requirements

- WordPress 5.0+
- PHP 7.4+

## License

MIT — see [LICENSE](LICENSE).

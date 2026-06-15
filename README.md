# WeTicket WordPress Sync

Periodically syncs events from a WeTicket Storefront RSS feed into a custom post type, rendered with Twig templates.

| | |
|---|---|
| **Requires WordPress** | 5.8+ |
| **Tested up to** | 6.5 |
| **Requires PHP** | 7.4+ |
| **Stable tag** | 1.0.0 |
| **License** | [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html) |
| **Tags** | events, ticketing, weticket, sync, twig |

## Description

WeTicket WordPress Sync reads the WeTicket Storefront RSS feed (`events.xml`) on a schedule and creates/updates a post in the **WeTicket Events** custom post type (`weticket_event`) for each event.

Titles and content are rendered with **Twig**. Because Twig runs in non-strict mode, template variables that the feed does not provide simply render empty instead of erroring, so templates stay robust as the feed evolves.

### What the feed provides

The WeTicket Storefront RSS feed exposes a focused set of fields. These map to the following Twig variables:

- `title` — event title
- `description` / `text` — plain-text event description
- `textHtml` — HTML event description (used as the post content by default)
- `start` — start date/time (`ev:startdate`)
- `end` — end date/time (`ev:enddate`)
- `tickets_url` — link to the ticket shop
- `venue.title` / `location.name` — venue (`ev:location`)
- `organizationName` — organizer (`ev:organizer`)
- `status` — defaults to `onsale` (the feed only lists upcoming events)
- `media.mainImageUrl` — cover image when `media:content` is present (set as the featured image)
- `guid` — the WeTicket event UUID (used to match events across syncs)

Variables that this feed does not provide (`prices`, `categories`, `programItems`, `status_internal`, and so on) are not populated and render empty.

### Custom fields (visible & REST-exposed)

In addition to rendering the title and content, each event stores its data as **public custom fields** so themes, ACF, and page builders can read individual values without parsing the content HTML. These appear in the Custom Fields metabox and the REST API:

- `weticket_start`
- `weticket_end`
- `weticket_tickets_url`
- `weticket_venue`
- `weticket_organizer`
- `weticket_description` (plain text)
- `weticket_text_html` (HTML)
- `weticket_status`
- `weticket_pubdate`

A few internal values stay protected (hidden) because they are plumbing rather than content: `_weticket_guid` (used to match events across syncs), `_weticket_synced_at`, and `_weticket_image_url`.

### Settings

Settings → **WeTicket Sync**:

- Feed URL
- Sync interval (15 minutes / hourly / twice daily / daily)
- What to do when an event leaves the feed (set to draft / move to trash / leave published)
- Title and content Twig templates
- A "Sync now" button and last-run status

## Installation

1. Upload the plugin to `wp-content/plugins/weticket-wordpress-sync`.
2. If installing from source, run `composer install` in the plugin directory to vendor Twig.
3. Activate the plugin.
4. Go to Settings → WeTicket Sync, set your feed URL, and click "Sync now".

## Frequently Asked Questions

### How are duplicate events avoided?

Each event's WeTicket UUID (the RSS `guid`) is stored in the `_weticket_guid` post meta. On every sync the plugin matches events by that UUID and updates the existing post instead of creating a new one.

### What happens to events that disappear from the feed?

Configurable: set them to draft (default), move them to trash, or leave them published.

## Changelog

### 1.0.0

- Initial release: scheduled RSS sync into the `weticket_event` post type with Twig templating.

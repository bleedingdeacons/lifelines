=== LifeLines ===
Contributors: thebleedingdeacons
Tags: lookup, search, directory, helpline, ajax
Requires at least: 6.1
Tested up to: 6.9
Stable tag: 1.0.1
Build date: 2026/07/07 22:48:10
Requires PHP: 8.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

A standalone real-time lookup tool for UK place, service and helpline data, with admin-configurable searchable and displayed columns.

== Description ==

**A standalone real-time lookup tool for UK place, service and helpline data.**

LifeLines imports a UK dataset (place / service / helpline records) that you
upload — a mysqldump of the `life_lines` table — into its own database table, and
exposes a fast, public smart lookup. As you type, matching results appear in real
time, partial-matched across the columns you choose. No data is bundled with the
plugin, and LifeLines is fully self-contained — it has **no plugin dependencies**.

**Features:**

* **Real-time smart lookup** — the `[lifelines_lookup]` shortcode renders a search box that fetches partial-match results live via AJAX as you type.
* **Auto-created public page** — on activation a published "Lookup" page containing the shortcode is created for you.
* **Configurable columns** — an admin settings page lets you choose which columns are searched and which are shown (and in what order) in the results.
* **Tunable behaviour** — set the maximum number of results and the minimum characters before a search fires.
* **Upload &amp; import** — upload a `.sql` dump of the `life_lines` table; it is imported and the uploaded file is then deleted. Only `life_lines` INSERT statements are executed, so an uploaded file cannot run arbitrary SQL. A live row count is shown.
* **Safe by construction** — column identifiers are restricted to a fixed whitelist and search terms are bound via `$wpdb->prepare()`.
* **Kill switch** — `define('LIFELINES_KILL', true)` in `wp-config.php` stands the plugin down without deactivating it.

== Installation ==

= From a .zip archive =

1. Build the `lifelines.zip` archive (`composer build`).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**.
3. Upload the archive and activate the plugin.
4. On activation the (empty) table is created and a public **Lookup** page is added. Then upload your data from **LifeLines → Smart Lookup**.

= From source =

1. Clone this repository into `wp-content/plugins/lifelines`.
2. Run `composer install`.
3. Activate **LifeLines** from the Plugins screen.

== Frequently Asked Questions ==

= How do I place the lookup on my own page? =

Add the `[lifelines_lookup]` shortcode to any page or post. An optional
`placeholder` attribute customises the input's placeholder text.

= How do I choose which columns are searched or displayed? =

Open the **LifeLines → Smart Lookup** admin screen and tick the searchable and
displayed columns, then save.

== Changelog ==

= 1.0.0 =
* Initial release: real-time smart lookup over an uploaded UK dataset, with a configurable admin settings page and an auto-created public lookup page.

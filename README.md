# LifeLines

[![CI](https://github.com/bleedingdeacons/lifelines/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/bleedingdeacons/lifelines/actions/workflows/ci.yml)
![Version](https://img.shields.io/badge/version-1.2.6-blue)
![PHP](https://img.shields.io/badge/php-8.1%2B-777bb4)
![Licence](https://img.shields.io/badge/licence-MIT%20(Modified)-green)

**A standalone real-time lookup tool for UK place, service and helpline data.**

LifeLines imports a UK dataset (place / service / helpline records) that you
upload as a CSV into its own table, and exposes a fast, public **smart lookup**:
partial-match search across admin-configurable columns, with results rendered in
real time as you type. It is self-contained — it has **no plugin dependencies**
and registers entirely on core WordPress hooks.

**Dependencies:** none
**License:** MIT (Modified — see [License](#license))
**Author:** [The Bleeding Deacons](mailto:thebleedingdeacons@gmail.com)

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Smart Lookup](#smart-lookup)
- [Architecture](#architecture)
- [Kill Switch](#kill-switch)
- [Extending LifeLines](#extending-lifelines)
- [Building for Production](#building-for-production)
- [License](#license)

---

## Requirements

- WordPress 6.1 or later
- PHP 8.1 or later
- No other plugins required

## Installation

1. Build the `lifelines.zip` archive (`composer build`) or clone this repository
   into `wp-content/plugins/lifelines`.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, or activate it from
   the Plugins screen if cloned.
3. On activation LifeLines creates its (empty) table and adds a public **Lookup**
   page.
4. Load your data: on **LifeLines → Smart Lookup**, upload a CSV whose first row
   is the column names (see [Smart Lookup](#smart-lookup)).

## Smart Lookup

LifeLines provides a self-contained, real-time lookup over a UK dataset (place /
service / helpline data) that you import into its `wp_life_lines` table.
No data is bundled with the plugin — you upload a **CSV** (first row = column
names) from the admin screen, and can export the current data back to CSV. It is
fully **self-contained** — no plugin dependencies; it registers on core
WordPress hooks.

**Public page.** On activation the plugin creates a published **Lookup** page
containing the `[lifelines_lookup]` shortcode (you can also drop that shortcode on
any page). As you type, results are fetched from `admin-ajax.php` and rendered
live, matching **partial values** across the configured columns. The results list
is responsive — a table on wider screens, stacked cards on phones.

**Remembered state.** The search term is mirrored to the URL (`?q=…`) and to
`sessionStorage`, and the scroll position is stored too. If a visitor searches,
navigates away, and returns to the page, their term, results, and scroll position
are restored. A URL with `?q=` pre-fills and runs that search (shareable links).

**Admin settings** (*LifeLines* menu → **Smart Lookup**):

- **Searchable columns** — which columns the typed text is partial-matched against.
- **Displayed columns** — which columns (and in what order) appear in the results.
- **Maximum results** and **minimum characters** before a search fires.
- **Import / export data** — upload a CSV (first row = column names; unknown
  columns ignored, blank cells stored as NULL) to replace the current rows; the
  uploaded file is then deleted. Export downloads all rows as CSV. A live row
  count is shown. Embedded commas/quotes are handled via `fgetcsv`/`fputcsv`.

**Security.** Column identifiers are never taken from user input: admin-chosen
columns are validated against a fixed whitelist (`Lookup\Columns`) before being
back-ticked, and the search term is bound via `$wpdb->prepare()`. The public
search endpoint is read-only and nonce-free by design, so it survives full-page
caching.

Key classes live under `src/Lookup/`: `LookupBootstrap` (wiring + activation),
`TownSchema` (table + import), `TownRepository` (search), `LookupController`
(shortcode + AJAX + assets), `SettingsPage` (admin), `LookupSettings` and
`Columns` (config + whitelist).

## Architecture

- **`lifelines.php`** — plugin header, constants (`LIFELINES_VERSION`,
  `LIFELINES_PLUGIN_DIR`, `LIFELINES_PLUGIN_URL`), the `LIFELINES_KILL` kill
  switch, a PSR-4 autoloader for the `LifeLines\` namespace, and registration of
  the lookup subsystem on the `plugins_loaded` and activation hooks.
- **`LifeLines\Lookup\LookupBootstrap`** — wires the subsystem together and runs
  activation (install table, import data, create the public page).
- **`LifeLines\Lookup\TownSchema`** — the single source of truth for the
  `wp_life_lines` table: it creates the schema (via `dbDelta`), imports row data
  from an uploaded CSV (batched inserts; header matched against `Columns`), and
  exports the table to CSV.
- **`LifeLines\Lookup\TownRepository`** — the partial-match search query.
- **`LifeLines\Lookup\LookupController`** — the `[lifelines_lookup]` shortcode,
  front-end assets, and the public AJAX search endpoint.
- **`LifeLines\Lookup\SettingsPage`** — the admin settings screen.
- **`LifeLines\Lookup\LookupSettings`** / **`Columns`** — configuration and the
  fixed column whitelist that keeps identifiers safe.

## Kill Switch

Stand the plugin down without deactivating it by adding this to `wp-config.php`:

```php
define('LIFELINES_KILL', true);
```

## Extending LifeLines

1. Add a class under `src/` (namespace `LifeLines\…`).
2. Hook it up from `LookupBootstrap::register()` (or add a new bootstrap wired in
   from `lifelines.php`).
3. To broaden the dataset, extend the whitelist in `Lookup\Columns` and the table
   definition in `Lookup\TownSchema`.

## Building for Production

```bash
composer install
composer build          # → build/lifelines.zip (production)
composer build:dev      # includes tests
composer build:clean    # clean the build directory
```

## License

MIT (Modified) — see [LICENSE](LICENSE). The licensee may not sell the Software,
alone or as part of an aggregate software distribution containing the Software.

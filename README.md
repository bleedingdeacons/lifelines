# LifeLines

**A standalone real-time lookup tool for UK place, service and helpline data.**

LifeLines imports a bundled UK dataset (~43k rows of place / service / helpline
records) into its own table and exposes a fast, public **smart lookup**:
partial-match search across admin-configurable columns, with results rendered in
real time as you type. It is self-contained — it has **no plugin dependencies**
and registers entirely on core WordPress hooks.

**Version:** 1.0.1
**Requires:** WordPress 6.1+ · PHP 8.1+
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
4. Load your data: on **LifeLines → Smart Lookup**, upload a `.sql` dump of the
   `life_lines` table (see [Smart Lookup](#smart-lookup)).

## Smart Lookup

LifeLines provides a self-contained, real-time lookup over a UK dataset (place /
service / helpline data) that you import into its `wp_life_lines` table.
No data is bundled with the plugin — you upload a `.sql` dump of the `life_lines`
table from the admin screen. It is fully **self-contained** — no plugin
dependencies; it registers on core WordPress hooks.

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
- **Import data** — upload a `.sql` dump; it is imported and then the uploaded
  file is deleted. A live row count is shown.

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
  `wp_life_lines` table: it creates the schema (via `dbDelta`) and imports the row
  data from an uploaded `.sql` dump. The dump needs data only — only `life_lines`
  INSERTs are executed, no `CREATE TABLE`.
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

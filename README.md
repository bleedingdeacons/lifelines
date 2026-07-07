# LifeLines

**An intergroup management plugin built on Unity.**

LifeLines is a companion plugin to [Unity](https://github.com/bleedingdeacons/unity),
scaffolded from the same conventions as the rest of the Bleeding Deacons
intergroup suite. It ships as a clean, wired-up skeleton — a namespaced PSR-4
autoloader, a kill switch, a service provider registered against Unity's
container, and a `lifelines/loaded` action — ready for feature development.

**Version:** 1.0.0
**Requires:** WordPress 6.1+ · PHP 8.1+
**Dependencies:** Unity
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
- The **Unity** plugin, installed and activated (provides member data and the DI
  container LifeLines registers against)

## Installation

1. Ensure the **Unity** plugin is installed and activated.
2. Build the `lifelines.zip` archive (`composer build`) or clone this repository
   into `wp-content/plugins/lifelines`.
3. In WordPress, go to **Plugins → Add New → Upload Plugin**, or activate it from
   the Plugins screen if cloned.

## Smart Lookup

LifeLines ships a self-contained, real-time lookup over the bundled UK dataset
(`data/uk.sql` → the `wp_lifelines_uk_towns` table, ~43k rows of place / service /
helpline data). It is **independent of Unity** — it registers on core WordPress
hooks, so the public lookup works whether or not Unity is active.

**Public page.** On activation the plugin creates a published **Lookup** page
containing the `[lifelines_lookup]` shortcode (you can also drop that shortcode on
any page). As you type, results are fetched from `admin-ajax.php` and rendered
live, matching **partial values** across the configured columns.

**Admin settings** (*LifeLines* menu → **Smart Lookup**):

- **Searchable columns** — which columns the typed text is partial-matched against.
- **Displayed columns** — which columns (and in what order) appear in the results.
- **Maximum results** and **minimum characters** before a search fires.
- **Re-import data** — rebuild the table from `data/uk.sql`, with a live row count.

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

LifeLines follows the suite's standard shape:

- **`lifelines.php`** — plugin header, constants (`LIFELINES_VERSION`,
  `LIFELINES_PLUGIN_DIR`, `LIFELINES_PLUGIN_URL`), the `LIFELINES_KILL` kill
  switch, a PSR-4 autoloader for the `LifeLines\` namespace, and the
  `unity/loaded` gate that boots the plugin.
- **`LifeLines\Plugin`** — the lifecycle orchestrator. `Plugin::init()` receives
  Unity's container, delegates service registration to the service provider, and
  is where managers and admin services are resolved as the plugin grows.
- **`LifeLines\Core\LifeLinesServiceProvider`** — registers LifeLines services
  in Unity's container, mirroring `Unity\Core\UnityServiceProvider`.
- **`LifeLines\Logger\HasLogger`** — a safe logging trait that no-ops unless the
  shared Sentinel logger mu-plugin is present.

When Unity finishes loading it fires `unity/loaded`; LifeLines initialises and
then fires its own `lifelines/loaded` action, passing the container so downstream
plugins can gate on it.

## Kill Switch

Stand the plugin down without deactivating it by adding this to `wp-config.php`:

```php
define('LIFELINES_KILL', true);
```

## Extending LifeLines

1. Add a class under `src/` (namespace `LifeLines\…`).
2. Register it in `LifeLinesServiceProvider::register()`.
3. Resolve and boot it from `Plugin::init()`.

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

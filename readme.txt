=== LifeLines ===
Contributors: thebleedingdeacons
Tags: intergroup, management, unity
Requires at least: 6.1
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

An intergroup management plugin built on Unity. Scaffold ready for feature development.

== Description ==

**An intergroup management plugin built on Unity.**

LifeLines is a companion plugin to the [Unity](https://github.com/bleedingdeacons/unity)
intergroup management framework. It ships as a clean, wired-up scaffold following
the conventions of the rest of the Bleeding Deacons suite, ready for feature
development.

**Dependencies:** Unity

**Included scaffolding:**

* **Namespaced autoloader** — PSR-4 autoloading for the `LifeLines\` namespace.
* **Kill switch** — `define('LIFELINES_KILL', true)` in `wp-config.php` stands the plugin down without deactivating it.
* **Service provider** — `LifeLinesServiceProvider` registers services in Unity's dependency container.
* **Loaded action** — fires `lifelines/loaded` once Unity is available, gating any downstream plugins.
* **Safe logging** — `HasLogger` trait that no-ops unless Sentinel's shared logger is present.

== Installation ==

= From a .zip archive =

1. Ensure the **Unity** plugin is installed and activated.
2. Build the `lifelines.zip` archive (`composer build`).
3. In WordPress, go to **Plugins → Add New → Upload Plugin**.
4. Upload the archive and activate the plugin.

= From source =

1. Clone this repository into `wp-content/plugins/lifelines`.
2. Run `composer install`.
3. Activate **LifeLines** from the Plugins screen.

== Changelog ==

= 1.0.0 =
* Initial scaffold: autoloader, kill switch, service provider, and `lifelines/loaded` action.

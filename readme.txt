=== QA Kit ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Developer QA tools for local and staging sites: front-end checks, ACF inspector, AI prompt library and a WP-CLI ACF audit. Does nothing on production.

== Description ==

QA Kit adds developer QA tools to local and staging sites and stays inert on production. See README.md for details.

== Changelog ==

= 0.1.2 =
* Fixed the JavaScript error capture so harmless "ResizeObserver loop" browser warnings are no longer reported as errors.
* Changed the plugin author to True Market and added an Author URI.

= 0.1.1 =
* Improved the tap target check: links inside paragraphs and visually hidden (screen-reader-only) elements are no longer flagged, and the first 10 small targets are now listed with their size and selector.
* Improved the navigation label check: aria-labelledby is now resolved to its text, and purely numeric labels are flagged as not describing the nav.
* Changed the QA Kit panel to use plain containers instead of header and footer elements.

= 0.1.0 =
* Initial release.

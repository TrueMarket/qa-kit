=== QA Kit ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.1.6
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Developer QA tools for local and staging sites: front-end checks, ACF inspector, AI prompt library and a WP-CLI ACF audit. Does nothing on production.

== Description ==

QA Kit adds developer QA tools to local and staging sites and stays inert on production. See https://github.com/TrueMarket/qa-kit for details.

== Changelog ==

= 0.1.6 =
* Added a Flexible layout test page to Tools > QA Kit. It builds a private page on the theme's Flexible layout template with a section for every block, block option and section option, read from the template's ACF field group. Options change one at a time from a baseline, and extra sections cover all blocks combined, empty fields and long content.
* Added buttons to rebuild the test page and to delete it along with its placeholder images and PDF.
* Added a warning on production sites when the test page or its placeholder media are still in the database.
* Changed each check's results to list errors first, then warnings, info and passes, in both the panel and the AI report. Grouped rows stay together, and the heading outline keeps page order.

= 0.1.5 =
* Made the QA Kit panel resizable: drag its left edge, or focus the edge and use the arrow keys (hold Shift for bigger steps). The width is remembered between pages, and double-clicking the edge resets it.
* Added a Copy for AI button to each check's tab that copies a report for that check only, running the check first if it has no results yet.
* Renamed "Copy report for AI" to "Copy full report for AI" in the panel and the admin bar menu.
* Changed the fallback for when copying is blocked to show the report text in the current tab instead of switching to Summary.

= 0.1.4 =
* Removed development files (README.md, .gitignore and .gitattributes) from the release zip.
* Simplified the README to cover installing and releasing, and linked to the GitHub repo from readme.txt.

= 0.1.3 =
* Changed updates to download the qa-kit.zip attached to each GitHub release instead of GitHub's source archive. Releases without a zip still fall back to the source archive.
* Added installation instructions to the README, including a WP-CLI command that installs the latest release.

= 0.1.2 =
* Fixed the JavaScript error capture so harmless "ResizeObserver loop" browser warnings are no longer reported as errors.
* Changed the plugin author to True Market and added an Author URI.

= 0.1.1 =
* Improved the tap target check: links inside paragraphs and visually hidden (screen-reader-only) elements are no longer flagged, and the first 10 small targets are now listed with their size and selector.
* Improved the navigation label check: aria-labelledby is now resolved to its text, and purely numeric labels are flagged as not describing the nav.
* Changed the QA Kit panel to use plain containers instead of header and footer elements.

= 0.1.0 =
* Initial release.

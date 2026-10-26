# QA Kit

Developer QA tools for WordPress projects. Ships with the boilerplate, runs on local and staging, and does nothing on production.

## What's in it

| Tool | Where | What it does |
| --- | --- | --- |
| QA toolbar | Admin bar > QA Kit, on any front-end page | Accessibility (axe-core), structure and semantics, SEO, ACF fields, images, performance, errors, breakpoints |
| Copy report for AI | Admin bar > QA Kit, or the panel footer | Runs every check and copies a markdown report with the page's template files, ready to paste into an AI tool |
| Prompt library | Tools > QA Kit | Markdown prompts for code-level QA, with one-click copy |
| ACF audit | Tools > QA Kit | Fields defined but never used, and fields called in code that don't exist |

## Environments

QA Kit only loads when `wp_get_environment_type()` is not `production`. Set this in each site's `wp-config.php`:

```php
define( 'WP_ENVIRONMENT_TYPE', 'staging' ); // or 'local' / 'development'
```

Local by Flywheel sets `local` for you. If the constant is missing, WordPress reports `production` and QA Kit stays off, so staging needs it set.

On production QA Kit stays inert and shows admins a notice to remove it. You can force it on or off with `define( 'QA_KIT_ENABLED', true );`.

Still remove the plugin before launch. It is on the pre-launch checklist.

## Using the prompts

**With an AI agent in the repo (best):**

```
Read wp-content/plugins/qa-kit/prompts/acf.md and run it against the theme.
```

**In a chat:** Tools > QA Kit > Copy prompt, then paste and attach the files it asks for.

**For one page:** on the front end, QA Kit > Copy report for AI, then paste the report into the agent with the theme open.

## Project-specific prompts

Add a `qa-prompts` folder to the theme. Any `.md` file there shows up in the library. A file with the same name as a bundled prompt replaces it.

Prompt files can start with optional front matter:

```markdown
---
order: 20
description: One line shown on the card.
---
# Prompt title

Prompt body...
```

## Filters

```php
// Who can use QA Kit (default manage_options).
add_filter( 'qa_kit_capability', fn() => 'edit_pages' );

// Extra folders for the ACF audit, e.g. a custom plugin.
add_filter( 'qa_kit_scan_paths', function ( $paths ) {
	$paths[] = WP_PLUGIN_DIR . '/client-blocks';
	return $paths;
} );

// Extra prompt folders.
add_filter( 'qa_kit_prompt_dirs', function ( $dirs ) {
	$dirs[] = WP_CONTENT_DIR . '/qa-prompts';
	return $dirs;
} );

// Breakpoints for the preview and overflow checks.
add_filter( 'qa_kit_breakpoints', fn() => array( 360, 390, 768, 1024, 1280, 1536 ) );
```

## Updates

QA Kit uses the [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) library (bundled in `vendor/`) to check the `TrueMarket/qa-kit` GitHub repo for new tagged releases. New versions appear on the Plugins screen like any wordpress.org plugin.

If the repo is private, each site needs a read-only GitHub token:

1. Create a fine-grained personal access token at GitHub > Settings > Developer settings > Personal access tokens. Scope it to the `qa-kit` repo only, with **Contents: Read-only** permission.
2. Add it to `wp-config.php`:
   ```php
   define( 'QA_KIT_GITHUB_TOKEN', 'github_pat_...' );
   ```

### Releasing a new version

Paste this into Claude Code from the plugin folder:

```
Release a new version of this plugin.

1. Work out what changed since the last release: run `git describe --tags --abbrev=0`
   to find the latest tag, then read `git log` and `git diff` from that tag to HEAD,
   plus any uncommitted changes. If there are no tags yet, use the whole history.
2. Bump the version (patch bump unless I say otherwise). Update the Version header
   and QA_KIT_VERSION in qa-kit.php, and Stable tag in readme.txt, so all three match.
3. Add a new entry at the top of the == Changelog == section in readme.txt, using the
   format `= X.Y.Z =` followed by `* ` bullet points. Keep it a short list of all
   user-facing changes and group changes together where it makes sense, written in the past tense.
4. Show me the new version number and the changelog entry, and wait for my OK
   before committing.
5. Commit everything with the message "Release vX.Y.Z", tag the commit vX.Y.Z,
   and push both main and the tag to origin.
```

For a minor or major release, add "minor bump" or "major bump" to the end of the prompt.

The changelog entries do more than record history. WordPress shows them in the "View version X.Y.Z details" popup on the Plugins screen, because the update checker reads the `== Changelog ==` section of `readme.txt` for the tagged release.

By hand, the steps are: update the three version numbers, add the changelog entry, commit, then `git tag v0.1.1 && git push origin main && git push origin v0.1.1`.

## Notes

- Front-end checks run in the browser, so nothing is sent anywhere. axe-core is bundled in `assets/vendor` (MPL-2.0).
- The panel renders in a shadow root so theme CSS cannot break it.
- Breakpoint checks load the page in hidden iframes with `?qa-kit-frame=1`, which hides the admin bar and toolbar.
- Local timings are not representative. Use PageSpeed Insights on staging for real performance numbers.

## Ideas for later

- Pull prompts from a central repo so older projects get updates.
- Optional direct AI call from the panel (would need an API key setting).
- Playwright sweep across the sitemap for pre-launch.

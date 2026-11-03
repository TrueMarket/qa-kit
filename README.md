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

## Installing

Each [GitHub release](https://github.com/TrueMarket/qa-kit/releases) has a `qa-kit.zip` attached. Use that file, not GitHub's "Download ZIP" or "Source code" links. Those name the folder `qa-kit-main` or `qa-kit-X.Y.Z`, and the site keeps that folder name through every update.

- **In the dashboard:** download `qa-kit.zip` from the latest release, then go to Plugins > Add New > Upload Plugin.
- **With WP-CLI:**
  ```
  wp plugin install https://github.com/TrueMarket/qa-kit/releases/latest/download/qa-kit.zip --activate
  ```

Remember to set `WP_ENVIRONMENT_TYPE` on staging (see Environments above).

## Updates

QA Kit uses the [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) library (bundled in `vendor/`) to check the `TrueMarket/qa-kit` GitHub repo for new releases. New versions appear on the Plugins screen like any wordpress.org plugin. Updates download the `qa-kit.zip` attached to the release. If a release has no zip, the update checker falls back to GitHub's source archive.

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
6. Build the release zip from the tag:
   `git archive --format=zip --prefix=qa-kit/ -o qa-kit.zip vX.Y.Z`
   Check that every path in it starts with qa-kit/ and that there is no .git folder.
7. Create the GitHub release with the zip attached, using the changelog bullets
   as the notes:
   `gh release create vX.Y.Z qa-kit.zip --title "vX.Y.Z" --notes-file <file with the bullets>`
   The asset must be named exactly qa-kit.zip so the
   releases/latest/download/qa-kit.zip link keeps working.
8. Delete the local qa-kit.zip and any notes file, then give me the release URL.
```

For a minor or major release, add "minor bump" or "major bump" to the end of the prompt.

Steps 7 and 8 need the GitHub CLI (`gh`), installed and logged in with an account that can write to `TrueMarket/qa-kit`. See "Setting up the GitHub CLI" below.

The changelog entries do more than record history. WordPress shows them in the "View version X.Y.Z details" popup on the Plugins screen, because the update checker reads the `== Changelog ==` section of `readme.txt` for the tagged release.

By hand, the steps are:

1. Update the three version numbers and add the changelog entry.
2. Commit, tag and push:
   ```
   git commit -am "Release vX.Y.Z"
   git tag vX.Y.Z
   git push origin main
   git push origin vX.Y.Z
   ```
3. Build the zip: `git archive --format=zip --prefix=qa-kit/ -o qa-kit.zip vX.Y.Z`
4. Create the release, either with `gh release create vX.Y.Z qa-kit.zip --title "vX.Y.Z" --notes "..."`, or on GitHub: Releases > Draft a new release, choose the tag, paste the changelog bullets, and drag `qa-kit.zip` into the attachments area.

### Setting up the GitHub CLI

This only needs doing once per computer.

1. Install it. On Windows: `winget install --id GitHub.cli`. On macOS: `brew install gh`.
2. Restart VS Code (or your terminal) so it finds the new `gh` command.
3. Run `gh auth login` in a terminal and choose: GitHub.com, HTTPS, "Login with a web browser". If it asks whether to authenticate Git with your GitHub credentials, yes is fine.
4. Check it worked: `gh auth status`, then `gh repo view TrueMarket/qa-kit`.

If `gh repo view` fails with a permissions error, the TrueMarket organization may need to approve the GitHub CLI: go to github.com > Settings > Applications > Authorized OAuth Apps > GitHub CLI and request or grant access for TrueMarket.

## Notes

- Front-end checks run in the browser, so nothing is sent anywhere. axe-core is bundled in `assets/vendor` (MPL-2.0).
- The panel renders in a shadow root so theme CSS cannot break it.
- Breakpoint checks load the page in hidden iframes with `?qa-kit-frame=1`, which hides the admin bar and toolbar.
- Local timings are not representative. Use PageSpeed Insights on staging for real performance numbers.

## Ideas for later

- Pull prompts from a central repo so older projects get updates.
- Optional direct AI call from the panel (would need an API key setting).
- Playwright sweep across the sitemap for pre-launch.

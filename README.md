# QA Kit

Developer QA tools for WordPress projects. Ships with the boilerplate, runs on local and staging, and does nothing on production.

## Installing

Download `qa-kit.zip` from the [latest release](https://github.com/TrueMarket/qa-kit/releases/latest) and upload it at Plugins > Add New > Upload Plugin. Don't use GitHub's "Download ZIP" or "Source code" links, because they give the plugin folder the wrong name.

Or with WP-CLI:

```
wp plugin install https://github.com/TrueMarket/qa-kit/releases/latest/download/qa-kit.zip --activate
```

Staging sites need `define( 'WP_ENVIRONMENT_TYPE', 'staging' );` in `wp-config.php`, or QA Kit stays off. Local by Flywheel sets this for you.

Updates appear on the Plugins screen like any other plugin.

## Releasing a new version

Paste this into Claude Code from the plugin folder:

```
Release a new version of this plugin.

1. Find what changed since the last tag (`git describe --tags --abbrev=0`), using
   `git log` and `git diff`, plus any uncommitted changes.
2. Bump the version (patch unless I say otherwise) in the Version header and
   QA_KIT_VERSION in qa-kit.php, and Stable tag in readme.txt, so all three match.
3. Add an entry at the top of == Changelog == in readme.txt: `= X.Y.Z =` followed by
   short `* ` bullets of the user-facing changes, grouped where it makes sense,
   in the past tense.
4. Show me the version and changelog entry, and wait for my OK before committing.
5. Commit as "Release vX.Y.Z", tag vX.Y.Z, and push main and the tag to origin.
6. Build the zip: `git archive --format=zip --prefix=qa-kit/ -o qa-kit.zip vX.Y.Z`
7. Run `gh release create` for vX.Y.Z, titled vX.Y.Z, with the changelog bullets
   as notes and qa-kit.zip attached. Keep the name qa-kit.zip so the install link works.
8. Delete the local zip and give me the release URL.
```

For a minor or major release, add "minor bump" or "major bump" to the end of the prompt. This needs the GitHub CLI (`gh`), logged in to an account that can write to the repo.

## Ideas for later

- Pull prompts from a central repo so older projects get updates.
- Optional direct AI call from the panel (would need an API key setting).
- Playwright sweep across the sitemap for pre-launch.

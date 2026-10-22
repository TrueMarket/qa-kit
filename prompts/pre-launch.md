---
order: 11
description: Everything to verify before and right after go-live.
---
# Pre-launch review

Review the project for launch readiness. Some items need the live site, so list those in **Not checked** with how to verify them.

Check:

- **Indexing:** staging `noindex` and "Discourage search engines" are off for production. `robots.txt` and the XML sitemap are correct.
- **URLs:** no hardcoded staging or local domains in templates, ACF values, CSS or JS. Search-replace done on the database.
- **Redirects:** 301s from old URLs are in place (Redirection plugin, server config or code). Spot-check the top pages from the old site.
- **Environment:** `WP_ENVIRONMENT_TYPE` is `production`, `WP_DEBUG` is off, dev plugins (including QA Kit) are removed.
- **Forms:** submissions work, notification emails arrive (SMTP configured), spam protection is on.
- **Tracking and consent:** analytics or GTM installed once, cookie consent configured if required.
- **Caching and CDN:** page cache, object cache and CDN enabled and purged.
- **Basics:** favicon, 404 page, search results page, social sharing image, SSL with no mixed content.
- **Backups and monitoring:** backups scheduled and tested, uptime monitoring on.

## Report format

Return one markdown table, most severe first:

| Severity | Issue | Location | Fix |
| --- | --- | --- | --- |

- **Severity:** Critical (broken or blocks users), High, Medium, Low.
- **Location:** `path/to/file.php:line`, relative to the theme or plugin root.
- **Fix:** the specific change, with a short code snippet when it helps.

After the table, add a short **Not checked** list for anything you could not verify from the code alone. Do not edit files unless asked. Propose changes instead.

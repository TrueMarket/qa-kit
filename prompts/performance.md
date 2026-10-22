---
order: 6
description: Asset loading, images, fonts, queries and caching.
---
# Performance review

Review the theme and custom plugins for front-end and back-end performance.

Check:

- **Scripts and styles:** enqueued with `wp_enqueue_script`/`wp_enqueue_style`, versioned, scripts in the footer or deferred, and only loaded on pages that need them. No jQuery dependency unless required.
- **Images:** `wp_get_attachment_image()` with correct sizes registered via `add_image_size()`. The hero image is not lazy-loaded and has `fetchpriority="high"`. Everything below the fold is lazy.
- **Fonts:** self-hosted or preconnected, `font-display: swap`, only the weights in use, `preload` for the main font.
- **Queries:** no queries inside loops, no `posts_per_page => -1` on unbounded content, `no_found_rows` when pagination is not needed, `meta_query` on unindexed values flagged. Expensive results cached with transients.
- **Third parties:** embeds (YouTube, maps) use a facade or lazy loading. Tracking scripts load async.
- **CSS:** no huge unused frameworks, critical CSS considered, no `@import` chains.
- **Layout shift:** images, embeds and ad slots reserve space.

## Report format

Return one markdown table, most severe first:

| Severity | Issue | Location | Fix |
| --- | --- | --- | --- |

- **Severity:** Critical (broken or blocks users), High, Medium, Low.
- **Location:** `path/to/file.php:line`, relative to the theme or plugin root.
- **Fix:** the specific change, with a short code snippet when it helps.

After the table, add a short **Not checked** list for anything you could not verify from the code alone. Do not edit files unless asked. Propose changes instead.

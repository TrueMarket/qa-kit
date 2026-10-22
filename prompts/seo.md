---
order: 5
description: On-page SEO in templates: titles, meta, headings, schema, images and links.
---
# SEO review

Review the theme for on-page SEO. Note which SEO plugin is active (Yoast, Rank Math, SEOPress or none) and do not duplicate what it already handles.

Check:

- `add_theme_support( 'title-tag' )` is used and the theme does not hardcode `<title>`.
- Meta description, canonical and Open Graph tags come from the SEO plugin or the theme, not both.
- One H1 per template, matching the page topic. Archive, search and 404 templates have sensible H1s.
- Images use `wp_get_attachment_image()` with real alt text and sensible sizes.
- Structured data: JSON-LD for the organisation, breadcrumbs, articles, events, products or FAQs where relevant. No duplicate schema from theme and plugin.
- Custom post types and taxonomies have `public`, `has_archive` and `rewrite` slugs set intentionally, and appear in the XML sitemap only when they should.
- Internal links use `get_permalink()`, not hardcoded URLs. No hardcoded staging domains.
- Pagination, search results and thin archives are handled (`noindex` where appropriate).
- Breadcrumbs exist and match the URL structure.

## Report format

Return one markdown table, most severe first:

| Severity | Issue | Location | Fix |
| --- | --- | --- | --- |

- **Severity:** Critical (broken or blocks users), High, Medium, Low.
- **Location:** `path/to/file.php:line`, relative to the theme or plugin root.
- **Fix:** the specific change, with a short code snippet when it helps.

After the table, add a short **Not checked** list for anything you could not verify from the code alone. Do not edit files unless asked. Propose changes instead.

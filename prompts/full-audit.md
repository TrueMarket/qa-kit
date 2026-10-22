---
order: 1
description: Runs every check below in one pass. Best with an AI agent that can read the whole repo.
---
# Full QA audit

You are a senior WordPress developer doing QA on this project before it goes to the client. Review the custom theme and any custom plugins (skip `vendor`, `node_modules` and third-party plugins).

Work through each area and report findings for all of them in a single table:

1. **Accessibility:** WCAG 2.2 AA issues visible in the templates and CSS.
2. **Semantic HTML:** landmarks, heading order, lists, buttons vs links.
3. **ACF:** every field is output, escaped and handles empty values.
4. **SEO:** titles, meta, headings, structured data, image alt text.
5. **Performance:** asset loading, image handling, expensive queries.
6. **Responsive:** fixed widths, overflow risks, missing breakpoints.
7. **Security:** escaping, sanitizing, nonces, capability checks.
8. **Editor experience:** block styles, field instructions, sensible defaults.

Start by listing the templates, template parts and ACF field groups you found, so I can confirm the scope. Then audit.

## Report format

Return one markdown table, most severe first:

| Severity | Issue | Location | Fix |
| --- | --- | --- | --- |

- **Severity:** Critical (broken or blocks users), High, Medium, Low.
- **Location:** `path/to/file.php:line`, relative to the theme or plugin root.
- **Fix:** the specific change, with a short code snippet when it helps.

After the table, add a short **Not checked** list for anything you could not verify from the code alone. Do not edit files unless asked. Propose changes instead.

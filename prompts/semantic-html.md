---
order: 3
description: Landmarks, heading hierarchy, lists and the right element for the job.
---
# Semantic HTML review

Review the theme templates and template parts for semantic HTML.

Check:

- One `<main>`, with `<header>`, `<nav>`, `<footer>` and `<aside>` used for their real purpose. Multiple `<nav>` elements have `aria-label`.
- Heading hierarchy: one H1 per page, no skipped levels, headings not used just for styling. Watch for ACF "heading" fields hardcoded to one level in reusable components.
- `<section>` and `<article>` used where content has a heading and stands alone, not as generic wrappers.
- Lists of things (cards, team members, logos, features) are `<ul>`/`<ol>`.
- `<button>` for actions, `<a>` for navigation. No clickable `<div>` or `<span>`.
- Tables only for tabular data, with `<th>` and `scope`.
- `<figure>`/`<figcaption>`, `<time datetime>`, `<address>`, `<blockquote>`/`<cite>` where they fit.
- Unnecessary wrapper `<div>`s and stacked `<br>` tags used for spacing.

## Report format

Return one markdown table, most severe first:

| Severity | Issue | Location | Fix |
| --- | --- | --- | --- |

- **Severity:** Critical (broken or blocks users), High, Medium, Low.
- **Location:** `path/to/file.php:line`, relative to the theme or plugin root.
- **Fix:** the specific change, with a short code snippet when it helps.

After the table, add a short **Not checked** list for anything you could not verify from the code alone. Do not edit files unless asked. Propose changes instead.

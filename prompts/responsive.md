---
order: 7
description: CSS and markup issues that break layouts at common breakpoints.
---
# Responsive review

Review the theme CSS (or SCSS source) and templates for responsive layout issues at 375, 768, 1024 and 1440px.

Check:

- Fixed pixel widths or heights on containers, images and embeds that cause overflow on small screens.
- Long words, URLs and email addresses that do not wrap (`overflow-wrap: anywhere`).
- Tables, code blocks and iframes without a scroll wrapper or `max-width: 100%`.
- Navigation: mobile menu works, is reachable by keyboard, and the breakpoint where it switches is consistent.
- Tap targets at least 24x24px (44x44px preferred) with spacing between them.
- Body text at least 16px on mobile. No text smaller than 12px.
- `100vh` on mobile causing content to hide behind browser UI (use `100dvh` or `min-height`).
- Hover-only interactions with no touch equivalent.
- Breakpoints consistent across components, mobile first where possible.
- Images use `srcset`/`sizes` so mobile does not download desktop images.

If I give you a QA Kit report with Breakpoints results, start with the overflowing elements it lists.

## Report format

Return one markdown table, most severe first:

| Severity | Issue | Location | Fix |
| --- | --- | --- | --- |

- **Severity:** Critical (broken or blocks users), High, Medium, Low.
- **Location:** `path/to/file.php:line`, relative to the theme or plugin root.
- **Fix:** the specific change, with a short code snippet when it helps.

After the table, add a short **Not checked** list for anything you could not verify from the code alone. Do not edit files unless asked. Propose changes instead.

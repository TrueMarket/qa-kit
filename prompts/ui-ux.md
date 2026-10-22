---
order: 8
description: Consistency, states and edge cases in components and templates.
---
# UI and UX review

Review the theme components and templates for consistency and missing states.

Check:

- **States:** hover, focus, active and disabled styles on links, buttons and form fields. Loading, empty, error and success states for forms, filters, search and AJAX content.
- **Edge cases:** very long titles and names, missing featured images, a single item in a slider or grid, zero search results, empty archives, pagination on the last page.
- **Forms:** clear labels, inline validation messages, a confirmation after submit, and sensible spam protection.
- **Consistency:** buttons, spacing, colours and type follow the same tokens or variables. Flag one-off values in the CSS.
- **Navigation:** current page is indicated, menus handle many items and long labels, the 404 page helps people find their way.
- **Feedback:** external links and file downloads are obvious. Modals and cookie banners do not block content on mobile.

## Report format

Return one markdown table, most severe first:

| Severity | Issue | Location | Fix |
| --- | --- | --- | --- |

- **Severity:** Critical (broken or blocks users), High, Medium, Low.
- **Location:** `path/to/file.php:line`, relative to the theme or plugin root.
- **Fix:** the specific change, with a short code snippet when it helps.

After the table, add a short **Not checked** list for anything you could not verify from the code alone. Do not edit files unless asked. Propose changes instead.

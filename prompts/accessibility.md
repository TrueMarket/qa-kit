---
order: 2
description: WCAG 2.2 AA review of templates, components and CSS.
---
# Accessibility review

Review the theme templates, template parts, JavaScript and CSS for WCAG 2.2 AA issues.

Check:

- Images: meaningful `alt` from the media library or ACF image field, `alt=""` only for decorative images.
- Links and buttons: real `<a href>` for navigation, `<button>` for actions, descriptive text (no bare "Read more" without context or `aria-label`).
- Keyboard: menus, dropdowns, modals, sliders, tabs and accordions work with Tab, Enter, Space, Escape and arrow keys where expected. Focus is trapped in modals and returned on close.
- Focus styles: never `outline: none` without a visible replacement.
- ARIA: `aria-expanded`, `aria-controls`, `aria-current` kept in sync by the JS. No ARIA where native HTML works.
- Forms: every input has a `<label>`, errors are announced and linked with `aria-describedby`.
- Colour contrast: flag text and UI colours in the CSS that look below 4.5:1 (3:1 for large text and UI).
- Motion: animations respect `prefers-reduced-motion`. Autoplaying carousels or video can be paused.
- Skip link, `lang` attribute, page titles, and visible text matching accessible names.

## Report format

Return one markdown table, most severe first:

| Severity | Issue | Location | Fix |
| --- | --- | --- | --- |

- **Severity:** Critical (broken or blocks users), High, Medium, Low.
- **Location:** `path/to/file.php:line`, relative to the theme or plugin root.
- **Fix:** the specific change, with a short code snippet when it helps.

After the table, add a short **Not checked** list for anything you could not verify from the code alone. Do not edit files unless asked. Propose changes instead.

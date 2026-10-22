---
order: 10
description: How the site feels for the client editing it.
---
# Editor experience review

Review the project from the point of view of the client who will edit content.

Check:

- **Blocks:** custom and ACF blocks have previews, icons, sensible defaults and `supports` set. Editor styles (`add_editor_style` or `theme.json`) make the editor match the front end.
- **Restrictions:** unused core blocks, colours and font sizes are disabled so editors cannot break the design. Patterns exist for common layouts.
- **ACF fields:** clear labels, helpful instructions (image sizes, character limits), required flags where content is needed, conditional logic to hide irrelevant fields, and tabs for long groups.
- **Menus and options:** global content (header, footer, contact details, social links) is editable from one place.
- **Admin clutter:** unneeded admin menus, dashboard widgets and plugin notices are hidden for client roles.
- **Roles:** the client role can do what they need without full admin access.

## Report format

Return one markdown table, most severe first:

| Severity | Issue | Location | Fix |
| --- | --- | --- | --- |

- **Severity:** Critical (broken or blocks users), High, Medium, Low.
- **Location:** `path/to/file.php:line`, relative to the theme or plugin root.
- **Fix:** the specific change, with a short code snippet when it helps.

After the table, add a short **Not checked** list for anything you could not verify from the code alone. Do not edit files unless asked. Propose changes instead.

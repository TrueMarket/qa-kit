---
order: 9
description: Escaping, sanitizing, nonces and capability checks in custom code.
---
# Security review

Review the custom theme and custom plugins for common WordPress security issues.

Check:

- **Output:** everything echoed is escaped late with `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` or `wp_json_encode`. Includes ACF values, `$_GET` values reflected into pages, and `get_the_title()` inside attributes.
- **Input:** `$_GET`, `$_POST` and `$_REQUEST` sanitized with the right function (`sanitize_text_field`, `absint`, `sanitize_email`, etc.) and `wp_unslash` applied.
- **Nonces:** every form, AJAX action and REST write endpoint verifies a nonce.
- **Capabilities:** `current_user_can()` on admin actions, AJAX handlers (`wp_ajax_nopriv_` especially) and REST `permission_callback` (never `__return_true` on writes).
- **Database:** `$wpdb->prepare()` for every query with variables.
- **Files:** uploads validated by type and size. No `include` with user input.
- **Secrets:** no API keys, passwords or tokens in the repo or in front-end JS.
- **Leaks:** debug output, `var_dump`, `error_log` of sensitive data, and directory listings.

## Report format

Return one markdown table, most severe first:

| Severity | Issue | Location | Fix |
| --- | --- | --- | --- |

- **Severity:** Critical (broken or blocks users), High, Medium, Low.
- **Location:** `path/to/file.php:line`, relative to the theme or plugin root.
- **Fix:** the specific change, with a short code snippet when it helps.

After the table, add a short **Not checked** list for anything you could not verify from the code alone. Do not edit files unless asked. Propose changes instead.

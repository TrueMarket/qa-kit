---
order: 4
description: Every ACF field is output, escaped and handles empty values. Run the ACF audit in Tools > QA Kit first.
---
# ACF fields review

Check that every ACF field is hooked up correctly in the theme.

First, find the field definitions (`acf-json` folder, `acf_add_local_field_group()` calls, or ask me to paste the ACF audit from Tools > QA Kit > Copy audit as markdown). Then map each field group to the templates and blocks that use it.

Check:

- **Wiring:** every field is output somewhere. Every `get_field()`, `the_field()`, `have_rows()` and `get_sub_field()` call matches a real field name and the right context (post, `'option'`, `'term_ID'`, block).
- **Empty values:** templates do not print empty wrappers, headings, buttons or broken images when a field is empty. Repeaters and flexible content with zero rows output nothing.
- **Escaping:** `esc_html()`, `esc_attr()`, `esc_url()` or `wp_kses_post()` on every output. No bare `the_field()` on text that could hold HTML. WYSIWYG fields use `wp_kses_post()`.
- **Return formats:** image, file, link, post object and relationship fields are handled according to their return format (array vs ID vs URL). Link fields use `target` and `title`.
- **Images:** output with `wp_get_attachment_image()` so `srcset`, `sizes`, `alt`, `width` and `height` are included.
- **Options pages:** global fields (header, footer, social links) read with `'option'`.
- **Field setup:** required flags, defaults, instructions and conditional logic make sense for the content editor.

## Report format

Return one markdown table, most severe first:

| Severity | Issue | Location | Fix |
| --- | --- | --- | --- |

- **Severity:** Critical (broken or blocks users), High, Medium, Low.
- **Location:** `path/to/file.php:line`, relative to the theme or plugin root.
- **Fix:** the specific change, with a short code snippet when it helps.

After the table, add a short **Not checked** list for anything you could not verify from the code alone. Do not edit files unless asked. Propose changes instead.

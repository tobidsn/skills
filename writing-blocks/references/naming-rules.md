# Naming rules

## Slug sanitization

Apply in order:
1. Lowercase the Figma frame name.
2. Replace any run of non-alphanumeric characters with a single hyphen (`-`).
3. Trim leading and trailing hyphens.
4. If empty after step 3, the name is **generic** (see below).
5. Truncate to 60 characters.

| Figma name        | Slug          |
|-------------------|---------------|
| `Header`          | `header`      |
| `Hero / Top`      | `hero-top`    |
| `About Section`   | `about-section` |
| `🎾 FAQ`          | `faq`         |
| `Frame 27`        | (generic — ask) |
| ` `               | (generic — ask) |

## Generic-name detection

A frame name is **generic** if any of these match (case-insensitive, after sanitization):

- Empty string
- Matches regex `^(frame|group|rectangle|ellipse|component|instance)-?\d+$`
- Matches `^untitled-?\d*$`
- Collides with a slug already produced in the current run (duplicate)

When a name is generic, the skill MUST ask the user for an explicit slug before writing any files for that block. Suggested prompt:

> "Frame `<original name>` doesn't have a meaningful name. What slug should this block use?"

## Slug rules for user-provided values

If the user supplies a slug (after a generic-name prompt), apply the same sanitizer to their input. If the result is still generic, re-prompt.

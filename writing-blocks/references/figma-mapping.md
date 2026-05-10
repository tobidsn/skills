# Figma → block schema mapping

The skill walks a Figma node tree and assigns each leaf or named child to a content slot in the block schema. This file specifies the rules.

## Node-type → schema-type table

| Figma node type         | When                                                | Schema type | Slot name source        |
|-------------------------|-----------------------------------------------------|-------------|-------------------------|
| `TEXT`                  | Single line, ≤ 80 chars                             | `string`    | Frame name or text role |
| `TEXT`                  | Multi-line OR > 80 chars                            | `richtext`  | Frame name or text role |
| `RECTANGLE` / `ELLIPSE` | `fills[0].type == "IMAGE"`                          | `image`     | Frame name              |
| `INSTANCE`              | `mainComponent.name` matches a button-like name¹    | `object` with fields `{label: string, url: string}` | Frame name (e.g. `cta`) |
| `INSTANCE`              | `mainComponent.name` matches an icon-like name²     | `image`     | Frame name              |
| `INSTANCE`              | Anything else                                       | `object` (component reference) | Component name |
| `FRAME` (with children) | Children are repeated similar shapes (≥ 2 siblings, same node-type signature) | `array` with derived `item` schema | Frame name (pluralized if singular) |
| `FRAME` (with children) | Children are heterogeneous                          | `object` whose fields are recursively mapped | Frame name |
| `VECTOR` / `BOOLEAN_OPERATION` | Always                                       | `image` (export as SVG asset) | Frame name             |

¹ Button-like: name matches `/(button|btn|cta)/i`.
² Icon-like: name matches `/(icon|arrow|chevron|caret|check|close|menu)/i`.

## Slot-name normalization

After deriving a slot name from a Figma frame name, apply the slug sanitizer (see `naming-rules.md`) and additionally:
- Convert hyphens to underscores (slot names are snake_case, not kebab-case).
- If the result collides with another slot in the same block, append `_2`, `_3`, etc.

Examples:
- Frame `Hero Image` → slot `hero_image`
- Frame `Title` → slot `title`
- Frame `Book Now` → slot `book_now`

## Token detection

A node "binds a token" when `boundVariables` (returned by `get_design_context` / `get_variable_defs`) maps a property to a variable. Token role names in `block.md`'s `tokens:` block are derived from the bound property:

| Bound property                      | Token role        |
|-------------------------------------|-------------------|
| `fills` on a frame background       | `background`      |
| `fills` on a TEXT node              | `text-color`      |
| `strokes`                           | `border-color`    |
| `cornerRadius`                      | `radius`          |
| `itemSpacing`                       | `gap`             |
| `paddingTop` / `paddingLeft` / etc. | `padding`         |

Only tokens that are actually bound on the extracted node are emitted. Non-tokenized values (raw hex, raw px) are NOT emitted as `tokens:` entries — they appear in `layout:` instead.

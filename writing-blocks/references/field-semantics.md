# Field semantics

This file specifies every field the skill may emit, when it's emitted, and how its value is derived.

**Strict rule:** if a field cannot be derived with confidence from Figma data, do not emit it. Never fabricate, never write `TODO` placeholders.

## block.md fields

### `source:` (always emit)
- `figma_file`: the `fileKey` parsed from the URL.
- `figma_node`: the `nodeId` of the extracted frame, formatted as `"NNNN:NNNN"` (string, quoted).

### `dimensions:` (always emit)
- `width`: integer pixels from the frame's `absoluteBoundingBox.width`.
- `height`: integer pixels from the frame's `absoluteBoundingBox.height`.

### `layout:` (always emit)
- `mode`: one of `horizontal`, `vertical`, `grid`, `absolute`. Map from `layoutMode`:
  - `HORIZONTAL` → `horizontal`
  - `VERTICAL` → `vertical`
  - `GRID` → `grid`
  - `NONE` (or unset) → `absolute`
- `direction`: emit only when `mode` is `horizontal` or `vertical` (`row` / `column` respectively).
- `align`: from `primaryAxisAlignItems` lowercased (`min` → `start`, `max` → `end`, `center`, `space_between`).
- `gap`: from `itemSpacing` (omit if zero or absent).
- `padding`: from `paddingTop/Right/Bottom/Left` as `{ top, right, bottom, left }` (omit if all zero).
- `background`: emit if a fill or background variable is bound. Use a token name (`<var-name>`) if bound, otherwise the resolved hex.

### `tokens:` (omit if empty)
Map of `{role: var-name}` based on bound variables (see `figma-mapping.md`'s token table). Emit only roles where a variable is actually bound.

### `content:` (omit if empty)
Ordered list of slots, each with a typed schema. Slot order follows Figma render order (top-to-bottom, then left-to-right). Each entry is one of:
- `<slot>: { type: string }`
- `<slot>: { type: richtext }`
- `<slot>: { type: image }`
- `<slot>: { type: object, fields: [<field>: <type>, ...] }`
- `<slot>: { type: array, item: { <field>: <type>, ... } }`

See `figma-mapping.md` for the full type derivation table.

### `components:` (omit if empty)
List of component instances referenced inside the frame, formatted as:
```
- <ComponentName> (instance of <component-key>)
```
Where `<component-key>` is the component's `key` from Figma (or `local` if local). Use the component's `name` for `<ComponentName>`.

## example.json fields

`example.json` mirrors the slot schema in `block.md` and provides values:

| Schema type | Example value source                                         |
|-------------|--------------------------------------------------------------|
| `string`    | The literal `characters` from the Figma TEXT node.           |
| `richtext`  | The literal `characters` (newlines preserved).               |
| `image`     | The relative path to the downloaded asset under `/docs/design/assets/<slug>/`. |
| `object`    | A nested object whose fields each follow the rules above.    |
| `array`     | An array. For each repeating Figma child, produce one item using the item schema. |

Values that cannot be derived from Figma get safe placeholders:
- `string` URLs → `"#"`
- `string` empty Figma text → `""`
- `image` missing → omit the slot from `example.json` entirely (and from `block.md`).

## page.md fields

### `source:` (always emit)
Same shape as `block.md`'s `source:`.

### `route:` (always emit)
Always `route: /` — placeholder for the user to fill in.

### `blocks:` (always emit)
Ordered list of slugs for child blocks, top-to-bottom by Figma `y` position.

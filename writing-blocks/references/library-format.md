# Library entry format

Library entries live at `library/blocks/<slug>.md` and `library/pages/<slug>.md`. The skill reads them as schema templates when handling `generate-block` / `generate-page` invocations.

## Block entry

Same shape as a real `block.md` (see `field-semantics.md`), with two differences:

1. **Omit the `source:` block.** Library entries are templates, not derived artifacts. The skill writes `source: { generated_from: library, library_entry: <slug> }` into the rendered output at runtime.
2. **Optionally include `default_image_dimensions:`.** A map keyed by image-slot name, with values like `1600x900`. Used by the skill when constructing picsum URLs for `example.json`.

Example:

```markdown
# BLOCK: hero

dimensions:
  width: 1440
  height: 720

layout:
  mode: vertical
  align: center

content:
  - title: { type: richtext }
  - description: { type: richtext }
  - hero_image: { type: image }

default_image_dimensions:
  hero_image: 1600x900
```

## Page entry

Same shape as a real `page.md`, with `source:` omitted. Required field: `blocks:` (ordered list of block slugs).

Example:

```markdown
# PAGE: homepage

route: /

blocks:
  - hero
  - feature-grid
  - testimonials
  - cta
  - faq
```

## Authoring custom entries

The skill ships with one block example (`hero.md`) and one page recipe (`homepage.md`) to demonstrate the format. Add new entries by dropping new files into `library/blocks/` or `library/pages/`. The slug is the filename (without `.md`). Slugs must obey `naming-rules.md` (kebab-case, no generics).

When the skill processes a `generate-block` or page-cascade for a slug not in the library, it falls back to LLM inference — so missing library entries are an opportunity, not an error.

---
name: writing-blocks
description: Emit semantic block files (block.md + example.json, plus optional page.md) into /docs/design/ from any of three input sources — a Figma URL, a prompt naming a block to generate, or a prompt naming a page to compose. Also lists existing blocks/pages by slug substring. Use when the user says "extract blocks from this figma <url>", "convert figma to blocks", "generate block <slug>", "make a block called <slug>", "make component <slug>", "make section <slug>", "regenerate block <slug>", "rebuild block <slug>", "generate page <slug>", "make a page called <slug>", "build page <slug>", "find blocks matching <keyword>", "search blocks for <keyword>", "list blocks like <keyword>", or invokes /writing-blocks.
---

# writing-blocks

Three flows, one output contract. Output files use the same shape regardless of input source.

## Sub-command routing

The slash command `/writing-blocks <args>` dispatches based on the first word of `<args>`:

- `figma <url>` → **Figma flow** (extracts blocks from a live Figma node).
- `generate-block <slug> [force] [<freeform context>]` → **Block-generation flow** (no Figma). Skips if the block already exists; pass `force` as the second token to regenerate.
- `generate-page <slug> [with <slug-list>] [<freeform context>]` → **Page-composition flow** (cascading).
- `find <keyword>` → **Find flow** (read-only listing). Empty keyword lists everything.
- Anything else → print this routing hint and stop.

Natural-language phrases are routed identically:
- "extract blocks from this figma…" / "convert figma to blocks" → Figma flow
- "generate block X" / "make a block called X" / "make component X" / "make section X" → block flow (skip-if-exists)
- "regenerate block X" / "rebuild block X" → block flow with `force`
- "generate page X" / "make a page called X" / "build page X" → page flow
- "find blocks matching X" / "search blocks for X" / "list blocks like X" / "show blocks with X in the name" → find flow

## Hard rules (apply to every flow)

- Strict Figma-derived OR strict library/LLM-inferred — never mixed within a single block. Never write `TODO` placeholders.
- ALWAYS fill the templates in `templates/`. Never invent the output shape.
- Output destination is the current working directory's `docs/design/`. Create directories with `mkdir -p`. Never write outside this root.
- On re-run with existing files: print a unified diff vs. new content, then overwrite.
- On any failure: write nothing for that block/page and report the error.

---

## Flow 1: Figma flow

(Unchanged from v1 of this skill, then named `figma-to-blocks`.)

### 1. Parse the URL

Accept these forms:
- `https://figma.com/design/<fileKey>/<fileName>?node-id=<a>-<b>`
- `https://figma.com/design/<fileKey>/branch/<branchKey>/<fileName>?node-id=<a>-<b>` — use `branchKey` as `fileKey`

Extract `fileKey` and convert `node-id=A-B` to `nodeId="A:B"`. Reject any other URL form.

### 2. Inspect the node

Call `mcp__plugin_figma_figma__get_metadata` with `fileKey` and `nodeId`.

### 3. Classify

- **Leaf section** — node has 0 children OR children are content (text/image/instance). Treat as one block.
- **Page-like parent** — node has ≥ 2 child FRAMES whose widths are all within ±10% of the parent's width. Treat each child as a block; also emit a page composition file.
- **Ambiguous** — exactly 1 child frame near parent width, OR child frames vary widely. Ask the user.

### 4. For each target frame

1. **Slug.** Apply `references/naming-rules.md`. Ask user if generic.
2. **Fetch.** Call `mcp__plugin_figma_figma__get_design_context` and `mcp__plugin_figma_figma__get_variable_defs`.
3. **Walk + classify.** Per `references/figma-mapping.md`.
4. **Download assets.** Use `curl -s -o <path>` into `/docs/design/assets/<slug>/`. Halt on any download failure.
5. **Render block.md.** Per `references/field-semantics.md`, fill `templates/block.md.template`. Drop empty optional sections. Set `source: { figma_file, figma_node }`.
6. **Render example.json.** Real Figma text values; asset paths point into `/docs/design/assets/<slug>/`.
7. **Diff + write.**

### 5. For page-like inputs

Sort by `y`, write `pages/<slug>.page.md` from `templates/page.md.template`.

### 6. Summarize

Block slugs emitted, asset count, page file path, prompts asked.

---

## Flow 2: Block-generation flow (`generate-block <slug> [context]`)

### 1. Sanitize slug

Apply `references/naming-rules.md`. If generic → ask the user for an explicit slug.

### 2. Locate the schema

- Look up `library/blocks/<slug>.md`. If found → that's the schema.
- If not found → infer the schema from `<slug> + context`. The inferred schema follows the same shape as library entries (see `references/library-format.md`). Mark it in the run summary as **LLM-generated** so the user knows to review.

### 3. Render block.md

Fill `templates/block.md.template` from the schema. The `source:` block is:

```
source:
  generated_from: <library | llm>
  library_entry: <slug>          # only when generated_from == library
  context: <freeform context>    # only if context was provided
```

Drop optional sections per `references/field-semantics.md`.

### 4. Render example.json

For each slot in the schema:

- `string` / `richtext` → Claude generates appropriate sample copy from the slot name + block context. Keep it short, realistic, English-only.
- `image` → URL `https://picsum.photos/<W>/<H>`. Resolve `<W>x<H>` per `references/placeholder-conventions.md`.
- `object` → nested object whose fields each follow the rules above.
- `array` → produce 2–3 sample items (or as the library entry suggests via comments) using the item schema.

Pretty-print, 2-space indent, trailing newline.

### 5. Write — skip-if-exists by default

Behavior depends on whether the user passed `force` as the second token of the args (after the slug):

- **`force` passed AND existing files present** → print `diff -u <old> <new>` to the conversation, then overwrite both files.
- **`force` NOT passed AND existing files present** → print: `block <slug> already exists at /docs/design/blocks/<slug>/ — pass \`force\` to regenerate`. Write nothing. Exit successfully.
- **No existing files** → write the new files (force is a no-op).

A block "already exists" if EITHER `block.md` OR `example.json` is present at the target path.

The cascading page flow (Flow 3, step 3) already implements skip-if-exists; this brings the direct-invocation path in line.

### 6. Summarize

Slug, source (`library` / `llm`), file paths, image-slot count.

---

## Flow 3: Page-composition flow (`generate-page <slug> [with <slug-list>] [context]`)

### 1. Sanitize slug

Apply `references/naming-rules.md`.

### 2. Determine block list

In order of preference:

- User passed `with a, b, c, …` → use that list verbatim.
- Library recipe at `library/pages/<slug>.md` → use its `blocks:` field.
- Neither → ask the user inline. Suggested prompt: "Which blocks should `<slug>` contain? (comma-separated)".

Sanitize each block slug. Dedupe; warn the user if any slug appeared twice.

### 3. Cascade — generate any missing blocks

For each slug in the list:

- If `/docs/design/blocks/<slug>/block.md` already exists → skip (use as-is).
- Else → recursively run **Flow 2** (`generate-block`) for that slug, passing the page's freeform context as the cascading context. The recursion creates the block files before the page file is written. Each cascaded block independently hits the library if its slug is known there, or falls back to LLM inference if not.

### 4. Render page.md

Fill `templates/page.md.template`:

- `page_slug`: the page slug.
- `figma_file` / `figma_node` in `source:` are **omitted** in the page-flow render. Instead emit:

```
source:
  generated_from: <library | user-list | inline-prompt>
  recipe_entry: <slug>           # only when generated_from == library
  context: <freeform context>    # only if context was provided
```

- `blocks_list`: one indented `- <slug>` per slug in render order.

### 5. Write

Create `/docs/design/pages/<slug>.page.md`. Diff + overwrite if existing.

### 6. Summarize

Page file path, plus a categorized block list:
- **Generated this run:** [slugs that were missing and just cascaded into existence] — annotate each with `(library)` or `(llm)`.
- **Already on disk:** [slugs that were skipped]

---

## Flow 4: Find sub-command (`find <keyword>`)

Read-only flow. No files are written.

### 1. Sanitize keyword

Lowercase and trim. An empty keyword means "list everything".

### 2. Glob the design folder

Look at the current working directory's:
- `docs/design/blocks/*/block.md` — each match is a block, slug = parent directory name
- `docs/design/pages/*.page.md` — each match is a page, slug = filename without `.page.md`

If `docs/design/` doesn't exist, treat both as empty.

### 3. Filter

Keep entries whose lowercased slug contains the keyword as a substring. Empty keyword keeps everything.

### 4. Print

Categorize the output:

```
Blocks:
  <slug> → <absolute-path-to-block.md>
  ...

Pages:
  <slug> → <absolute-path-to-page.md>
  ...
```

If a category has no matches, omit its heading. If no matches at all:

- Empty keyword: `no blocks or pages found`
- Non-empty keyword: `no blocks or pages match <keyword>`

## Output destination

All flows write under the current working directory's `docs/design/`. Create directories as needed. Never write outside this root.

## When to load references

- `references/naming-rules.md` — at slug derivation in every flow.
- `references/figma-mapping.md` — Figma flow only.
- `references/field-semantics.md` — at block.md / example.json rendering in every flow.
- `references/library-format.md` — block-generation and page-composition flows when reading library entries.
- `references/placeholder-conventions.md` — block-generation flow when sizing picsum URLs.

Load each only when needed.

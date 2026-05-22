---
name: mindmap-architect
description: Generate, refine, and visualize interactive mindmaps from various sources (prompts, YouTube videos, PDFs, DOCX, Lark Docs, images, text files, and CSVs). Trigger this skill whenever the user mentions mindmaps, brainstorming, diagrams, visual outlines, visual summaries, mapping notes, concept mapping, or wants to visualize information hierarchically, even if they do not explicitly use the word "mindmap."
---

# Mindmap Architect

A specialized skill to convert messy information (from prompts, files, Lark Docs, or YouTube links) into clean, visual, interactive mindmaps saved into the user's **global vault** (`~/Mindmaps/` by default).

## Storage Model

All mindmaps are written to a single vault — like an Obsidian vault. The vault is shared across every project: invoke this skill from any working directory and the output lands in the same place.

**Vault resolution order:**
1. `--vault <path>` CLI arg
2. `$MINDMAP_VAULT` environment variable
3. Default: `~/Mindmaps/`

**Vault layout:**
```
~/Mindmaps/
  index.html          ← catalog (entry point)
  viewer.css          ← shared per-mindmap styles
  viewer.js           ← shared per-mindmap logic
  serve.py            ← optional preview server
  {slug}/
    index.html        ← thin stub (~1KB)
    mindmap.json      ← canonical data (includes source_path)
    mindmap.md        ← outline export
    export.png        ← PNG export (optional)
```

When you generate a mindmap, the script records the **invocation directory** as `source_path` and its basename as `source_project` — so the catalog can show which project a mindmap came from, even though all data lives in one vault.

## Quality Rules
- **Short Node Labels**: Keep node labels brief (max 25 characters/3-4 words). Avoid paragraphs or sentences in nodes.
- **Detailed Descriptions**: Include a detailed `"description"` string on every node in the mindmap JSON to provide context and elaboration when selected in the visualizer.
- **Clear Hierarchy**: Use a logical grouping from broad concepts to specific details.
- **No Duplicates**: Ensure sibling branches do not have duplicate topics.
- **Maximum 4 Levels Deep**: Keep the mindmap legible by default. Ensure depth is capped at 4 levels.
- **Normalize Wording**: Group messy notes by theme, infer missing parent category nodes, and remove redundancy.

## Standard Flow

Follow these steps exactly for any request:

### Step 1: Parse and Extract Input

Detect the input type and extract raw text:
- **Prompt/Text**: Extract from the user prompt directly.
- **Refine Mindmap**: Look for an existing `{slug}/mindmap.json` in the vault (`~/Mindmaps/` by default), parse it, and improve the organization. To find the slug, you can `ls ~/Mindmaps/` or grep titles in the catalog.
- **YouTube URL**: Use tools or python scripts to fetch subtitles/transcript (or ask the user to provide it if blocked). Save the video URL in `mindmap.json`.
- **Files (PDF, DOCX, TXT, MD, JSON, CSV)**: Read the files. For PDF/DOCX, extract text using python scripts (like standard `pypdf` or standard libraries).
- **Images/Screenshots/Whiteboards**: Use visual capabilities to read text and layout, extracting key headings and conceptual links.
- **Lark Docs**: Run the `lark-cli` export tool:
  ```bash
  lark-cli doc export <doc-id>
  ```
  Then extract content from the exported markdown/text.

### Step 2: Normalize and Structure (JSON format)

Model the extracted text into a hierarchical JSON nodes array.
Structure of each node:
```json
{
  "id": "unique-node-id",
  "label": "Brief Node Title",
  "description": "A detailed 1-2 sentence description explaining the concept or key items of this node.",
  "children": []
}
```
Primary nodes should be split relatively evenly to branch out to both sides.

### Step 3: Build Mindmap Files

Run the python build script `build_mindmap.py` to write the mindmap into the global vault.

Find the location of the templates directory. In the repository structure, they live in the `mindmap-architect/assets/` directory. Find the absolute path to this folder (usually in `~/.agents/skills/mindmap-architect/assets` or `./mindmap-architect/assets`).

```bash
python3 <path-to-skill>/scripts/build_mindmap.py \
  --title "<Mindmap Title>" \
  --summary "<Short summary of the mindmap>" \
  --source-type "prompt | youtube | file | lark | mixed" \
  --source "<Original reference URL or path>" \
  --json-file "<Path to temporary file containing nodes JSON>" \
  --templates-dir "<Path to skill's assets directory>"
```

Optional arguments:
- `--vault <path>` — override vault location (default: `$MINDMAP_VAULT` or `~/Mindmaps/`)
- `--source-path <path>` — explicit invocation directory (default: cwd)
- `--source-project <name>` — friendly project label (default: basename of source-path)

What the script does:
1. Resolves the vault (`~/Mindmaps/` by default) and initializes it if missing — copies `viewer.css`, `viewer.js`, `serve.py`, and the catalog template.
2. Generates a unique slug (e.g. `agentic-ai-workflow`, `agentic-ai-workflow-2` on collision).
3. Captures `source_path` (cwd at invocation) and `source_project` into `mindmap.json` metadata — so the catalog can show which project the mindmap came from.
4. Writes `{vault}/{slug}/mindmap.json`, `mindmap.md`, `README.md`, and a tiny `index.html` stub that references the shared `../viewer.css` and `../viewer.js`.
5. Refreshes `{vault}/index.html` (the catalog) by scanning all subfolders.

Because the viewer is shared, **template changes propagate without regenerating each mindmap's HTML** — just edit `viewer.css` / `viewer.js` in the skill's `assets/` and the next build (or running `init_vault` via any rebuild) will refresh them in the vault.

### Step 4: Generate PNG Screenshot

Resolve `<vault>` first (`~/Mindmaps/` unless `$MINDMAP_VAULT` is set). Then capture the rendered SVG via headless Chrome:

```bash
VAULT="${MINDMAP_VAULT:-$HOME/Mindmaps}"
/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome \
  --headless \
  --screenshot="$VAULT/{slug}/export.png" \
  --window-size=1920,1080 \
  "file://$VAULT/{slug}/index.html"
```
*(Adjust the Chrome path if needed, or use a python screenshot script / the DevTools screenshot MCP tool.)*

Since there's only one vault, no extra copy step is required — the PNG lands directly where the catalog reads it.

### Step 5: Respond to the User

Output the final response matching this template (substitute `<vault>` with the actual vault path):

```
Mindmap generated successfully.

Vault: <vault>

Files:
- <vault>/{slug}/index.html      (thin stub)
- <vault>/{slug}/mindmap.json
- <vault>/{slug}/mindmap.md
- <vault>/{slug}/export.png
- <vault>/index.html             (catalog refreshed)

Shared assets at vault root:
- <vault>/viewer.css
- <vault>/viewer.js
- <vault>/serve.py

Preview:
- Open <vault>/index.html directly, or run: python <vault>/serve.py

Export from the open mindmap:
- PNG via toolbar button
- Markdown via toolbar button
```

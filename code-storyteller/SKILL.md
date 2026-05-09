---
name: code-storyteller
description: >-
  Walk a reader through code one move at a time, like a director's commentary
  track on a screenplay. Produces a single self-contained HTML step-through
  (macOS code window left, serif narrative right, ←/→/space/dots/Prev-Next nav).
  Two modes — the default narrates a flow in the working codebase ("tell the
  story of /api/login", "walk me through checkout", "narrate auth.ts"); the
  `pr <id>` subcommand narrates a PR diff move-by-move via `gh pr diff` ("walk
  me through PR 1234", "/code-storyteller pr 1234"). Works on any language;
  entry-point hints for Express, NestJS, Next.js (App + Pages), FastAPI,
  Laravel, PayloadCMS, Rails, Spring, and Go.
---

# code-storyteller

You are about to generate an HTML story that walks a reader through a code flow one step at a time. Follow this procedure exactly.

## What it produces

A single self-contained HTML file that is a **step-through slideshow**, not a long article. At any moment the reader sees:

- **Left:** a macOS-style code window (traffic lights, filename, dark navy background, syntax-highlighted source, and a "Current type" footer with code chips).
- **Right:** a cream narrative panel: story name (green mono caps) → optional chapter rule → warm-orange `STEP NN` → large serif headline → serif body paragraph → side-by-side **Problem** (orange) and **Move** (green) cards → a closing sans-serif paragraph.
- **Footer rail:** `← Prev` / dots / `N / total` / `Next →`. Keyboard: `←`, `→`, `space`, `Home`, `End`. URL hash `#3` deep-links to step 3.

Each step swaps the code, filename, type signature, headline, body, cards, and closing. The code window is a tape head; the narrative tells you what changed and why.

## Modes

The skill has two modes. Pick the right procedure based on the invocation.

### Default mode — narrate a code flow in the working directory

```
/code-storyteller "tell me the story of /api/login"
/code-storyteller "walk me through the checkout flow" --out docs/stories/
/code-storyteller "narrate auth.ts" --tone casual
```

Use the **default procedure** (Step 1 onward). Trace the codebase, decompose into moves, generate a `STORY` object, render.

### `pr` subcommand — narrate a PR diff

```
/code-storyteller pr 1234
/code-storyteller pr https://github.com/foo/bar/pull/1234
/code-storyteller pr 1234 --tone casual --out docs/pr-stories/
```

Use the **PR procedure** (separate section below). Fetch the diff via `gh`, decompose into moves, render with diff syntax highlighting.

### Recognized flags (both modes)

- `--out <path>` — output location override (file or directory)
- `--tone {technical|casual|tutorial}` — narrative tone (default: `technical`)
- `--no-open` — skip auto-open in browser

## Procedure

### Step 1 — Detect scope

Classify the user's prompt:

| Scope | Trigger keywords |
|-------|------------------|
| `api-flow` | mentions a route path (`/api/...`, HTTP verb + noun like "POST login") |
| `feature-flow` | broad feature ("checkout", "signup flow", "how does X work end-to-end") |
| `module` | a specific file/class/function ("explain auth.ts", "what does AuthService do") |
| `refactor-postmortem` | "how this gnarly function got that way", "walk me through the diff" |

When ambiguous, pick the narrowest match.

### Step 2 — Discover entry point(s)

Read `references/traversal-guide.md` and apply the hints for the stack you detect (look at `package.json`, `composer.json`, `requirements.txt`, `go.mod`, `payload.config.ts`, etc.).

If multiple entry points match: list them and ask the user to pick. If none match: tell the user what you searched for and ask them to point you to the entry point.

### Step 3 — Trace the path & decompose into moves

Follow imports, calls, decorators, hooks, and middlewares from the entry point to the data layer. Read each file fully before moving on. Decompose the flow into **moves** — atomic steps where one decision or transformation happens. Aim for 4–10 steps total. Each move records:

- `filename` — relative path
- `code` — only the relevant lines (do NOT paste whole files; show the few lines that make this move legible)
- `typeline` — one-line type / signature / surface description (e.g. `(req, res, next) => void`, `POST /api/login → RequestHandler`, `AuthService.verify({email, password}) → { token: string }`)
- a short `chapter` label if the flow naturally splits (Setup, Hand-off, Verification, Persistence, etc.) — optional
- a one-sentence headline (the *one idea* of this step)
- a body paragraph framing the situation
- a `Problem` / `Move` pair: the tension and the resolution
- an optional `closing` paragraph that ties this step back into the running narrative

Stop when you reach the data layer (DB query, external API call) or a clear terminal action.

### Step 4 — Read references and the sample

- Read `references/narration-style.md` for tone (default `technical`).
- Read `examples/build-sample.sh` — it is the working reference for the `STORY` object shape and the level of polish to aim for. The rendered version is `examples/sample-story.html`.

### Step 5 — Resolve output path

- If `--out` is given:
  - Ends with `.html` → use it as-is.
  - Otherwise treat as directory; create if missing; auto-generate filename.
- If `--out` is not given: use `.claude-stories/` in the project root.

Auto-generated filename: `YYYY-MM-DD-<slug>.html` where `<slug>` is the prompt converted to kebab-case (lowercase, alphanumeric + hyphen, max 50 chars).

### Step 6 — Generate the HTML

**Important:** `template.html` is ~300 KB (Prism.js + 4 inlined fonts + render engine). DO NOT use the `Read` tool on it — substitute placeholders programmatically by running a Python script via the `Bash` tool.

The template has only **two** placeholders:

| Placeholder | What to inject |
|-------------|----------------|
| `{{TITLE}}` | The HTML page title (used in `<title>`). Usually the story name. |
| `{{STORY_JSON}}` | A JSON object: `{ title, steps: [...] }` — the entire story. |

Skeleton:

```bash
python3 <<'PY'
from pathlib import Path
import json

skill = Path.home() / ".claude/skills/code-storyteller"
src = (skill / "template.html").read_text()

STORY = {
  "title": "EXPRESS · LOGIN FLOW",   # green mono caps shown atop every step
  "steps": [
    {
      "chapter":   "Setup",                         # optional
      "filename":  "routes/auth.ts",
      "code":      "router.post('/login', ...)",     # raw source — Prism highlights it at runtime
      "typeline":  "`authRouter.post(path, ...handlers)` → `Router`",
      "title":     "Wire the pipeline before the controller sees a thing",
      "body":      "When a client posts to `/api/login`, ...",
      "problem":   "Controllers that defensively re-check `req.body` shapes drift toward duplicated guards.",
      "move":      "Push validation up to the middleware layer; the controller assumes shape-correctness.",
      "closing":   "Express resolves the handler array left-to-right, so by listing `validateLoginPayload` first, the route author makes shape-checking a *precondition*, not an afterthought."
    },
    # ... more steps ...
  ],
}

out = (src
  .replace("{{TITLE}}",      STORY["title"])
  .replace("{{STORY_JSON}}", json.dumps(STORY)))

dest = Path("<resolved-path>")
dest.parent.mkdir(parents=True, exist_ok=True)
dest.write_text(out)
print(f"Wrote {dest} ({len(out)} bytes)")
PY
```

#### `STORY.steps[]` schema

Every step must have:
- `filename` — string, relative path shown in window chrome
- `code` — string, raw source lines (do NOT HTML-escape; the runtime sets it via `textContent`, then Prism highlights it)
- `typeline` — string, the "Current type" footer text (backticks become chips)
- `title` — string, the headline (backticks → chips, `*italic*` → italic)
- `body` — string, the framing paragraph (backticks → chips, `*italic*` → italic, `**bold**` → bold)
- `problem` — string, the tension card text
- `move` — string, the resolution card text

Optional:
- `chapter` — string, an uppercase chapter label shown above `STEP NN`. Use sparingly to mark major transitions.
- `closing` — string, a sans-serif paragraph that closes the step. Use it to tie the new code back to the running narrative.
- `lang` — string, Prism language tag (default `typescript`). Set to e.g. `python`, `php`, `go`, `ruby`, `java`, `csharp`, `bash`, `json`, `yaml`, `markdown`, `jsx`, `tsx`, `javascript`.

#### Backticks become chips

In **any** of the strings above (except `code`), `` `Identifier` `` renders at runtime as an inline code chip. Use chips liberally for any identifier the reader should recognize: function names, types, file paths, HTTP verbs, env-var names. The `code` field is plain source; do not put backticks around lines there.

#### Style rules for the `STORY`

- **One idea per step.** The headline is the move; the body explains the move; the cards are the tension and the resolution; the closing ties it back.
- **4–10 steps total.** Below 4, the story doesn't earn a step-through. Above 10, the reader loses the thread.
- **Name things.** Say `AuthController.login`, not "the controller".
- **Why before what** when the why isn't obvious.
- **Skip `closing` when there's nothing to add.** Empty closings are fine — the field is hidden when blank.
- **Use chapters sparingly.** Two or three chapters is plenty for a 6-step story; many small stories don't need any.

### Step 7 — Write the file

Write the substituted HTML to the resolved output path.

## PR procedure (when invoked as `pr <id>`)

Skip Steps 1–3 of the default procedure. Use this PR-specific procedure instead, then jump to Step 4 (read references) onward.

### PR-1 — Resolve the PR

The argument after `pr` is one of:
- A bare number (e.g. `1234`) — assume current repo
- A full GitHub URL (e.g. `https://github.com/owner/repo/pull/1234`) — extract owner/repo/number

Verify `gh` is on `PATH` (`gh --version`). If not, error fast: ask the user to install GitHub CLI.

### PR-2 — Fetch metadata + diff

Run, in this order:

```bash
gh pr view <id> --json number,title,body,additions,deletions,changedFiles,commits,files,headRefName,baseRefName,author
gh pr diff <id>
```

For URL input, prepend `--repo owner/repo` to both commands.

If `gh` returns auth errors, ask the user to run `gh auth login` and re-invoke.

### PR-3 — Decompose into moves

You have: PR title + body, list of commits, list of files, full diff. Decompose into **4–10 moves** (the same target as default mode). Pick whichever decomposition tells the clearest story:

| Strategy | When to use |
|----------|-------------|
| **One step per commit** | The PR has 4–10 well-titled commits and each commit is one move |
| **Group commits by topic** | The PR has many small commits ("fix typo", "lint", etc.) — group them |
| **One step per logical chunk** | The PR has one fat commit but the diff has clear sub-changes (schema, API, tests, docs) |
| **Synthesize from the whole diff** | The diff is small enough to read holistically but has 4+ distinct moves |

Avoid "one step per file" — files are organizational, not narrative. A move is a *decision*.

### PR-4 — Build each step

For each move, produce a STORY step where:

- `chapter` — short tag for the move's category (`Schema`, `API`, `Tests`, `Migration`, `Refactor`, `Cleanup`). Use sparingly.
- `filename` — the most representative file for this move. If a move spans multiple files, pick the one that anchors the change; mention the others in `body` or `closing`.
- `code` — the **diff hunk(s)** for this move, NOT the full file. Format:
  ```diff
  @@ ... @@
   unchanged line
  -removed line
  +added line
  ```
  Use the `@@` hunk headers verbatim from `gh pr diff`. Keep 2–3 lines of context above/below the change.
- `lang` — set to `"diff"` so Prism colours `+` lines green and `-` lines red.
- `typeline` — the **before → after** signature change in one line. Examples:
  - `User.balance: never → number`
  - `function login(): void → Promise<{ token }>`
  - `+34 / −11 lines · 2 files`
  Use chips inside the typeline.
- `title` — the move as a noun phrase ("Add the balance column", "Fold the legacy auth path into the new service").
- `body` — what changed and why. Pull *why* from the PR body or commit message; pull *what* from reading the diff. Use chips for any identifier introduced.
- `problem` — the tension this move resolves. From the PR body, commit message, or your own reading of "what was missing/broken before".
- `move` — the resolution: what the PR did to address the problem.
- `closing` — implication for downstream code. Often: "Callers of `X` now have to provide `Y`" or "This unblocks `Z`".

### PR-5 — Add a header step (optional but recommended)

If the PR has a title and body worth setting up, add a **Step 1 = Setup** that does NOT show a diff:

- `code` — set to a short summary block, e.g.:
  ```
  PR #1234 · feat: add user balance to checkout
  Author: @username
  Branch: feat/balance → main
  +34 / −11 lines · 2 files · 3 commits
  ```
- `lang` — `"bash"` or `"plain"` for muted styling
- `typeline` — `PR #<id> · <branch> → <baseRef>`
- `title` — the PR title
- `body` — paraphrase the PR body (1–2 paragraphs)
- `problem` — the high-level problem the whole PR solves
- `move` — the high-level approach
- Subsequent steps then walk the diff move-by-move

### PR-6 — Resolve output path

Same as Step 5 of default mode. Default filename: `YYYY-MM-DD-pr-<id>-<slug>.html` where `<slug>` is the PR title kebab-cased.

### PR-7 — Generate, write, open

Same as Steps 6, 7, 9 of default mode. Skip Step 8 (`.gitignore` update) unless writing to `.claude-stories/`.

### Step 8 — Update `.gitignore` (only when default location used)

If and only if the output path is in the default `.claude-stories/` folder:
- Read the project's `.gitignore` (if it exists).
- If `.claude-stories/` is not already listed, append a new line: `.claude-stories/`.
- Do NOT touch `.gitignore` when `--out` is used.

### Step 9 — Open in browser (unless `--no-open`)

Run `open <path>` (macOS) / `xdg-open <path>` (Linux) / `start <path>` (Windows). If the command fails, print the file path so the user can open it manually.

## Failure modes

| Situation | Action |
|-----------|--------|
| Entry point not found | Tell the user what you searched for; ask where the entry point is |
| Multiple matching entry points | List them; ask user to pick |
| `--out` path is invalid (e.g., parent dir doesn't exist) | Error fast; do not generate the file |
| Codebase too large to fully trace in one pass | Trace best-effort; consolidate uncertain hops into a single step with a note in the closing |
| Fewer than 4 natural moves | Skip the step-through; tell the user a regular code summary is more appropriate |
| Browser open command fails | Print the file path so the user can open manually |

## Files in this skill

- `template.html` — HTML shell with `{{TITLE}}` and `{{STORY_JSON}}` placeholders. ~300 KB (Prism + 4 fonts + render engine). Substitute programmatically via Python (see Step 6); do NOT use the `Read` tool.
- `references/narration-style.md` — Tone guide. Read at step 4.
- `references/traversal-guide.md` — Entry-point hints per stack. Read at step 2.
- `examples/build-sample.sh` — Working reference for the `STORY` object and the quality bar.
- `examples/sample-story.html` — Rendered sample. Open in a browser to see the target output.

## Reminders

- The narrative is the product. The code window is supporting context — don't paste whole files.
- Use chips (`` `like-this` ``) generously in narrative strings; they're how the reader anchors back to the code.
- The `closing` field is optional. Use it when there's a payoff to land; skip it when the body and cards are enough.
- Every step should make the reader feel a **move was made** — not just "here's another file".

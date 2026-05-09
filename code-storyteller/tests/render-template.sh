#!/usr/bin/env bash
# Regression test: substitute a tiny mock STORY into template.html and write
# tests/output.html. Open output.html in a browser to verify the
# step-through template renders correctly. Independent of any fixture.

set -euo pipefail

SKILL_DIR="$HOME/.claude/skills/code-storyteller"
OUT="$SKILL_DIR/tests/output.html"

python3 <<'PY'
import json
from pathlib import Path

skill = Path.home() / ".claude/skills/code-storyteller"
src = (skill / "template.html").read_text()

STORY = {
    "title": "MOCK · TEMPLATE SMOKE-TEST",
    "steps": [
        {
            "filename": "step-one.ts",
            "code": "function greet(name: string): string {\n  return `Hello, ${name}!`;\n}",
            "typeline": "`greet(name: string)` → `string`",
            "title": "The opening move",
            "body": "A trivial `greet` function — but it sets the shape contract for everything downstream. The caller commits to a string in, string out.",
            "problem": "Untyped greeters return `any`, leaking through call sites.",
            "move": "Annotate parameters and the return type explicitly.",
            "closing": "From here, every consumer of `greet` knows what to expect — no defensive `String(...)` casts at the boundary.",
        },
        {
            "chapter": "Composition",
            "filename": "step-two.ts",
            "code": "const SHOUT = (s: string) => s.toUpperCase();\nconst loud = (n: string) => SHOUT(greet(n));",
            "typeline": "`loud(n: string)` → `string`",
            "title": "Compose without re-typing",
            "body": "`loud` takes the shape contract from step one and stacks `SHOUT` on top. TypeScript infers the return type — no annotation needed.",
            "problem": "Manually re-typing each composed function duplicates the source of truth.",
            "move": "Lean on inference; only annotate the *outermost* surface where the type matters to a human.",
        },
        {
            "filename": "step-three.ts",
            "code": "// Now wire it up\nconsole.log(loud('world'));\n// → \"HELLO, WORLD!\"",
            "typeline": "`console.log(...)` → `void`",
            "title": "The terminal action",
            "body": "Three steps in, the pipe terminates at `console.log` — the world-facing surface. Notice the comment showing the runtime value: it's the only place a literal appears in the file.",
            "problem": "Silent terminal actions force readers to mentally execute the code to know what happened.",
            "move": "Drop a comment with the expected output. Future readers thank you.",
            "closing": "And that's the whole arc — *contract*, *composition*, *surface*. Three moves, one story.",
        },
    ],
}

out = (src
  .replace("{{TITLE}}",      STORY["title"])
  .replace("{{STORY_JSON}}", json.dumps(STORY)))

(skill / "tests/output.html").write_text(out)
print("Wrote", skill / "tests/output.html", f"({len(out):,} bytes)")
PY

echo ""
echo "Open with: open '$OUT'"

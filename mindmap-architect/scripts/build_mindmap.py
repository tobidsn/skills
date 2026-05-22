"""Build a mindmap into the user's global vault (~/Mindmaps/ by default).

Single source of truth: all mindmaps live under one vault. Per-mindmap folders
are thin (~1KB stub + JSON/MD/PNG). Shared viewer.css/viewer.js sit at the
vault root, so template changes propagate automatically without regenerating
each mindmap's HTML.

Usage example (called from the skill):
  python3 build_mindmap.py \
    --title "My Mindmap" --summary "..." \
    --source-type prompt --source "user prompt" \
    --json-file /tmp/nodes.json \
    --templates-dir /path/to/mindmap-architect/assets

Vault location:
  1. --vault CLI arg
  2. $MINDMAP_VAULT env var
  3. ~/Mindmaps  (default)
"""

import argparse
import json
import os
import re
import shutil
import sys
from datetime import datetime
from pathlib import Path

SHARED_FILES = ("viewer.css", "viewer.js", "serve.py")


def clean_slug(title: str) -> str:
    slug = title.lower()
    slug = re.sub(r"\s+", "-", slug)
    slug = re.sub(r"[^a-z0-9\-]", "", slug)
    slug = re.sub(r"\-+", "-", slug)
    return slug.strip("-")[:60]


def get_unique_slug(vault: Path, slug: str) -> str:
    if not (vault / slug).exists():
        return slug
    n = 2
    while True:
        candidate = f"{slug}-{n}"
        if not (vault / candidate).exists():
            return candidate
        n += 1


def resolve_vault(arg_vault: str | None) -> Path:
    if arg_vault:
        return Path(arg_vault).expanduser().resolve()
    env = os.environ.get("MINDMAP_VAULT")
    if env:
        return Path(env).expanduser().resolve()
    return Path.home() / "Mindmaps"


def init_vault(vault: Path, templates_dir: Path) -> None:
    """Create vault dir, copy shared viewer assets and serve.py.

    Always overwrites — keeps vault in sync with the latest templates.
    """
    vault.mkdir(parents=True, exist_ok=True)
    for name in SHARED_FILES:
        src = templates_dir / name
        if not src.exists():
            print(f"Warning: template asset missing: {src}", file=sys.stderr)
            continue
        shutil.copy(src, vault / name)
        if name == "serve.py":
            os.chmod(vault / name, 0o755)


def scan_vault(vault: Path) -> list[dict]:
    items: list[dict] = []
    if not vault.exists():
        return items
    for path in sorted(vault.iterdir()):
        if not path.is_dir() or path.name in (".git", "node_modules", "examples"):
            continue
        json_file = path / "mindmap.json"
        if not json_file.exists():
            continue
        try:
            data = json.loads(json_file.read_text(encoding="utf-8"))
        except Exception as exc:
            print(f"Warning: skipping {json_file}: {exc}", file=sys.stderr)
            continue
        items.append({
            "title": data.get("title", path.name),
            "summary": data.get("summary", ""),
            "source_type": data.get("source_type", "prompt"),
            "slug": data.get("slug", path.name),
            "created_at": data.get("created_at", ""),
            "source_path": data.get("source_path", ""),
            "source_project": data.get("source_project", ""),
            "is_example": bool(data.get("is_example", False)),
            "has_thumbnail": (path / "export.png").exists(),
        })
    items.sort(key=lambda x: x.get("created_at", ""), reverse=True)
    return items


def generate_markdown(title: str, summary: str, nodes: list) -> str:
    lines = [f"# {title}\n"]
    if summary:
        lines.append(f"*{summary}*\n")

    def recurse(node: dict, depth: int) -> None:
        label = node.get("label", node.get("title", ""))
        if depth == 1:
            lines.append(f"\n## {label}")
        else:
            indent = "  " * (depth - 2)
            lines.append(f"{indent}- {label}")
        for child in node.get("children", []):
            recurse(child, depth + 1)

    for node in nodes:
        recurse(node, 1)
    return "\n".join(lines) + "\n"


def write_stub(slug_dir: Path, stub_template: str, canonical_data: dict) -> None:
    serialized = json.dumps(canonical_data, ensure_ascii=False)
    out = stub_template.replace("__DATA_PLACEHOLDER__", serialized)
    out = out.replace("__TITLE__", canonical_data.get("title", "Mindmap"))
    (slug_dir / "index.html").write_text(out, encoding="utf-8")


def write_catalog(vault: Path, catalog_template: str) -> None:
    items = scan_vault(vault)
    serialized = json.dumps(items, indent=2, ensure_ascii=False)
    out = catalog_template.replace("__ITEMS_PLACEHOLDER__", serialized)
    (vault / "index.html").write_text(out, encoding="utf-8")


def main() -> None:
    parser = argparse.ArgumentParser(description="Build a mindmap into the global vault.")
    parser.add_argument("--title", required=True)
    parser.add_argument("--summary", required=True)
    parser.add_argument("--source-type", required=True,
                        choices=["prompt", "youtube", "file", "lark", "mixed"])
    parser.add_argument("--source", required=True,
                        help="Original reference (URL, path, prompt snippet)")
    parser.add_argument("--json-file", required=True,
                        help="Path to temp JSON file containing the nodes tree")
    parser.add_argument("--templates-dir", required=True,
                        help="Path to mindmap-architect/assets")
    parser.add_argument("--vault", default=None,
                        help="Vault dir (default: $MINDMAP_VAULT or ~/Mindmaps)")
    parser.add_argument("--source-path", default=None,
                        help="Working directory at invocation (default: cwd)")
    parser.add_argument("--source-project", default=None,
                        help="Friendly project name (default: basename of source-path)")
    args = parser.parse_args()

    templates_dir = Path(args.templates_dir).expanduser().resolve()
    vault = resolve_vault(args.vault)

    # 1. Init vault if needed (also refreshes shared assets)
    init_vault(vault, templates_dir)

    # 2. Load templates
    stub_tpl = (templates_dir / "stub.html").read_text(encoding="utf-8")
    catalog_tpl = (templates_dir / "catalog.html").read_text(encoding="utf-8")

    # 3. Load nodes JSON
    nodes = json.loads(Path(args.json_file).read_text(encoding="utf-8"))

    # 4. Resolve slug
    base_slug = clean_slug(args.title) or "untitled-mindmap"
    slug = get_unique_slug(vault, base_slug)

    # 5. Source-path metadata (defaults to cwd)
    source_path = args.source_path or os.getcwd()
    source_path = str(Path(source_path).expanduser().resolve())
    source_project = args.source_project or Path(source_path).name

    # 6. Build canonical data
    canonical_data = {
        "title": args.title,
        "summary": args.summary,
        "source_type": args.source_type,
        "source": args.source,
        "slug": slug,
        "created_at": datetime.now().isoformat(),
        "source_path": source_path,
        "source_project": source_project,
        "nodes": nodes,
    }

    # 7. Write slug folder
    slug_dir = vault / slug
    slug_dir.mkdir(parents=True, exist_ok=True)

    (slug_dir / "mindmap.json").write_text(
        json.dumps(canonical_data, indent=2, ensure_ascii=False),
        encoding="utf-8",
    )
    (slug_dir / "mindmap.md").write_text(
        generate_markdown(args.title, args.summary, nodes),
        encoding="utf-8",
    )
    write_stub(slug_dir, stub_tpl, canonical_data)

    (slug_dir / "README.md").write_text(
        f"""# {args.title}

*Source Type:* {args.source_type.upper()}
*Source:* {args.source}
*From:* {source_project}

Generated interactive mindmap.

## Files
- `index.html` — Interactive viewer (uses shared `../viewer.css` + `../viewer.js`)
- `mindmap.md` — Outline format
- `mindmap.json` — Raw structure data
- `export.png` — Image export (optional)

## Preview
Open `index.html` directly in a browser, or from the vault root run:
```bash
python serve.py
```
""",
        encoding="utf-8",
    )

    # 8. Refresh catalog
    write_catalog(vault, catalog_tpl)

    print("Success")
    print(f"Vault:  {vault}")
    print(f"Slug:   {slug}")
    print(f"From:   {source_project} ({source_path})")


if __name__ == "__main__":
    main()

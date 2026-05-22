"""Tiny preview server for a Mindmap Architect vault.

Run from the vault root (e.g. ~/Mindmaps/) — it scans subfolders for
mindmap.json files and exposes /api/mindmaps for the catalog page.
"""

import http.server
import json
import socket
import socketserver
import webbrowser
from pathlib import Path

ROOT = Path(__file__).resolve().parent


def find_free_port() -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.bind(("", 0))
        return s.getsockname()[1]


def scan_mindmaps() -> list[dict]:
    items: list[dict] = []
    if not ROOT.exists():
        return items
    for child in sorted(ROOT.iterdir()):
        if not child.is_dir() or child.name in (".git", "node_modules"):
            continue
        json_file = child / "mindmap.json"
        if not json_file.exists():
            continue
        try:
            data = json.loads(json_file.read_text(encoding="utf-8"))
        except Exception as exc:
            print(f"Skip {json_file}: {exc}")
            continue
        items.append({
            "slug": data.get("slug", child.name),
            "title": data.get("title", child.name.replace("-", " ").title()),
            "summary": data.get("summary", ""),
            "source_type": data.get("source_type", "prompt"),
            "created_at": data.get("created_at", ""),
            "source_path": data.get("source_path", ""),
            "source_project": data.get("source_project", ""),
            "is_example": bool(data.get("is_example", False)),
            "has_thumbnail": (child / "export.png").exists(),
        })
    items.sort(key=lambda x: x.get("created_at", ""), reverse=True)
    return items


class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(ROOT), **kwargs)

    def do_GET(self):
        if self.path == "/api/mindmaps":
            try:
                data = scan_mindmaps()
                payload = json.dumps(data, ensure_ascii=False).encode("utf-8")
                self.send_response(200)
                self.send_header("Content-Type", "application/json")
                self.send_header("Cache-Control", "no-cache, no-store, must-revalidate")
                self.end_headers()
                self.wfile.write(payload)
            except Exception as exc:
                self.send_response(500)
                self.send_header("Content-Type", "application/json")
                self.end_headers()
                self.wfile.write(json.dumps({"error": str(exc)}).encode("utf-8"))
            return
        super().do_GET()


def main() -> None:
    port = find_free_port()
    url = f"http://localhost:{port}/index.html"
    print("")
    print(f"Mindmap vault: {ROOT}")
    print(f"Preview:       {url}")
    print("")
    webbrowser.open(url)
    socketserver.TCPServer.allow_reuse_address = True
    with socketserver.TCPServer(("", port), Handler) as httpd:
        httpd.serve_forever()


if __name__ == "__main__":
    main()

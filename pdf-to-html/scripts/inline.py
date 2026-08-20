#!/usr/bin/env python3
"""Step 3.5 of pdf-to-html: fold every local image into the HTML as a data URI.

  python3 inline.py draft.html --out final.html [--max-width 1400] [--quality 82]

A single file is the whole point of the deliverable: it survives being emailed,
dropped in a chat, or copied to a USB stick, and no asset can go missing on the
way. WebP is what makes that affordable -- the flat gradients and logos in a
document like this compress roughly 10x smaller than PNG at a quality nobody
can see the difference in.

Rewrites every src="..." (and url(...) in inline CSS) that points at a local
file. Absolute URLs, existing data: URIs, and missing files are left alone and
reported. Stdlib only; ImageMagick does the conversion.
"""

import argparse
import base64
import os
import re
import shutil
import subprocess
import sys

REF = re.compile(r'(src\s*=\s*["\']|url\(\s*["\']?)([^"\')>]+)(["\']|["\']?\s*\))')
SKIP = re.compile(r"^(data:|https?:|//|#)")


def to_webp(path, max_width, quality):
    cmd = ["magick", path]
    if max_width:
        # ">" only shrinks; upscaling a small logo would add bytes and no detail
        cmd += ["-resize", f"{max_width}x>"]
    cmd += ["-quality", str(quality), "webp:-"]
    p = subprocess.run(cmd, capture_output=True)
    if p.returncode or not p.stdout:
        return None
    return p.stdout


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("html")
    ap.add_argument("--out", required=True)
    ap.add_argument("--max-width", type=int, default=1400,
                    help="cap the raster width in px; 1400 is ~170dpi across A4")
    ap.add_argument("--quality", type=int, default=82)
    ap.add_argument("--no-webp", action="store_true",
                    help="embed bytes as-is instead of converting")
    a = ap.parse_args()

    if not a.no_webp and not shutil.which("magick"):
        sys.exit("missing `magick` -> brew install imagemagick (or pass --no-webp)")

    src = open(a.html, encoding="utf-8").read()
    base = os.path.dirname(os.path.abspath(a.html))
    done, saved_from, saved_to, skipped = {}, 0, 0, []

    def sub(m):
        nonlocal saved_from, saved_to
        pre, ref, post = m.group(1), m.group(2).strip(), m.group(3)
        if SKIP.match(ref):
            return m.group(0)
        path = ref if os.path.isabs(ref) else os.path.join(base, ref)
        if not os.path.isfile(path):
            skipped.append(ref)
            return m.group(0)
        if path not in done:
            raw = open(path, "rb").read()
            blob, mime = raw, None
            if not a.no_webp:
                w = to_webp(path, a.max_width, a.quality)
                if w and len(w) < len(raw):
                    blob, mime = w, "image/webp"
            if mime is None:
                ext = os.path.splitext(path)[1].lower().lstrip(".")
                mime = "image/" + {"jpg": "jpeg", "svg": "svg+xml"}.get(ext, ext or "png")
            saved_from += len(raw)
            saved_to += len(blob)
            done[path] = f"data:{mime};base64," + base64.b64encode(blob).decode()
            print(f"  {os.path.basename(path):32s} {len(raw)/1024:8.1f} KB -> "
                  f"{len(blob)/1024:7.1f} KB  {mime}")
        return pre + done[path] + post

    out = REF.sub(sub, src)
    with open(a.out, "w", encoding="utf-8") as fh:
        fh.write(out)

    print(f"\n{len(done)} image(s) inlined: {saved_from/1024:.1f} KB -> {saved_to/1024:.1f} KB")
    print(f"{a.out}  {len(out)/1024:.1f} KB total")
    if skipped:
        print("\nNOT inlined (file not found) - the deliverable will have holes:")
        for s in sorted(set(skipped)):
            print(f"  {s}")
        sys.exit(1)
    left = [m.group(2) for m in REF.finditer(out) if not SKIP.match(m.group(2).strip())]
    if left:
        print("\nstill referencing external files:", left)
        sys.exit(1)
    print("self-contained: no external references remain")


if __name__ == "__main__":
    main()

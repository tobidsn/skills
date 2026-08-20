#!/usr/bin/env python3
"""The measuring tape. Reads ink off a 300 dpi page raster and answers in mm.

Everything takes and returns millimetres, because that is the unit the HTML is
written in. Converting px<->mm by hand in your head is where the 2mm errors come
from.

  rules   <img>                       every box border / rule, as rectangles
  bands   <img> [--x0 --x1]           horizontal ink bands down a column strip
  cols    <img> --y MM                ink runs across one scanline
  rows    <img> --box L,T,R,B         per text line: top + left ink + right ink
  colors  <img> --box L,T,R,B [-n]    dominant colours in a region

Why rows reports BOTH left and right ink: left tells you the indent, right tells
you the justification measure. A column whose right edge sits 2mm past the
original will wrap in different places and desynchronise every line below it.

Stdlib only; ImageMagick does the decoding. Pixels arrive as binary PGM/PPM,
which is ~100x faster than parsing `txt:` output and needs no Pillow.
"""

import argparse
import re
import shutil
import subprocess
import sys

MM_IN = 25.4


def need_magick():
    if not shutil.which("magick"):
        sys.exit("missing `magick` -> brew install imagemagick")


def _read_pnm(data):
    """Minimal binary PGM/PPM reader -> (w, h, channels, bytes)."""
    tok, pos, hdr = [], 0, None
    while len(tok) < 4:
        while pos < len(data) and data[pos : pos + 1].isspace():
            pos += 1
        if data[pos : pos + 1] == b"#":
            while data[pos : pos + 1] not in (b"\n", b""):
                pos += 1
            continue
        s = pos
        while pos < len(data) and not data[pos : pos + 1].isspace():
            pos += 1
        tok.append(data[s:pos])
    pos += 1
    magic, w, h = tok[0], int(tok[1]), int(tok[2])
    ch = 3 if magic == b"P6" else 1
    return w, h, ch, data[pos : pos + w * h * ch]


def load(img, gray=True, crop=None):
    """crop = (x, y, w, h) in px."""
    need_magick()
    cmd = ["magick", img]
    if crop:
        cmd += ["-crop", "%dx%d+%d+%d" % (crop[2], crop[3], crop[0], crop[1]), "+repage"]
    if gray:
        cmd += ["-colorspace", "gray"]
    cmd += ["-depth", "8", ("pgm:-" if gray else "ppm:-")]
    p = subprocess.run(cmd, capture_output=True)
    if p.returncode:
        sys.exit(p.stderr.decode()[:400])
    return _read_pnm(p.stdout)


def dpi_of(img, page_w_mm):
    need_magick()
    w = int(subprocess.run(["magick", "identify", "-format", "%w", img],
                           capture_output=True, text=True).stdout)
    return w / (page_w_mm / MM_IN)


def runs(flags):
    """[bool] -> [(start, end)] inclusive, of the True stretches."""
    out, s = [], None
    for i, v in enumerate(flags):
        if v and s is None:
            s = i
        elif not v and s is not None:
            out.append((s, i - 1))
            s = None
    if s is not None:
        out.append((s, len(flags) - 1))
    return out


def cmd_bands(a):
    px_mm = a.dpi / MM_IN
    x0 = int(a.x0 * px_mm) if a.x0 is not None else 0
    w, h, _, buf = load(a.img)
    x1 = int(a.x1 * px_mm) if a.x1 is not None else w
    x0, x1 = max(0, x0), min(w, x1)
    dark = [any(buf[y * w + x] < a.thresh for x in range(x0, x1)) for y in range(h)]
    print(f"# {a.img}  x {x0/px_mm:.1f}..{x1/px_mm:.1f} mm   thresh {a.thresh}")
    print("# top_mm  bot_mm  height_mm")
    for s, e in runs(dark):
        if e - s + 1 < a.min_px:
            continue
        print(f"{s/px_mm:8.2f}{(e+1)/px_mm:8.2f}{(e-s+1)/px_mm:9.2f}")


def cmd_cols(a):
    px_mm = a.dpi / MM_IN
    y = int(a.y * px_mm)
    w, h, _, buf = load(a.img, crop=(0, y, 0, 0) if False else None)
    if y >= h:
        sys.exit(f"y={a.y}mm is past the page ({h/px_mm:.1f}mm)")
    row = buf[y * w : (y + 1) * w]
    dark = [v < a.thresh for v in row]
    print(f"# {a.img}  scanline y={a.y}mm   thresh {a.thresh}")
    print("# left_mm  right_mm  width_mm")
    for s, e in runs(dark):
        if e - s + 1 < a.min_px:
            continue
        print(f"{s/px_mm:9.2f}{(e+1)/px_mm:10.2f}{(e-s+1)/px_mm:10.2f}")


def cmd_rows(a):
    px_mm = a.dpi / MM_IN
    L, T, R, B = (float(v) for v in a.box.split(","))
    cx, cy = int(L * px_mm), int(T * px_mm)
    cw, chh = int((R - L) * px_mm), int((B - T) * px_mm)
    w, h, _, buf = load(a.img, crop=(cx, cy, cw, chh))
    print(f"# {a.img}  box {L},{T},{R},{B} mm   thresh {a.thresh}")
    print("# top_mm  left_mm right_mm  (ink extents per text line)")
    band, n = [], 0
    for y in range(h):
        row = buf[y * w : (y + 1) * w]
        xs = [x for x, v in enumerate(row) if v < a.thresh]
        if xs:
            band.append((y, xs[0], xs[-1]))
        elif band:
            n += _flush(band, L, T, px_mm, a.min_px)
            band = []
    if band:
        n += _flush(band, L, T, px_mm, a.min_px)
    print(f"# {n} lines")


def _flush(band, L, T, px_mm, min_px):
    if len(band) < min_px:
        return 0
    top = T + band[0][0] / px_mm
    left = L + min(b[1] for b in band) / px_mm
    right = L + (max(b[2] for b in band) + 1) / px_mm
    print(f"{top:8.2f}{left:8.2f}{right:9.2f}")
    return 1


def cmd_colors(a):
    px_mm = a.dpi / MM_IN
    L, T, R, B = (float(v) for v in a.box.split(","))
    crop = "%dx%d+%d+%d" % (
        int((R - L) * px_mm), int((B - T) * px_mm), int(L * px_mm), int(T * px_mm),
    )
    need_magick()
    out = subprocess.run(
        ["magick", a.img, "-crop", crop, "+repage", "-format", "%c", "histogram:info:"],
        capture_output=True, text=True,
    ).stdout
    rows = []
    for line in out.splitlines():
        m = re.search(r"(\d+):.*?(#[0-9A-Fa-f]{6})", line)
        if m:
            rows.append((int(m.group(1)), m.group(2).upper()))
    rows.sort(reverse=True)
    total = sum(c for c, _ in rows) or 1
    print(f"# {a.img}  box {a.box} mm")
    print("# share  hex       role guess")
    for c, hexv in rows[: a.n]:
        role = "paper" if hexv in ("#FFFFFF", "#FEFEFE") else ""
        print(f"{100*c/total:6.1f}%  {hexv}  {role}")


def cmd_rules(a):
    """Long runs of dark = box borders and rules. Reported as rectangles so you
    can paste left/top/width/height straight into CSS."""
    px_mm = a.dpi / MM_IN
    w, h, _, buf = load(a.img)
    minlen = int(a.min_len * px_mm)

    hrules = []
    for y in range(h):
        row = buf[y * w : (y + 1) * w]
        for s, e in runs([v < a.thresh for v in row]):
            if e - s + 1 >= minlen:
                hrules.append((y, s, e))
    vrules = []
    for x in range(w):
        col = [buf[y * w + x] for y in range(h)]
        for s, e in runs([v < a.thresh for v in col]):
            if e - s + 1 >= minlen:
                vrules.append((x, s, e))

    def merge(items):
        items.sort()
        out, cur = [], None
        for pos, s, e in items:
            if cur and pos - cur[1] <= 3 and not (e < cur[2] or s > cur[3]):
                cur[1] = pos
                cur[2], cur[3] = min(cur[2], s), max(cur[3], e)
            else:
                cur = [pos, pos, s, e]
                out.append(cur)
        return out

    print(f"# {a.img}   rules at least {a.min_len}mm long, thresh {a.thresh}")
    print("\n# horizontal:  top_mm   from_mm    to_mm")
    for p0, p1, s, e in merge(hrules):
        print(f"{(p0+p1)/2/px_mm:16.2f}{s/px_mm:10.2f}{(e+1)/px_mm:9.2f}")
    print("\n# vertical:   left_mm   from_mm    to_mm")
    for p0, p1, s, e in merge(vrules):
        print(f"{(p0+p1)/2/px_mm:16.2f}{s/px_mm:10.2f}{(e+1)/px_mm:9.2f}")


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--dpi", type=float, default=300,
                    help="dpi of the raster (probe.py writes hires/ at 300)")
    ap.add_argument("--thresh", type=int, default=170,
                    help="0-255; below this counts as ink. Raise for pale text.")
    sub = ap.add_subparsers(dest="cmd", required=True)

    p = sub.add_parser("bands", help="horizontal ink bands down a column strip")
    p.add_argument("img"); p.add_argument("--x0", type=float); p.add_argument("--x1", type=float)
    p.add_argument("--min-px", type=int, default=3); p.set_defaults(fn=cmd_bands)

    p = sub.add_parser("cols", help="ink runs across one scanline")
    p.add_argument("img"); p.add_argument("--y", type=float, required=True)
    p.add_argument("--min-px", type=int, default=1); p.set_defaults(fn=cmd_cols)

    p = sub.add_parser("rows", help="per text line: top, left ink, right ink")
    p.add_argument("img"); p.add_argument("--box", required=True, metavar="L,T,R,B")
    p.add_argument("--min-px", type=int, default=3); p.set_defaults(fn=cmd_rows)

    p = sub.add_parser("colors", help="dominant colours in a region")
    p.add_argument("img"); p.add_argument("--box", required=True, metavar="L,T,R,B")
    p.add_argument("-n", type=int, default=6); p.set_defaults(fn=cmd_colors)

    p = sub.add_parser("rules", help="box borders and rules, as rectangles")
    p.add_argument("img"); p.add_argument("--min-len", type=float, default=20)
    p.set_defaults(fn=cmd_rules)

    a = ap.parse_args()
    a.fn(a)


if __name__ == "__main__":
    main()

#!/usr/bin/env python3
"""Step 4 of pdf-to-html: prove the HTML matches the PDF, don't hope it does.

  python3 verify.py out/final.html --pdf original.pdf --out verify/

Prints three verdicts and writes side-by-side images you must then LOOK at:

  1. shape     page count and page size
  2. geometry  every long rule (box border, banner edge) matched between the two
               renders, with the delta in mm. This is what catches a box that
               drifted 3mm or a banner that ended up on the wrong page.
  3. text      normalised similarity of the extracted text, plus what differs.
               Catches dropped sentences and typos introduced while retyping.

  cmp-N.png    original on the left, yours on the right

The geometry and text checks are cheap and objective, so they run every time.
They cannot see a colour that came out wrong, text sitting on top of other text,
or a logo squashed out of proportion -- for those, read cmp-N.png. Both halves
matter; neither substitutes for the other.

Stdlib only. Needs poppler, ImageMagick, and Chrome (any of the usual paths).
"""

import argparse
import difflib
import os
import re
import shutil
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from measure import load, runs  # noqa: E402

MM_IN = 25.4
CHROME_PATHS = [
    "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
    "/Applications/Chromium.app/Contents/MacOS/Chromium",
    "/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge",
    "google-chrome", "google-chrome-stable", "chromium", "chromium-browser",
]


def find_chrome():
    for p in CHROME_PATHS:
        if os.path.exists(p) or shutil.which(p):
            return p
    sys.exit("no Chrome/Chromium found; set --chrome /path/to/binary")


def sh(cmd):
    p = subprocess.run(cmd, capture_output=True, text=True)
    return p.returncode, p.stdout, p.stderr


def pdf_shape(pdf):
    _, out, _ = sh(["pdfinfo", pdf])
    pages = size = None
    for line in out.splitlines():
        if line.startswith("Pages:"):
            pages = int(line.split()[1])
        elif line.startswith("Page size:"):
            m = re.search(r"([\d.]+) x ([\d.]+)", line)
            size = (float(m.group(1)), float(m.group(2)))
    return pages, size


def print_to_pdf(chrome, html, out_pdf, budget):
    url = "file://" + subprocess.run(
        ["python3", "-c",
         "import sys,urllib.parse,os;print(urllib.parse.quote(os.path.abspath(sys.argv[1])))",
         html], capture_output=True, text=True).stdout.strip()
    rc, _, err = sh([
        chrome, "--headless", "--disable-gpu", "--no-pdf-header-footer",
        f"--virtual-time-budget={budget}", f"--print-to-pdf={out_pdf}", url,
    ])
    if not os.path.exists(out_pdf):
        sys.exit(f"chrome produced no pdf\n{err[:600]}")


MAX_RULE_MM = 1.6  # anything thicker is a filled block, not a border


def long_rules(img, dpi, min_len_mm, thresh=170):
    """Structural landmarks, in mm.

    -> (horizontals, verticals). Horizontals are thin rules (box borders, table
    rules) plus the top and bottom EDGE of every filled block. Verticals are
    thin rules only.

    Splitting thin from filled matters: a teal section banner is dark for its
    whole 9mm height and its whole width, so a naive "long dark run" scan
    reports it as dozens of rules on both axes and buries the one border that
    actually moved. Reduced to two edges it stays informative and quiet.
    """
    px_mm = dpi / MM_IN
    w, h, _, buf = load(img)
    minlen = int(min_len_mm * px_mm)
    maxthick = max(1, int(MAX_RULE_MM * px_mm))

    def long_enough(flags):
        """True if some run reaches minlen, tolerating hairline gaps.

        Browser table borders drawn with border-collapse land on fractional
        pixels and leave 1-2px holes where cells meet. Without closing those
        holes a perfectly placed table rule fails the length test, gets reported
        UNPAIRED, and the nearest-neighbour match then slides onto the wrong
        rule and invents a 4mm delta that isn't there.
        """
        spans = runs(flags)
        if not spans:
            return False
        cur_s, cur_e = spans[0]
        for s, e in spans[1:]:
            if s - cur_e - 1 <= 3:
                cur_e = e
            else:
                if cur_e - cur_s + 1 >= minlen:
                    return True
                cur_s, cur_e = s, e
        return cur_e - cur_s + 1 >= minlen

    def merged(axis_len, get):
        hits = [i for i in range(axis_len) if long_enough([v < thresh for v in get(i)])]
        out, cur = [], None
        for i in hits:
            if cur is not None and i - cur[1] <= 3:
                cur[1] = i
            else:
                cur = [i, i]
                out.append(cur)
        return out

    hs = []
    for a, b in merged(h, lambda y: buf[y * w : (y + 1) * w]):
        if b - a + 1 <= maxthick:
            hs.append(round((a + b + 1) / 2 / px_mm, 2))
        else:
            hs += [round(a / px_mm, 2), round((b + 1) / px_mm, 2)]

    # For the vertical pass, drop rows that are dark across most of the page.
    # Those rows are either a filled banner or a horizontal border; both make
    # unrelated columns look like tall rules and both are already accounted for
    # by the horizontal pass.
    keep = [
        y for y in range(h)
        if sum(1 for x in range(0, w, 4) if buf[y * w + x] < thresh) < 0.6 * (w / 4)
    ]
    vs = [
        round((a + b + 1) / 2 / px_mm, 2)
        for a, b in merged(w, lambda x: [buf[y * w + x] for y in keep])
        if b - a + 1 <= maxthick
    ]
    return sorted(hs), sorted(vs)


def match(a, b, tol):
    """Nearest-neighbour pair -> [(a_val, b_val|None, delta)] plus b-only extras."""
    used, rows = set(), []
    for va in a:
        best = None
        for j, vb in enumerate(b):
            if j in used:
                continue
            d = abs(vb - va)
            if best is None or d < best[0]:
                best = (d, j, vb)
        if best and best[0] <= tol:
            used.add(best[1])
            rows.append((va, best[2], best[2] - va))
        else:
            rows.append((va, None, None))
    for j, vb in enumerate(b):
        if j not in used:
            rows.append((None, vb, None))
    return rows


def norm_text(pdf):
    _, out, _ = sh(["pdftotext", pdf, "-"])
    return re.sub(r"\s+", "", out).lower()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("html")
    ap.add_argument("--pdf", required=True, help="the original PDF")
    ap.add_argument("--out", default="verify")
    ap.add_argument("--dpi", type=int, default=300)
    ap.add_argument("--tol", type=float, default=1.0, help="mm a rule may drift")
    ap.add_argument("--min-len", type=float, default=40.0, help="mm; rule length floor")
    ap.add_argument("--chrome", default=None)
    ap.add_argument("--budget", type=int, default=5000, help="chrome virtual-time ms")
    ap.add_argument("--skip-geometry", action="store_true")
    a = ap.parse_args()

    for t in ("pdfinfo", "pdftoppm", "pdftotext", "magick"):
        if not shutil.which(t):
            sys.exit(f"missing `{t}` -> brew install poppler imagemagick")
    chrome = a.chrome or find_chrome()
    os.makedirs(a.out, exist_ok=True)
    mine_pdf = os.path.join(a.out, "render.pdf")

    print(f"rendering {a.html} through {os.path.basename(chrome)} ...")
    print_to_pdf(chrome, a.html, os.path.abspath(mine_pdf), a.budget)

    # ---- 1. shape
    op, osz = pdf_shape(a.pdf)
    mp, msz = pdf_shape(mine_pdf)
    print("\n=== 1. shape")
    print(f"  pages       original {op}   yours {mp}   {'OK' if op == mp else 'MISMATCH'}")
    dw, dh = abs(osz[0] - msz[0]), abs(osz[1] - msz[1])
    print(f"  page size   original {osz[0]:.1f}x{osz[1]:.1f}pt   yours {msz[0]:.1f}x{msz[1]:.1f}pt"
          f"   {'OK' if dw < 2 and dh < 2 else 'MISMATCH'}")
    shape_ok = op == mp and dw < 2 and dh < 2

    print("\nrasterising both at %d dpi ..." % a.dpi)
    for tag, src in (("orig", a.pdf), ("mine", mine_pdf)):
        d = os.path.join(a.out, tag)
        os.makedirs(d, exist_ok=True)
        sh(["pdftoppm", "-r", str(a.dpi), "-png", src, os.path.join(d, "pg")])

    npages = min(op or 0, mp or 0)
    for p in range(1, npages + 1):
        o = os.path.join(a.out, "orig", f"pg-{p}.png")
        m = os.path.join(a.out, "mine", f"pg-{p}.png")
        if os.path.exists(o) and os.path.exists(m):
            sh(["magick", o, m, "-resize", "940x", "-bordercolor", "red",
                "-border", "2", "+append", os.path.join(a.out, f"cmp-{p}.png")])

    # ---- 2. geometry
    worst = 0.0
    if not a.skip_geometry:
        print("\n=== 2. geometry  (long rules, mm)")
        print("  page  axis  original    yours     delta")
        for p in range(1, npages + 1):
            o = os.path.join(a.out, "orig", f"pg-{p}.png")
            m = os.path.join(a.out, "mine", f"pg-{p}.png")
            oh, ov = long_rules(o, a.dpi, a.min_len)
            mh, mv = long_rules(m, a.dpi, a.min_len)
            for axis, ra, rb in (("h", oh, mh), ("v", ov, mv)):
                for va, vb, d in match(ra, rb, a.tol * 6):
                    if d is not None and abs(d) <= a.tol:
                        continue
                    sa = f"{va:8.2f}" if va is not None else "     ---"
                    sb = f"{vb:8.2f}" if vb is not None else "     ---"
                    sd = f"{d:+7.2f}" if d is not None else "  UNPAIRED"
                    print(f"  {p:4d}  {axis:4s} {sa} {sb}  {sd}")
                    if d is not None:
                        worst = max(worst, abs(d))
        print(f"  worst paired drift: {worst:.2f} mm (tolerance {a.tol} mm)")
        print("  (nothing listed above = every rule landed within tolerance)")

    # ---- 3. text
    print("\n=== 3. text")
    ao, bo = norm_text(a.pdf), norm_text(mine_pdf)
    sm = difflib.SequenceMatcher(None, ao, bo, autojunk=False)
    ratio = sm.ratio()
    print(f"  similarity {ratio:.4f}   ({len(ao)} chars original, {len(bo)} yours)")
    shown = 0
    for tag, i1, i2, j1, j2 in sm.get_opcodes():
        if tag == "equal":
            continue
        if shown >= 25:
            print("  ... more differences suppressed")
            break
        print(f"  {tag:7s} orig={ao[i1:i2][:70]!r} yours={bo[j1:j2][:70]!r}")
        shown += 1
    if shown == 0:
        print("  identical after whitespace normalisation")

    print("\n=== verdict")
    print(f"  shape     {'pass' if shape_ok else 'FAIL'}")
    if not a.skip_geometry:
        print(f"  geometry  {'pass' if worst <= a.tol else 'review'}  (worst {worst:.2f} mm)")
    print(f"  text      {'pass' if ratio > 0.995 else 'review'}  ({ratio:.4f})")
    print(f"\n  now READ {a.out}/cmp-1.png .. cmp-{npages}.png -- the checks above")
    print("  cannot see wrong colours, overlapping text, or a squashed logo.")


if __name__ == "__main__":
    main()

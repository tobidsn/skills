#!/usr/bin/env python3
"""Step 1 of pdf-to-html: turn a PDF into everything you need to rebuild it.

Writes into --out:
  pages/pg-N.png      110 dpi previews  -> READ THESE, they are how you see the layout
  hires/pg-N.png      300 dpi rasters   -> what measure.py takes its numbers from
  assets/im-*         every embedded raster. Where one has a soft mask, an
                      im-*-rgba.png is written with the mask re-attached as
                      alpha -- use that copy, or logos and signatures render as
                      grey boxes.
  assets/manifest.tsv per raster: which file to use, whether it has alpha, and
                      where it is drawn on the page in mm
  geometry.txt        every text line: top/left/right in mm + pt size + the text
  text.txt            pdftotext -layout, for copying content without retyping it
  report.md           page size, font/size census, per-page image counts

Stdlib only. Shells out to poppler (pdftoppm/pdftotext/pdfimages/pdftohtml/
pdfinfo), ImageMagick, and pdf2txt.py from pdfminer.six. Nothing needs Pillow
or PyMuPDF. pdf2txt.py is optional: without it you lose geometry.txt and the
font census, which is most of the point, so install it.
"""

import argparse
import os
import re
import shutil
import subprocess
import sys
from collections import Counter

PT_MM = 25.4 / 72.0
CHAR = re.compile(r'<text\s[^>]*?bbox="([^"]+)"[^>]*?size="([^"]+)"[^>]*?>(.)</text>')
LINE = re.compile(r'<textline\s+bbox="([^"]+)">(.*?)</textline>', re.S)
FIG = re.compile(r'<figure\s+name="([^"]*)"\s+bbox="([^"]+)"')
FONT = re.compile(r'<text\s[^>]*?font="([^"]*)"[^>]*?size="([^"]+)"[^>]*?>(.)</text>')


def need(*tools):
    missing = [t for t in tools if not shutil.which(t)]
    if missing:
        sys.exit(
            f"missing required tool(s): {', '.join(missing)}\n"
            "  poppler  -> brew install poppler\n"
            "  pdf2txt.py -> pipx install pdfminer.six"
        )


def run(cmd, **kw):
    return subprocess.run(cmd, check=True, capture_output=True, text=True, **kw).stdout


def page_size_pt(pdf):
    for line in run(["pdfinfo", pdf]).splitlines():
        if line.startswith("Page size:"):
            m = re.search(r"([\d.]+) x ([\d.]+)", line)
            return float(m.group(1)), float(m.group(2))
    sys.exit("could not read page size from pdfinfo")


def parse_pages(xml):
    """-> [(page_id, page_body_xml)] in document order."""
    parts = re.split(r'<page id="(\d+)"', xml)[1:]
    return list(zip(parts[0::2], parts[1::2]))


def line_metrics(body, page_h):
    """Every text line as (top_mm, left_mm, right_mm, size_pt, text).

    left/right come from the first and last NON-SPACE glyph. pdfminer pads
    justified lines with space glyphs, so the raw textline bbox overstates the
    width -- that is what makes a measured column look 10mm wider than it is.
    """
    out = []
    for m in LINE.finditer(body):
        chars = CHAR.findall(m.group(2))
        if not chars:
            continue
        keep = [c for c in chars if c[2].strip()]
        if not keep:
            continue
        x0 = float(keep[0][0].split(",")[0])
        x1 = float(keep[-1][0].split(",")[2])
        top = page_h - float(m.group(1).split(",")[3])
        text = "".join(c[2] for c in chars).strip()
        out.append((top * PT_MM, x0 * PT_MM, x1 * PT_MM, float(keep[0][1]), text))
    out.sort(key=lambda r: (round(r[0], 1), r[1]))
    return out


def image_rows(pdf):
    """pdfimages -list, in the same order as `pdfimages -all` writes files."""
    rows = []
    for line in run(["pdfimages", "-list", pdf]).splitlines()[2:]:
        f = line.split()
        if len(f) < 5 or f[2] not in ("image", "smask"):
            continue
        rows.append({"page": int(f[0]), "kind": f[2], "w": int(f[3]), "h": int(f[4])})
    return rows


def composite_masks(assets, pdf):
    """Re-attach soft masks as alpha, and report which file to actually use.

    A logo with a drop shadow or a scanned signature is stored as an opaque
    raster plus a separate greyscale soft mask. Use the raster on its own and it
    renders as a grey box sitting on the page -- the single most visible way a
    transcription goes wrong. -> {index: (filename, has_alpha)}
    """
    files = sorted(f for f in os.listdir(assets) if f.startswith("im-"))
    rows = image_rows(pdf)
    use = {}
    if len(files) != len(rows):
        # ordering assumption broken; fall back to using every file as-is
        for i, f in enumerate(files):
            use[i] = (f, False)
        return use, rows
    i = 0
    while i < len(rows):
        r, f = rows[i], files[i]
        nxt = rows[i + 1] if i + 1 < len(rows) else None
        if (r["kind"] == "image" and nxt and nxt["kind"] == "smask"
                and (nxt["w"], nxt["h"]) == (r["w"], r["h"])):
            out = os.path.splitext(f)[0] + "-rgba.png"
            p = subprocess.run(
                ["magick", os.path.join(assets, f), os.path.join(assets, files[i + 1]),
                 "-alpha", "off", "-compose", "CopyOpacity", "-composite",
                 "PNG32:" + os.path.join(assets, out)],
                capture_output=True,
            )
            use[i] = (out, True) if p.returncode == 0 else (f, False)
            i += 2
        else:
            if r["kind"] == "image":
                use[i] = (f, False)
            i += 1
    return use, rows


def placements(pdf, work, page_w_mm, page_h_mm):
    """Where each raster is drawn, in mm, via `pdftohtml -xml`.

    pdfminer emits a <figure> for only some placements, so it silently leaves
    half the images on a page unplaced. pdftohtml reports every one, and poppler
    is already a hard dependency here. Its coordinates are in a page-pixel
    space, so scale by the page width.
    """
    base = os.path.join(work, "_ph")
    # Give it an output path inside the work dir: pdftohtml drops the images it
    # extracts next to its output, and next to the *input* would mean writing
    # into whatever folder the user's PDF lives in.
    subprocess.run(["pdftohtml", "-xml", "-q", pdf, base],
                   capture_output=True, text=True)
    xmlp = base + ".xml"
    if not os.path.exists(xmlp):
        return []
    xml = open(xmlp, encoding="utf-8", errors="replace").read()
    out, scale = [], None
    for m in re.finditer(r'<page number="(\d+)"[^>]*width="([\d.]+)"|'
                         r'<image top="(-?[\d.]+)" left="(-?[\d.]+)"'
                         r' width="([\d.]+)" height="([\d.]+)"', xml):
        if m.group(1):
            page, scale = int(m.group(1)), page_w_mm / float(m.group(2))
            continue
        if scale is None:
            continue
        t, l, w, h = (float(m.group(i)) for i in (3, 4, 5, 6))
        out.append({"page": page, "left": l * scale, "top": t * scale,
                    "w": w * scale, "h": h * scale})
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("pdf")
    ap.add_argument("--out", required=True)
    ap.add_argument("--preview-dpi", type=int, default=110)
    ap.add_argument("--hires-dpi", type=int, default=300)
    a = ap.parse_args()

    need("pdftoppm", "pdftotext", "pdfimages", "pdfinfo", "pdftohtml", "magick")
    if not os.path.exists(a.pdf):
        sys.exit(f"no such file: {a.pdf}")
    # pdf2txt.py is what gives exact glyph coordinates. Without it the renders,
    # text and images are still worth having, so degrade instead of refusing --
    # but say so loudly, because building without geometry.txt means guessing.
    have_glyphs = bool(shutil.which("pdf2txt.py"))

    out = a.out
    for d in ("pages", "hires", "assets"):
        os.makedirs(os.path.join(out, d), exist_ok=True)

    pw, ph = page_size_pt(a.pdf)
    print(f"page size {pw:.2f} x {ph:.2f} pt  ({pw*PT_MM:.1f} x {ph*PT_MM:.1f} mm)")

    print("rendering previews + hires ...")
    run(["pdftoppm", "-r", str(a.preview_dpi), "-png", a.pdf, os.path.join(out, "pages", "pg")])
    run(["pdftoppm", "-r", str(a.hires_dpi), "-png", a.pdf, os.path.join(out, "hires", "pg")])
    run(["pdftotext", "-layout", a.pdf, os.path.join(out, "text.txt")])
    run(["pdfimages", "-all", "-p", a.pdf, os.path.join(out, "assets", "im")])

    pages, fonts = [], Counter()
    if have_glyphs:
        print("extracting glyph geometry ...")
        xmlp = os.path.join(out, "_pdfminer.xml")
        run(["pdf2txt.py", "-t", "xml", "-o", xmlp, a.pdf])
        xml = open(xmlp, encoding="utf-8", errors="replace").read()
        pages = parse_pages(xml)

        with open(os.path.join(out, "geometry.txt"), "w", encoding="utf-8") as fh:
            fh.write(f"# page {pw*PT_MM:.2f} x {ph*PT_MM:.2f} mm   all values mm, pt for size\n")
            fh.write("# top     left    right   size  text\n")
            for pid, body in pages:
                fh.write(f"\n===== page {pid}\n")
                for top, x0, x1, sz, text in line_metrics(body, ph):
                    fh.write(f"{top:7.2f} {x0:7.2f} {x1:7.2f} {sz:6.2f}  {text}\n")

        for _, body in pages:
            for fam, sz, ch in FONT.findall(body):
                if ch.strip():
                    fonts[(fam, float(sz))] += 1
    else:
        print("!! pdf2txt.py not found -- no geometry.txt, no font census.")
        print("!! install it before building: pipx install pdfminer.six")

    assets = os.path.join(out, "assets")
    use, rows = composite_masks(assets, a.pdf)
    boxes = placements(a.pdf, out, pw * PT_MM, ph * PT_MM)

    # Both lists are in document order per page, so zip them page by page.
    by_page = {}
    for i, r in enumerate(rows):
        if i in use:
            by_page.setdefault(r["page"], []).append((r, use[i]))
    placed = []
    for pg, items in sorted(by_page.items()):
        pboxes = [b for b in boxes if b["page"] == pg]
        for k, (r, (fname, alpha)) in enumerate(items):
            placed.append((r, fname, alpha, pboxes[k] if k < len(pboxes) else None))

    with open(os.path.join(assets, "manifest.tsv"), "w", encoding="utf-8") as fh:
        fh.write("page\tfile\tpx\talpha\tleft_mm\ttop_mm\twidth_mm\theight_mm\tar_skew\n")
        for r, fname, alpha, b in placed:
            px = f"{r['w']}x{r['h']}"
            al = "yes" if alpha else "no"
            if b and b["h"] > 0:
                # ar_skew is how far the placement box departs from the raster's
                # own aspect ratio. Over ~10% the PDF is stretching the image, so
                # set width AND height in CSS instead of letting one derive.
                d = abs((b["w"] / b["h"]) - (r["w"] / r["h"])) / (r["w"] / r["h"])
                skew = f"{d*100:.0f}% STRETCHED" if d > 0.10 else f"{d*100:.0f}%"
                fh.write(f"{r['page']}\t{fname}\t{px}\t{al}\t{b['left']:.2f}\t{b['top']:.2f}"
                         f"\t{b['w']:.2f}\t{b['h']:.2f}\t{skew}\n")
            else:
                fh.write(f"{r['page']}\t{fname}\t{px}\t{al}\t?\t?\t?\t?\t-\n")

    per_page = Counter(r["page"] for r, _, _, _ in placed)
    n_alpha = sum(1 for _, _, al, _ in placed if al)
    npages = len(pages) or len(
        [f for f in os.listdir(os.path.join(out, "pages")) if f.endswith(".png")]
    )
    ranked = fonts.most_common()
    with open(os.path.join(out, "report.md"), "w", encoding="utf-8") as fh:
        fh.write(f"# probe: {os.path.basename(a.pdf)}\n\n")
        fh.write(f"- pages: **{npages}**\n")
        fh.write(f"- page size: **{pw*PT_MM:.1f} x {ph*PT_MM:.1f} mm** ({pw:.2f} x {ph:.2f} pt)\n")
        fh.write(f"- embedded rasters: **{len(placed)}** ({n_alpha} with a soft mask, use the `-rgba.png` copy)\n\n")
        if ranked:
            fh.write("## fonts and sizes (by glyph count)\n\n")
            fh.write("| font | pt | glyphs | likely role |\n|---|---|---|---|\n")
            for (fam, sz), n in ranked[:14]:
                role = "body" if (fam, sz) == ranked[0][0] else ""
                fh.write(f"| {fam} | {sz:.2f} | {n} | {role} |\n")
        else:
            fh.write("## fonts and sizes\n\nUNAVAILABLE - pdf2txt.py is not installed, so "
                     "there is no font census and no `geometry.txt`. Install it "
                     "(`pipx install pdfminer.six`) and re-run before building; the font and "
                     "its size are the two facts fidelity depends on most.\n")
        fh.write("\n## images per page\n\n")
        for p in range(1, npages + 1):
            fh.write(f"- page {p}: {per_page.get(p,0)}\n")

    print(f"\nwrote {out}/report.md" + (", geometry.txt" if ranked else "")
          + ", assets/manifest.tsv")
    print(f"  previews: {out}/pages/pg-*.png   <- read these next")
    if ranked:
        print(f"  body font looks like: {ranked[0][0][0]} @ {ranked[0][0][1]:.2f}pt")


if __name__ == "__main__":
    main()

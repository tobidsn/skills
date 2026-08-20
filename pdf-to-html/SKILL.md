---
name: pdf-to-html
description: Rebuild a PDF as one self-contained HTML file that keeps the original font, layout, and page breaks — measured from the source at 300 dpi, then verified back against it rule by rule. Use whenever someone hands over a PDF and wants an HTML version of it: "convert this PDF to HTML", "same font and structure", "pixel perfect", "bikin versi HTML-nya", a form or policy document that has to still look like itself, or a print-ready page they can Cmd-P back to the identical sheet. Also use when an existing PDF-to-HTML attempt came out drifted, clipped, or off-brand and needs measuring properly. Not for pulling data or plain text out of a PDF, and not for redesigning a document into something responsive.
---

# pdf-to-html — measure it, don't eyeball it

Ship **one `.html` file**. Images inlined, so it opens on a double-click, survives being emailed, and nothing can go missing on the way. Same font as the source, same page breaks, print-ready.

The whole difficulty is that a PDF looks easy to copy and is not. Every position in the output should come from a measurement, never from a guess — a box that is 3 mm off, a leading that is 0.4 pt short, an indent ladder built by eye: each one is invisible on its own and together they read as "close, but wrong". So the pipeline measures the source, builds against those numbers, then renders the result back to PDF and diffs it against the original.

## Setup

```bash
brew install poppler imagemagick        # pdftoppm/pdftotext/pdfimages/pdfinfo, magick
pipx install pdfminer.six               # pdf2txt.py, for exact glyph coordinates
```

Chrome, Chromium, or Edge — any one — for the verify render. `scripts/verify.py` finds it.

## Pipeline

### 1. Probe

```bash
python3 scripts/probe.py input.pdf --out work/
```

Writes into `work/`:

| | |
|---|---|
| `report.md` | page count, page size in mm, **font and pt-size census** |
| `pages/pg-N.png` | 110 dpi previews — you read these in step 2 |
| `hires/pg-N.png` | 300 dpi, what `measure.py` reads |
| `geometry.txt` | every text line: `top left right size text`, all mm |
| `assets/` | every embedded raster, plus `im-*-rgba.png` where a soft mask was re-attached as alpha |
| `assets/manifest.tsv` | per raster: which file to use, whether it has alpha, and where it is drawn in mm |
| `text.txt` | `pdftotext -layout`, so you copy content instead of retyping it |

Read `report.md` first. The top row of the font census is the body font and size, and those two facts do more for fidelity than anything else you will do — with the same font at the same size in a column of the same width, the browser breaks lines where the source did, and every paragraph below stays in sync.

### 2. Look at the pages

Read `work/pages/pg-*.png` with the Read tool. All of them. You are looking for things no coordinate dump will tell you:

- **The skeleton.** How many section bars, which boxes are one column and which are two, where a box continues onto the next page.
- **Content images vs decorative artwork.** A logo, a banner, a footer band: artwork, place it as an image. A chart or a scanned table: content, still an image, but its text will not be selectable and you should say so on delivery.
- **Text baked into artwork.** Banner headings and footer contact strips are often part of the graphic. Check `text.txt` — if the words are not in the text layer, they live in the image. Do not retype them on top; you would render them twice. Put them in the image's `alt`.
- **Quirks worth reproducing.** Boxes clipped by a footer band, a section bar wider than the boxes below it, two different left margins on the same page. These come from how the source Word file was built. They are the document, not mistakes — copy them.

### 3. Measure, then build

`scripts/measure.py` takes and returns **millimetres**, because that is the unit the CSS is written in. Converting px to mm in your head is where 2 mm errors come from.

| Question | Command |
|---|---|
| Where is every box border and table rule? | `rules hires/pg-2.png --min-len 40` |
| Where does this banner start and end vertically? | `bands hires/pg-1.png --x0 5 --x1 205 --min-px 20` |
| Where do the columns split on this row? | `cols hires/pg-7.png --y 119` |
| What is this list's indent ladder, and how wide is the justified measure? | `rows hires/pg-2.png --box 11,40,102,105` |
| What exact colour is that bar / that text? | `colors hires/pg-1.png --box 20,66,180,73` |

`rules` is the workhorse: one call per page gives you every box as `left/top/width/height` ready to paste into CSS.

Start from `assets/page-template.html`. It carries the primitives — `.page`, `.band`, `.box`, the flex hanging-indent row, the table, the print rules — with each measured value marked so you know what to replace.

Two mappings are worth stating explicitly because they are easy to get subtly wrong:

**Leading.** Successive `top` values in `geometry.txt` give you the line pitch. Set it in absolute units (`--lh: 11.64pt`), never in `em`. Then it is exactly what you measured, and it stays put if you later nudge the font size.

**Indent ladders.** For each level, `margin-left` = marker x − the box's text origin, and the marker width = text x − marker x. Both read straight off `geometry.txt`. Give each level a class, not an inline style — a ladder that turns out wrong is then one edit instead of forty.

Keep the images as ordinary files in `work/assets/` while you iterate. Inlining is the last step.

### 4. Inline and verify

```bash
python3 scripts/inline.py work/draft.html --out work/final.html
python3 scripts/verify.py work/final.html --pdf input.pdf --out verify/
```

`verify.py` renders your HTML back to PDF through headless Chrome and reports three things:

- **shape** — page count and page size
- **geometry** — every long rule matched between the two renders, with the drift in mm. Silence means everything landed inside tolerance.
- **text** — normalised similarity of the extracted text, and exactly what differs. This is what catches a dropped sentence or a typo introduced while retyping.

Then **read `verify/cmp-N.png`** — original left, yours right, every page. The automated checks are objective but blind: they cannot see a wrong colour, text overlapping other text, a squashed logo, or a highlight that has swallowed a table rule. Both halves matter, and neither substitutes for the other. Fix, re-run, repeat until geometry passes and the images look right.

Aim for text similarity above 0.995 and worst geometry drift under 1 mm. Both are reachable; on the document this skill was built from, the drift settles at 0.00 mm.

### 5. Deliver

Hand over the single `.html`. Say what it is, then be specific about the two things a reader would otherwise discover for themselves: any text that is image-only and therefore not selectable, and any place the layout knowingly differs. Reporting a 0.997 text match and one image-only contact strip is worth more than claiming it is perfect.

## What will break

Every one of these produced output that looked plausible and was wrong.

**Word compresses word spacing when it justifies; CSS only expands.** On a line where the next word almost fits, Word squeezes the spaces and takes it. `text-align: justify` cannot, so that line wraps and every line under it shifts down. Expect a handful of paragraphs across a long document to run one line longer.

Do not chase it by shrinking the font or adding negative letter-spacing — you will fit words the source did not and desynchronise in the other direction. Widen the measure by a fraction of a millimetre if it helps, then let the affected box absorb the extra line. Check that nothing lands under a footer band or outside its border, because that is the only version of this that actually loses content.

**An inline highlight is taller than its line-height.** A `<span>` background is painted over the font's full ascent-plus-descent no matter what `line-height` says, so inside a tight table row it covers the collapsed row rule and erases it. The table looks like it lost a border for no reason. Box the highlight: `display:inline-block;height:<row pitch - 1mm>`.

**A text line's bbox includes its padding spaces.** pdfminer pads justified lines with space glyphs, so a raw `textline` bbox can overstate the width by 10 mm and convince you a column is far wider than it is. `probe.py` already measures from the first and last non-space glyph — trust `geometry.txt`, not a bbox you parsed yourself.

**A figure box need not match its raster's aspect ratio.** PDFs stretch images. When `manifest.tsv` says `STRETCHED`, set both `width` and `height` in CSS; deriving one from the other distorts the artwork.

**A logo or a scanned signature is stored as an opaque raster plus a separate soft mask.** Place the raster on its own and it arrives as a grey box sitting on the page — loud, and easy to stare past when you are looking at coordinates. `probe.py` re-attaches the mask and writes `im-*-rgba.png`; `manifest.tsv` marks which files have alpha. Use those copies.

**A box that runs off the bottom of a page may still be closed.** Some are genuinely clipped by the footer band and you should reproduce that. Others have a bottom border sitting a millimetre above it, which is easy to mistake for a stray text row. `rules` tells you which — and this is exactly the kind of thing the geometry check catches after you have stopped looking.

**One document can have two grids.** A section bar wider than the boxes beneath it, or a right-hand box that reaches closer to the page edge than anything else, is normal in a document authored in Word. Measure every box; do not derive the rest from the first one.

**`file:` URLs are blocked in Playwright.** Not in Chrome's CLI, which is why `verify.py` shells out to `--headless --print-to-pdf` and needs no MCP server. If you do drive a browser through Playwright, serve the directory over localhost instead.

**When a font is not embedded, the reference render is itself a substitution.** Run `pdffonts source.pdf` and look at the `emb` column. For a font marked `no`, poppler picks something off your system, so `cmp-N.png` shows *its* substitution on the left and Chrome's on the right — text will differ in weight and width, and the original can even look *less* bold than yours where the PDF asked for a bold face it never carried. Read the font names in `geometry.txt` to decide what is truly bold or italic; do not infer weight from how the left-hand image looks.

**A rule right at `--min-len` flickers.** A divider a hair under the length floor shows up as `UNPAIRED` on one side and nothing on the other. Set `--min-len` a little below the shortest rule you actually care about.

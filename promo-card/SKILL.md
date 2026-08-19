---
name: promo-card
description: Turn a skill, feature, or release into a landscape PNG announcement card — self-contained HTML rendered to an exact-size image through headless Chrome, plus an optional shareable link. Use when someone wants a promo image, an announcement graphic, a launch card for a team channel, or asks to render an HTML page to a PNG at a specific pixel size. Not for charts or diagrams.
---

# promo-card — HTML in, exact-size PNG out

Ship three things: a `WIDTHxHEIGHT` PNG that drops straight into a chat channel, the HTML that produced it, and — if they want a link too — a published page.

The PNG is the deliverable people actually use, because a chat channel renders an image inline and a link does not. Everything here exists to make one screenshot land at exactly the size you promised, on the first look.

## Pipeline

1. **Get the facts, don't invent them.** Version numbers, install commands, tag lists, and URLs go on the card verbatim. Read them from the source (`SKILL.md` frontmatter, the catalog, the CLI output) — a promo card with a wrong install command is worse than no card.
2. **Pick the size.** `1600×900` (16:9) is the default for a landscape card. `1200×1200` for square, `1080×1350` for portrait feeds.
3. **Write the HTML** with a fixed-size `.stage` at exactly that size. Start from `assets/card-template.html`.
4. **Serve it over localhost** with `assets/serve.py`, never `file:`.
5. **Verify in the DOM**, then screenshot. `assets/verify.js` returns the assertions.
6. **Move the PNG out of the repo** and clean up.

## The five things that will break

Every one of these produced a wrong image that looked plausible. Check them by name.

**`file:` is blocked.** Playwright refuses `file:///…` outright. Serve the directory instead:

```bash
python3 assets/serve.py 8792 &        # then http://127.0.0.1:8792/card.html
```

**`python -m http.server` corrupts your arrows.** It sends `text/html` with no charset, so the browser decodes as latin-1 and every `→` becomes `â†'`, every `—` becomes `â€"`. The card renders "correctly" and is quietly garbage. `assets/serve.py` sets `charset=utf-8` — that is the only reason it exists.

**`box-sizing` defaults to `content-box`.** A `.stage` set to `1600×900` with `padding: 68px 76px` measures **1752×1024**, and your 16:9 card is not 16:9. Always:

```css
.stage, .stage * { box-sizing: border-box; }
```

**A reflow breakpoint at or above the target width swallows the layout.** `@media (max-width: 1640px)` matches at a 1600px viewport, so the mobile stack applies and the poster layout never renders. Set the breakpoint to **target − 1**:

```css
@media (max-width: 1599px) { /* reflow for real screens, not for the render */ }
```

**The screenshot saves to the working directory.** Playwright writes `./name.png` relative to the process cwd, which is usually the git repo. Move it to the scratchpad and delete the `.playwright-mcp/` directory it also leaves behind, then confirm `git status` is clean.

## Verify before you hand it over

Run `assets/verify.js` through `browser_evaluate` and require all four:

| Assertion | Why it matters |
|-----------|----------------|
| `exact: true` | The stage is the promised pixel size, not padded past it |
| `scrollsH: false` | No horizontal overflow — a scrollbar means content is cut off-frame |
| `clipped: false` | Nothing inside a fixed-height block is hidden by `overflow` |
| `bottomAligned` | Columns end together; a short column reads as a layout bug |

Then **read the PNG back** with the Read tool and look at it. The DOM assertions catch geometry; only your eyes catch a dead area, a faint-to-invisible color, or text that collides. Both checks, every time.

If the content genuinely does not fit, cut content or reduce type size — do not grow the stage past the size you promised.

## Design

Load the `artifact-design` skill and follow it: pick a palette of 4–6 named values, pair a display and a body face, ground the choices in the subject. Then, specific to a card:

- **The subject's own artifact is the hero.** For a code skill, that's real output — a tree, a terminal block, a diff. Not a headline about the output. A card that shows the thing beats a card that describes it.
- **Label anything illustrative.** If the sample output isn't a real trace of a real repo, write `example output` on it. Fabricated file paths presented as real is the one mistake that damages trust in the thing you're promoting.
- **No webfont links.** The Artifact CSP blocks font CDNs and a headless Chrome may not have your font either — both fail silently to a fallback. Use system stacks, or inline a face as a data URI.
- **Design both themes** via `prefers-color-scheme` plus `:root[data-theme]`, and screenshot both. Light usually reads better in a chat channel; give them the choice.
- **Two commands, maximum.** A card answers "what is it" and "how do I get it". Everything else belongs in the thread.

## Optional: a link as well as an image

Publish the same HTML with the `Artifact` tool for a shareable page. Two things to tell the recipient, because both cause confusion:

- Artifacts are **private by default** and must be shared from the page's share menu.
- An unauthorized viewer gets a **404, not a login prompt** — so "the link is broken" almost always means they're signed into a different account.

The template's reflow breakpoint is what makes the published page usable on a real screen; the fixed stage is only for the render.

## Files

- `assets/card-template.html` — 1600×900 landscape card: token block, two-column grid, output pane, both themes, correct breakpoint.
- `assets/serve.py` — static server that sends `charset=utf-8`. Usage: `python3 serve.py [port]`.
- `assets/verify.js` — the four DOM assertions, for `browser_evaluate`.

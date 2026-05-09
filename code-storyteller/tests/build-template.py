#!/usr/bin/env python3
"""
Build ~/.claude/skills/code-storyteller/template.html for the
director's-commentary step-through experience.

Design model:
  - Two-column full-bleed: left = macOS code window (dark navy),
    right = cream narrative panel.
  - Step-through navigation (← / → / space / dots / Prev-Next).
  - A single STORY = { title, steps[] } JSON object drives everything.
    Each step: { chapter?, title, body, problem, move, closing?,
                 filename, code, typeline }.
  - Backticks `inside any string` become inline code chips at render time.
  - Self-contained HTML: fonts (Source Serif 4 + Source Sans 3 +
    JetBrains Mono) + Prism syntax highlighting all base64-inlined.

Substitution placeholder: {{STORY_JSON}} (single replacement).
Plus {{TITLE}} and {{GENERATED_AT}} for <title> and footer.
"""
import base64
from pathlib import Path

SKILL = Path.home() / ".claude/skills/code-storyteller"
ASSETS = SKILL / "tests/_assets"
FONTS = ASSETS / "fonts"


def b64(p: Path) -> str:
    return base64.b64encode(p.read_bytes()).decode("ascii")


# ------------------------------------------------------------------
# 1. @font-face — variable fonts inlined as base64 woff2
# ------------------------------------------------------------------
FONT_FACE = f"""
@font-face {{
  font-family: 'Source Serif 4';
  font-style: normal;
  font-weight: 200 900;
  font-display: swap;
  src: url(data:font/woff2;base64,{b64(FONTS / 'source-serif-4-wght.woff2')}) format('woff2');
}}
@font-face {{
  font-family: 'Source Serif 4';
  font-style: italic;
  font-weight: 200 900;
  font-display: swap;
  src: url(data:font/woff2;base64,{b64(FONTS / 'source-serif-4-wght-italic.woff2')}) format('woff2');
}}
@font-face {{
  font-family: 'Source Sans 3';
  font-style: normal;
  font-weight: 200 900;
  font-display: swap;
  src: url(data:font/woff2;base64,{b64(FONTS / 'source-sans-3-wght.woff2')}) format('woff2');
}}
@font-face {{
  font-family: 'JetBrains Mono';
  font-style: normal;
  font-weight: 100 800;
  font-display: swap;
  src: url(data:font/woff2;base64,{b64(FONTS / 'jetbrains-mono-wght.woff2')}) format('woff2');
}}
"""

# ------------------------------------------------------------------
# 2. CSS — dark navy code side / cream narrative side
# ------------------------------------------------------------------
CSS = """
:root {
  /* Code side */
  --code-bg:        #0d1419;
  --code-surround:  #050a0f;
  --code-border:    #1d2730;
  --code-text:      #c8d3df;
  --code-muted:     #5d6b7a;

  /* Narrative side */
  --paper:          #f7f3ea;
  --paper-soft:     #faf7f0;
  --paper-card:     #ffffff;
  --ink:            #1a1612;
  --ink-soft:       #3d342b;
  --ink-muted:      #8a7a6c;
  --hairline:       #e6dfd0;

  /* Accents */
  --accent-orange:  #c95a2b;
  --accent-orange-soft: #f0e1d2;
  --accent-green:   #0e7a4f;
  --accent-green-soft: #d8e9dc;

  /* Inline code chips */
  --chip-bg:        #e2e8f3;
  --chip-text:      #1f3469;

  --font-serif:     'Source Serif 4', 'Iowan Old Style', Georgia, 'Times New Roman', serif;
  --font-sans:      'Source Sans 3', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
  --font-mono:      'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
}

*, *::before, *::after { box-sizing: border-box; }
html, body { margin: 0; padding: 0; height: 100%; }
body {
  background: var(--code-surround);
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 17px;
  line-height: 1.65;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-rendering: optimizeLegibility;
  overflow: hidden;
}

/* ============== Stage (full-bleed two columns) ============== */
.storyteller {
  height: 100vh;
  display: grid;
  grid-template-rows: 1fr auto;
}

.stage {
  display: grid;
  grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
  min-height: 0;
  overflow: hidden;
}
@media (max-width: 1080px) {
  .stage {
    grid-template-columns: 1fr;
    grid-template-rows: 1fr 1fr;
  }
  body { overflow: auto; }
  .storyteller { height: auto; min-height: 100vh; }
}

/* ============== LEFT — code side ============== */
.code-side {
  background: var(--code-surround);
  padding: 56px 40px 56px 56px;
  display: flex;
  align-items: stretch;
  justify-content: center;
  overflow: hidden;
}
@media (max-width: 1080px) {
  .code-side { padding: 32px 24px; }
}

.code-window {
  width: 100%;
  max-width: 720px;
  align-self: stretch;
  background: var(--code-bg);
  border: 1px solid var(--code-border);
  border-radius: 14px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow:
    0 30px 60px -24px rgba(0, 0, 0, 0.7),
    0 0 0 1px rgba(255, 255, 255, 0.02) inset;
}

.window-chrome {
  display: flex;
  align-items: center;
  padding: 14px 18px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  flex-shrink: 0;
}
.window-chrome .dots {
  display: flex;
  gap: 8px;
  align-items: center;
}
.window-chrome .dot {
  width: 12px; height: 12px;
  border-radius: 50%;
  display: inline-block;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.18);
}
.window-chrome .dot.r { background: #ff5f57; }
.window-chrome .dot.y { background: #febc2e; }
.window-chrome .dot.g { background: #28c840; }
.window-chrome .filename {
  margin-left: auto;
  font-family: var(--font-mono);
  font-size: 12px;
  color: var(--ink-muted);
  font-weight: 500;
  letter-spacing: 0.02em;
}

.code-body {
  flex: 1;
  overflow: auto;
  min-height: 0;
}
.code-body pre,
.code-body pre[class*="language-"] {
  margin: 0;
  padding: 28px 26px 28px 30px;
  background: transparent !important;
  color: var(--code-text);
  font-size: 13.5px;
  line-height: 1.78;
  text-shadow: none;
}
.code-body code,
.code-body code[class*="language-"] {
  font-family: var(--font-mono);
  font-size: 13.5px;
  font-weight: 400;
  font-feature-settings: "calt" 1;
  text-shadow: none;
  background: transparent;
  color: var(--code-text);
}
.code-body pre::-webkit-scrollbar { width: 8px; height: 8px; background: transparent; }
.code-body pre::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 8px; }
.code-body pre::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.16); }

/* Prism token palette (dark, refined) */
.token.comment, .token.prolog, .token.doctype, .token.cdata { color: #5d6b7a; font-style: italic; }
.token.punctuation { color: #a4b1be; }
.token.namespace { opacity: 0.75; }
.token.boolean, .token.number, .token.constant, .token.symbol { color: #79c0ff; }
.token.tag { color: #79c0ff; }
.token.property { color: #a3d0a3; }
.token.selector, .token.attr-name, .token.string, .token.char, .token.builtin { color: #a3d0a3; }
.token.operator, .token.entity, .token.url, .language-css .token.string, .style .token.string { color: #f0a36b; }
.token.atrule, .token.attr-value, .token.keyword { color: #f0a36b; }
.token.function { color: #c39bd3; }
.token.regex, .token.important, .token.variable { color: #f0a36b; }
.token.class-name { color: #fbb775; }

/* Diff highlighting (PR mode) */
.language-diff .token.inserted,
.token.inserted-sign  { color: #a3d0a3; background: rgba(76, 175, 80, 0.08); display: inline-block; width: 100%; }
.language-diff .token.deleted,
.token.deleted-sign   { color: #ff8a87; background: rgba(244, 67, 54, 0.08); display: inline-block; width: 100%; }
.language-diff .token.coord,
.token.coord          { color: #79c0ff; font-style: italic; }
.language-diff .token.diff { color: var(--code-text); }

/* Type bar (footer of code window) */
.type-bar {
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  padding: 14px 20px 16px;
  display: flex;
  align-items: center;
  gap: 16px;
  font-family: var(--font-mono);
  font-size: 11.5px;
  position: relative;
  flex-shrink: 0;
}
.type-bar::before {
  content: '';
  position: absolute;
  top: -1px;
  left: 20px;
  width: 64px;
  height: 2px;
  background: rgba(255, 255, 255, 0.85);
  border-radius: 2px;
}
.type-bar .type-label {
  color: var(--ink-muted);
  letter-spacing: 0.02em;
}
.type-bar .type-value {
  color: var(--code-text);
  font-weight: 500;
  margin-left: auto;
  text-align: right;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Inline code chip in type bar */
.type-bar .chip,
.type-bar code {
  font-family: var(--font-mono);
  font-size: 11px;
  font-weight: 500;
  background: rgba(255, 255, 255, 0.05);
  color: var(--code-text);
  padding: 1px 6px;
  border-radius: 4px;
  letter-spacing: 0;
  white-space: nowrap;
}

/* ============== RIGHT — narrative side ============== */
.story-side {
  background: var(--paper);
  color: var(--ink);
  padding: 40px 56px 40px 56px;
  overflow: hidden;
  position: relative;
  background-image: radial-gradient(rgba(0,0,0,0.018) 1px, transparent 1px);
  background-size: 3px 3px;
  display: flex;
  flex-direction: column;
  gap: 0;
  min-height: 0;
}
@media (max-width: 1080px) {
  .story-side { padding: 32px 24px; overflow: auto; }
}

/* Breadcrumb (story · chapter · step) */
.breadcrumb {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0 0 22px;
  font-family: var(--font-mono);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  line-height: 1.4;
}
.breadcrumb .crumb-story  { color: var(--accent-green); }
.breadcrumb .crumb-chapter { color: var(--ink-muted); }
.breadcrumb .crumb-step    { color: var(--accent-orange); }
.breadcrumb .crumb-sep {
  color: var(--ink-muted);
  opacity: 0.5;
  font-weight: 400;
  letter-spacing: 0;
}
.breadcrumb [data-chapter]:empty,
.breadcrumb [data-chapter]:empty + .crumb-sep { display: none; }

.headline {
  font-family: var(--font-serif);
  font-weight: 600;
  font-size: clamp(24px, 2.6vw, 36px);
  line-height: 1.1;
  letter-spacing: -0.018em;
  margin: 0 0 18px;
  color: var(--ink);
  max-width: 24ch;
}
.headline em { font-style: italic; font-weight: 600; }

.body-para {
  font-family: var(--font-serif);
  font-weight: 400;
  font-size: clamp(15px, 1.2vw, 17px);
  line-height: 1.55;
  margin: 0 0 18px;
  color: var(--ink-soft);
  max-width: 56ch;
}
.body-para em { font-style: italic; font-weight: 400; }
.body-para strong { font-weight: 600; color: var(--ink); }

/* Cards */
.card-pair {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin: 0 0 18px;
  max-width: 60ch;
}
@media (max-width: 720px) {
  .card-pair { grid-template-columns: 1fr; }
}
.card {
  background: var(--paper-card);
  border: 1px solid var(--hairline);
  border-radius: 8px;
  padding: 12px 14px 13px;
}
.card-label {
  font-family: var(--font-sans);
  font-weight: 700;
  font-size: 10px;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  margin: 0 0 6px;
}
.card-problem .card-label { color: var(--accent-orange); }
.card-move    .card-label { color: var(--accent-green); }
.card-body {
  font-family: var(--font-sans);
  font-weight: 400;
  font-size: 13px;
  line-height: 1.45;
  color: var(--ink);
  margin: 0;
}

.closing {
  font-family: var(--font-sans);
  font-weight: 400;
  font-size: clamp(13px, 1vw, 15px);
  line-height: 1.55;
  color: var(--ink-soft);
  margin: 0;
  max-width: 60ch;
}
.closing:empty { display: none; }
.closing em { font-style: italic; }

/* Inline code chips (work everywhere via .chip class) */
.chip {
  font-family: var(--font-mono);
  font-size: 0.86em;
  font-weight: 500;
  background: var(--chip-bg);
  color: var(--chip-text);
  padding: 1px 7px;
  border-radius: 5px;
  letter-spacing: 0;
  white-space: nowrap;
  display: inline-block;
  line-height: 1.4;
}

/* ============== Footer rail ============== */
.controls {
  background: var(--code-surround);
  border-top: 1px solid rgba(255, 255, 255, 0.05);
  padding: 14px 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  flex-shrink: 0;
  font-family: var(--font-mono);
  font-size: 12px;
  color: rgba(255, 255, 255, 0.55);
}

.controls .nav-btn {
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: rgba(255, 255, 255, 0.75);
  font-family: var(--font-mono);
  font-size: 12px;
  font-weight: 500;
  padding: 7px 14px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
  letter-spacing: 0.02em;
}
.controls .nav-btn:hover:not(:disabled) {
  color: #fff;
  border-color: rgba(255, 255, 255, 0.32);
}
.controls .nav-btn:disabled {
  opacity: 0.32;
  cursor: not-allowed;
}

.controls .dots-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
}
.dots {
  display: flex;
  gap: 6px;
  align-items: center;
}
.dots .dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  border: none;
  padding: 0;
  background: rgba(255, 255, 255, 0.18);
  cursor: pointer;
  transition: all 0.15s ease;
}
.dots .dot:hover { background: rgba(255, 255, 255, 0.32); }
.dots .dot.active {
  background: var(--accent-orange);
  width: 22px;
  border-radius: 4px;
}

.controls .counter {
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.05em;
}
.controls .counter b {
  color: rgba(255, 255, 255, 0.85);
  font-weight: 500;
}

.controls .hint {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.32);
  letter-spacing: 0.04em;
}
@media (max-width: 720px) {
  .controls .hint { display: none; }
  .controls { padding: 10px 16px; }
}
"""

# ------------------------------------------------------------------
# 3. Render engine — drives the slideshow from STORY object
# ------------------------------------------------------------------
RENDER_JS = """
(function () {
  const els = {
    filename:  document.querySelector('[data-filename]'),
    code:      document.querySelector('[data-code]'),
    typeline:  document.querySelector('[data-typeline]'),
    storyName:    document.querySelector('[data-story-name]'),
    chapter:      document.querySelector('[data-chapter]'),
    chapterGroup: document.querySelector('[data-chapter-group]'),
    stepNum:      document.querySelector('[data-step-num]'),
    title:     document.querySelector('[data-title]'),
    body:      document.querySelector('[data-body]'),
    problem:   document.querySelector('[data-problem]'),
    move:      document.querySelector('[data-move]'),
    closing:   document.querySelector('[data-closing]'),
    dots:      document.querySelector('[data-dots]'),
    counter:   document.querySelector('[data-counter]'),
    prev:      document.querySelector('[data-prev]'),
    next:      document.querySelector('[data-next]'),
  };

  let i = 0;
  const N = STORY.steps.length;

  // Escape HTML special chars
  function esc(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  // Plain chips only — no markdown. Used by typeline.
  function chip(text) {
    if (text == null) return '';
    return esc(text).replace(/`([^`]+)`/g, '<code class="chip">$1</code>');
  }

  // Full inline parse: backticks → chips, **bold**, *italic*, plus an HTML
  // allowlist for users who pass <em>/<strong> directly.
  // Chips are protected during markdown so asterisks inside chips stay literal.
  function rich(text) {
    if (text == null) return '';
    let s = esc(text);
    // Protect chips with sentinel placeholders
    const chips = [];
    s = s.replace(/`([^`]+)`/g, function (_, c) {
      chips.push(c);
      return '\\u0000CHIP' + (chips.length - 1) + '\\u0000';
    });
    // **bold** must run before *italic* to avoid greedy single-asterisk match
    s = s.replace(/\\*\\*([^*\\n]+?)\\*\\*/g, '<strong>$1</strong>');
    // *italic* — non-greedy, single line
    s = s.replace(/(^|[^*])\\*([^*\\n]+?)\\*(?!\\*)/g, '$1<em>$2</em>');
    // HTML allowlist (escaped form → real tags)
    s = s.replace(/&lt;em&gt;/g, '<em>').replace(/&lt;\\/em&gt;/g, '</em>');
    s = s.replace(/&lt;strong&gt;/g, '<strong>').replace(/&lt;\\/strong&gt;/g, '</strong>');
    // Restore chips
    s = s.replace(/\\u0000CHIP(\\d+)\\u0000/g, function (_, i) {
      return '<code class="chip">' + chips[+i] + '</code>';
    });
    return s;
  }

  function render(idx) {
    if (idx < 0 || idx >= N) return;
    const s = STORY.steps[idx];

    // Code side
    els.filename.textContent = s.filename || '';
    els.code.textContent = s.code || '';
    els.code.className = 'language-' + (s.lang || 'typescript');
    if (window.Prism) Prism.highlightElement(els.code);
    els.typeline.innerHTML = chip(s.typeline);

    // Narrative side
    els.storyName.textContent = STORY.title || '';
    els.chapter.textContent = s.chapter || '';
    if (els.chapterGroup) els.chapterGroup.style.display = s.chapter ? '' : 'none';
    els.stepNum.textContent = 'STEP ' + String(idx + 1).padStart(2, '0');
    els.title.innerHTML = rich(s.title);
    els.body.innerHTML = rich(s.body);
    els.problem.innerHTML = rich(s.problem);
    els.move.innerHTML = rich(s.move);
    els.closing.innerHTML = rich(s.closing);

    // Dots + counter
    els.dots.querySelectorAll('.dot').forEach((d, j) => {
      d.classList.toggle('active', j === idx);
    });
    els.counter.innerHTML = '<b>' + (idx + 1) + '</b>&nbsp;/&nbsp;' + N;

    // Prev/Next disabled state
    els.prev.disabled = (idx === 0);
    els.next.disabled = (idx === N - 1);

    // Reset code-body scroll
    const cb = document.querySelector('.code-body');
    if (cb) cb.scrollTop = 0;

    i = idx;
    // Update URL hash without scroll
    if (history.replaceState) history.replaceState(null, '', '#' + (idx + 1));
  }

  function go(delta) {
    const next = i + delta;
    if (next >= 0 && next < N) render(next);
  }

  // Build dots
  els.dots.innerHTML = STORY.steps
    .map((_, j) => '<button class="dot" type="button" data-i="' + j + '" aria-label="Go to step ' + (j + 1) + '"></button>')
    .join('');
  els.dots.querySelectorAll('.dot').forEach((d) => {
    d.addEventListener('click', () => render(Number(d.dataset.i)));
  });

  // Buttons
  els.prev.addEventListener('click', () => go(-1));
  els.next.addEventListener('click', () => go(1));

  // Keyboard
  document.addEventListener('keydown', (e) => {
    if (e.target.matches('input, textarea')) return;
    if (e.key === 'ArrowRight' || e.key === ' ') { e.preventDefault(); go(1); }
    else if (e.key === 'ArrowLeft') { e.preventDefault(); go(-1); }
    else if (e.key === 'Home') { e.preventDefault(); render(0); }
    else if (e.key === 'End') { e.preventDefault(); render(N - 1); }
  });

  // Initial render — respect #N hash
  let start = 0;
  const m = location.hash.match(/^#(\\d+)$/);
  if (m) {
    const n = Number(m[1]) - 1;
    if (n >= 0 && n < N) start = n;
  }
  render(start);
})();
"""

# ------------------------------------------------------------------
# 4. Concatenate Prism components in dependency order
# ------------------------------------------------------------------
PRISM_COMPONENTS = [
    "prism-core",
    # markup family — must precede languages that embed it
    "prism-markup", "prism-css", "prism-clike",
    # markup-templating — required by PHP; loaded before PHP to avoid
    # the famous `tokenizePlaceholders` runtime crash on highlightElement.
    "prism-markup-templating",
    # JS family
    "prism-javascript", "prism-typescript", "prism-jsx", "prism-tsx",
    # Python / PHP / Ruby / JVM / Go / .NET
    "prism-python", "prism-php", "prism-ruby", "prism-java",
    "prism-go", "prism-csharp",
    # Misc data / shell
    "prism-sql", "prism-bash", "prism-json", "prism-yaml", "prism-markdown",
    # PR mode
    "prism-diff",
]
PRISM_JS = "\n".join((ASSETS / f"{c}.min.js").read_text() for c in PRISM_COMPONENTS)

# ------------------------------------------------------------------
# 5. HTML structure
# ------------------------------------------------------------------
HTML = f"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{{{TITLE}}}}</title>
<style>
{FONT_FACE}
{CSS}
</style>
</head>
<body>

<div class="storyteller">
  <main class="stage">
    <section class="code-side">
      <div class="code-window">
        <div class="window-chrome">
          <span class="dots">
            <span class="dot r"></span><span class="dot y"></span><span class="dot g"></span>
          </span>
          <span class="filename" data-filename></span>
        </div>
        <div class="code-body">
          <pre><code data-code class="language-typescript"></code></pre>
        </div>
        <div class="type-bar">
          <span class="type-label">Current type</span>
          <span class="type-value" data-typeline></span>
        </div>
      </div>
    </section>

    <aside class="story-side">
      <nav class="breadcrumb">
        <span class="crumb-story" data-story-name></span>
        <span class="chapter-group" data-chapter-group>
          <span class="crumb-sep">/</span>
          <span class="crumb-chapter" data-chapter></span>
        </span>
        <span class="crumb-sep">/</span>
        <span class="crumb-step" data-step-num></span>
      </nav>
      <h1 class="headline" data-title></h1>
      <p class="body-para" data-body></p>
      <div class="card-pair">
        <article class="card card-problem">
          <p class="card-label">Problem</p>
          <p class="card-body" data-problem></p>
        </article>
        <article class="card card-move">
          <p class="card-label">Move</p>
          <p class="card-body" data-move></p>
        </article>
      </div>
      <p class="closing" data-closing></p>
    </aside>
  </main>

  <footer class="controls">
    <button class="nav-btn" type="button" data-prev>&larr; Prev</button>
    <div class="dots-wrap">
      <span class="dots" data-dots></span>
      <span class="counter" data-counter></span>
    </div>
    <span class="hint">← / → / space</span>
    <button class="nav-btn" type="button" data-next>Next &rarr;</button>
  </footer>
</div>

<script>
const STORY = {{{{STORY_JSON}}}};
</script>

<script>
{PRISM_JS}
</script>

<script>
{RENDER_JS}
</script>

</body>
</html>
"""

# ------------------------------------------------------------------
# 6. Write
# ------------------------------------------------------------------
out = SKILL / "template.html"
out.write_text(HTML)
print(f"Wrote {out} ({len(HTML):,} bytes)")

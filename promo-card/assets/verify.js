// Pass to browser_evaluate BEFORE screenshotting. Require exact/scrollsH/clipped
// to pass; treat a large bottomGap as a layout smell worth looking at.
//
// Set W and H to the stage size you promised.
() => {
  const W = 1600, H = 900;

  const stage = document.querySelector('.stage');
  if (!stage) return { error: 'no .stage element found' };

  // Any element whose content is taller/wider than its box, inside a clipping
  // ancestor. This is how a fixed-height pane silently eats the last lines.
  const clipped = [...document.querySelectorAll('.stage *')]
    .filter(el => {
      const oy = getComputedStyle(el).overflow;
      const cut = el.scrollHeight > el.clientHeight + 1 ||
                  el.scrollWidth  > el.clientWidth + 1;
      return cut && oy !== 'visible';
    })
    .map(el => `${el.tagName.toLowerCase()}.${el.className || '(no class)'}`);

  // Columns of the main grid should finish at roughly the same y.
  const cols = [...(document.querySelector('.grid')?.children ?? [])];
  const bottoms = cols.map(c => Math.round(c.getBoundingClientRect().bottom));
  const spread = bottoms.length > 1 ? Math.max(...bottoms) - Math.min(...bottoms) : 0;

  return {
    exact: stage.offsetWidth === W && stage.offsetHeight === H,
    measured: [stage.offsetWidth, stage.offsetHeight],
    expected: [W, H],
    scrollsH: document.documentElement.scrollWidth > innerWidth,
    scrollsV: document.documentElement.scrollHeight > innerHeight,
    clipped: clipped.length > 0,
    clippedElements: clipped,
    bottomAligned: spread <= 4,
    columnBottoms: bottoms,
    // Dead space under the tallest column. Over ~80px usually looks unbalanced.
    bottomGap: bottoms.length ? H - Math.max(...bottoms) : null,
    // Catches a webfont that failed to load and fell back silently.
    monoResolved: getComputedStyle(document.querySelector('pre') ?? stage).fontFamily,
  };
}

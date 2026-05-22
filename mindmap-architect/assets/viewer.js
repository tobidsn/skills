(function () {
  const DATA = window.MINDMAP_DATA;
  if (!DATA) {
    document.body.innerHTML = '<p style="padding:24px;font-family:sans-serif">No MINDMAP_DATA found.</p>';
    return;
  }
  if (!DATA.children && DATA.nodes) {
    DATA.children = DATA.nodes;
  }

  // ---- Theme ----
  let themeSetting = localStorage.getItem("mindmap-theme") || "light";

  function applyTheme() {
    const isSystem = themeSetting === "system";
    const systemIsDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
    const activeTheme = isSystem ? (systemIsDark ? "dark" : "light") : themeSetting;
    const meta = document.querySelector('meta[name="color-scheme"]');
    if (meta) meta.content = isSystem ? "light dark" : themeSetting;
    document.documentElement.setAttribute("data-theme", activeTheme);
    const themeBtn = document.getElementById("theme-btn");
    if (themeBtn) {
      themeBtn.title = activeTheme === "dark" ? "Switch to Light Mode" : "Switch to Dark Mode";
    }
  }

  function handleThemeToggle() {
    const isSystem = themeSetting === "system";
    const systemIsDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
    if (isSystem) {
      themeSetting = systemIsDark ? "light" : "dark";
    } else {
      themeSetting = "system";
    }
    localStorage.setItem("mindmap-theme", themeSetting);
    applyTheme();
  }

  // Apply theme to <html> before DOM build (avoid flash)
  (function preApplyTheme() {
    const isSystem = themeSetting === "system";
    const systemIsDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
    const activeTheme = isSystem ? (systemIsDark ? "dark" : "light") : themeSetting;
    document.documentElement.setAttribute("data-theme", activeTheme);
    if (!document.querySelector('meta[name="color-scheme"]')) {
      const m = document.createElement("meta");
      m.setAttribute("name", "color-scheme");
      m.setAttribute("content", isSystem ? "light dark" : themeSetting);
      document.head.appendChild(m);
    }
  })();

  window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", () => {
    if (themeSetting === "system") applyTheme();
  });

  // ---- DOM build ----
  function buildDom() {
    document.body.innerHTML = `
      <svg id="mindmap-svg">
        <g id="viewport">
          <g id="links-group"></g>
          <g id="nodes-group"></g>
        </g>
      </svg>

      <div id="info-panel" class="glass-panel">
        <a id="back-link" class="back-link" href="../index.html">
          <svg class="back-icon" viewBox="0 0 24 24">
            <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
          </svg>
          <span>Back to Library</span>
        </a>
        <div id="source-badge" class="badge badge-prompt">PROMPT</div>
        <h1 id="mindmap-title">Mindmap Title</h1>
        <p id="mindmap-summary">Summary content goes here.</p>
        <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 4px;">
          <div>Source: <span id="mindmap-source">N/A</span></div>
          <div id="mindmap-source-path-row" style="display:none;">From project: <span id="mindmap-source-path"></span></div>
          <div style="margin-top: 8px; border-top: 1px solid var(--border-color); padding-top: 8px;">
            Created by <a href="https://github.com/tobidsn/skills" target="_blank" style="color: var(--accent); text-decoration: none; font-weight: 600;">@tobidsn</a>
          </div>
        </div>
      </div>

      <div id="toolbar" class="glass-panel">
        <button class="btn" id="zoom-in-btn" title="Zoom In">
          <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
        </button>
        <button class="btn" id="zoom-out-btn" title="Zoom Out">
          <svg viewBox="0 0 24 24"><path d="M19 13H5v-2h14v2z"/></svg>
        </button>
        <button class="btn" id="zoom-reset-btn" title="Reset View">
          <svg viewBox="0 0 24 24"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm-7 7H3v4c0 1.1.9 2 2 2h4v-2H5v-4zM5 5h4V3H5c-1.1 0-2 .9-2 2v4h2V5zm14-2h-4v2h4v4h2V5c0-1.1-.9-2-2-2zm0 16h-4v2h4c1.1 0 2-.9 2-2v-4h-2v4z"/></svg>
        </button>
        <button class="btn" id="theme-btn" title="Toggle Theme">
          <svg viewBox="0 0 24 24"><path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9c0-.46-.04-.92-.1-1.36-.98 1.37-2.58 2.26-4.4 2.26-2.98 0-5.4-2.42-5.4-5.4 0-1.81.89-3.42 2.26-4.4-.44-.06-.9-.1-1.36-.1z"/></svg>
        </button>
        <button class="btn btn-text" id="export-png-btn" title="Export PNG">PNG</button>
        <button class="btn btn-text" id="export-md-btn" title="Export Markdown">Markdown</button>
      </div>

      <div id="detail-panel" class="glass-panel">
        <div class="panel-header">
          <h2>Node Detail</h2>
          <button class="close-btn" id="close-detail-btn">&times;</button>
        </div>
        <div class="detail-body">
          <div class="detail-section">
            <h3>Label</h3>
            <p class="detail-text" id="node-label-text">Select a node to inspect details.</p>
          </div>
          <div class="detail-section">
            <h3>Context</h3>
            <p class="detail-text" id="node-context-text" style="font-style: italic;">N/A</p>
          </div>
          <div class="detail-section">
            <h3>Description</h3>
            <p class="detail-text" id="node-description-text">Select a node to view its description.</p>
          </div>
          <div class="detail-section" style="margin-top: auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
              <h3>Continuation Prompt</h3>
              <button class="btn btn-text" id="copy-prompt-btn" style="height:28px; padding:0 8px; font-size:0.75rem;">Copy</button>
            </div>
            <div class="prompt-box" id="node-prompt-box">Select a node to generate a continuation prompt.</div>
          </div>
        </div>
      </div>
    `;
  }

  buildDom();

  // ---- Hook up references ----
  const svg = document.getElementById("mindmap-svg");
  const viewport = document.getElementById("viewport");
  const linksGroup = document.getElementById("links-group");
  const nodesGroup = document.getElementById("nodes-group");
  const detailPanel = document.getElementById("detail-panel");

  // Back link routing: detect nesting depth
  (function setBackLink() {
    const el = document.getElementById("back-link");
    if (!el) return;
    const path = window.location.pathname;
    const twoLevels = path.includes("/global/") || path.includes("/examples/");
    el.href = twoLevels ? "../../index.html" : "../index.html";
  })();

  // Header info
  document.getElementById("mindmap-title").textContent = DATA.title || "Untitled Mindmap";
  document.getElementById("mindmap-summary").textContent = DATA.summary || "";
  document.getElementById("mindmap-source").textContent = DATA.source || "N/A";

  if (DATA.source_path) {
    const row = document.getElementById("mindmap-source-path-row");
    const pathEl = document.getElementById("mindmap-source-path");
    if (row && pathEl) {
      row.style.display = "block";
      const shortName = DATA.source_project || DATA.source_path.split("/").pop() || DATA.source_path;
      pathEl.textContent = shortName;
      pathEl.title = DATA.source_path;
    }
  }

  const badge = document.getElementById("source-badge");
  badge.className = `badge badge-${DATA.source_type || "prompt"}`;
  badge.textContent = (DATA.source_type || "prompt").toUpperCase();

  // ---- Layout ----
  let activeNodeId = null;
  let transformState = { x: window.innerWidth / 2, y: window.innerHeight / 2, scale: 1.0 };
  let isDragging = false;
  const dragStart = { x: 0, y: 0 };
  const nodeWidth = 180;
  const nodeHeight = 36;
  const verticalGap = 24;
  const levelSeparation = 240;

  function initializeCollapse(node) {
    if (!Object.prototype.hasOwnProperty.call(node, "collapsed")) node.collapsed = false;
    if (node.children) node.children.forEach(initializeCollapse);
  }

  initializeCollapse(DATA);
  updateLayout();
  resetView();
  applyTheme();

  // ---- Panning / zooming ----
  svg.addEventListener("mousedown", (e) => {
    if (e.target.tagName === "svg" || e.target.id === "viewport" || e.target.tagName === "g" || e.target.tagName === "path") {
      isDragging = true;
      dragStart.x = e.clientX - transformState.x;
      dragStart.y = e.clientY - transformState.y;
    }
  });
  window.addEventListener("mousemove", (e) => {
    if (!isDragging) return;
    transformState.x = e.clientX - dragStart.x;
    transformState.y = e.clientY - dragStart.y;
    applyTransform();
  });
  window.addEventListener("mouseup", () => { isDragging = false; });

  svg.addEventListener("wheel", (e) => {
    e.preventDefault();
    const zoomFactor = 1.1;
    const mouseX = e.clientX - svg.getBoundingClientRect().left;
    const mouseY = e.clientY - svg.getBoundingClientRect().top;
    const prevScale = transformState.scale;
    transformState.scale = e.deltaY < 0
      ? Math.min(transformState.scale * zoomFactor, 3)
      : Math.max(transformState.scale / zoomFactor, 0.2);
    transformState.x = mouseX - (mouseX - transformState.x) * (transformState.scale / prevScale);
    transformState.y = mouseY - (mouseY - transformState.y) * (transformState.scale / prevScale);
    applyTransform();
  }, { passive: false });

  // ---- Toolbar ----
  document.getElementById("zoom-in-btn").addEventListener("click", () => {
    const prevScale = transformState.scale;
    transformState.scale = Math.min(transformState.scale * 1.2, 3);
    transformState.x = window.innerWidth / 2 - (window.innerWidth / 2 - transformState.x) * (transformState.scale / prevScale);
    transformState.y = window.innerHeight / 2 - (window.innerHeight / 2 - transformState.y) * (transformState.scale / prevScale);
    applyTransform();
  });
  document.getElementById("zoom-out-btn").addEventListener("click", () => {
    const prevScale = transformState.scale;
    transformState.scale = Math.max(transformState.scale / 1.2, 0.2);
    transformState.x = window.innerWidth / 2 - (window.innerWidth / 2 - transformState.x) * (transformState.scale / prevScale);
    transformState.y = window.innerHeight / 2 - (window.innerHeight / 2 - transformState.y) * (transformState.scale / prevScale);
    applyTransform();
  });
  document.getElementById("zoom-reset-btn").addEventListener("click", resetView);
  document.getElementById("theme-btn").addEventListener("click", handleThemeToggle);
  document.getElementById("close-detail-btn").addEventListener("click", () => {
    detailPanel.classList.remove("open");
    activeNodeId = null;
    updateLayout();
  });
  document.getElementById("copy-prompt-btn").addEventListener("click", () => {
    const promptText = document.getElementById("node-prompt-box").textContent;
    navigator.clipboard.writeText(promptText).then(() => {
      const btn = document.getElementById("copy-prompt-btn");
      btn.textContent = "Copied!";
      setTimeout(() => (btn.textContent = "Copy"), 1500);
    });
  });

  function applyTransform() {
    viewport.setAttribute("transform", `translate(${transformState.x}, ${transformState.y}) scale(${transformState.scale})`);
  }
  function resetView() {
    transformState = { x: window.innerWidth / 2, y: window.innerHeight / 2, scale: 1.0 };
    applyTransform();
  }

  // ---- Layout calc ----
  function updateLayout() {
    calculateHeight(DATA);
    DATA.x = 0;
    DATA.y = 0;
    if (DATA.children && DATA.children.length > 0) {
      const half = Math.ceil(DATA.children.length / 2);
      const rightChildren = DATA.children.slice(0, half);
      const leftChildren = DATA.children.slice(half);
      layoutBranchSide(rightChildren, 1);
      layoutBranchSide(leftChildren, -1);
    }
    render();
  }

  function calculateHeight(node) {
    if (node.collapsed || !node.children || node.children.length === 0) {
      node.subtreeHeight = nodeHeight;
      return nodeHeight;
    }
    let total = 0;
    node.children.forEach((c) => { total += calculateHeight(c); });
    total += (node.children.length - 1) * verticalGap;
    node.subtreeHeight = Math.max(nodeHeight, total);
    return node.subtreeHeight;
  }

  function layoutBranchSide(children, direction) {
    if (children.length === 0) return;
    let totalSideHeight = 0;
    children.forEach((c) => { totalSideHeight += c.subtreeHeight; });
    totalSideHeight += (children.length - 1) * verticalGap;
    let startY = -totalSideHeight / 2;
    children.forEach((c) => {
      c.x = direction * levelSeparation;
      c.y = startY + c.subtreeHeight / 2;
      startY += c.subtreeHeight + verticalGap;
      layoutSubnodes(c, direction);
    });
  }

  function layoutSubnodes(parent, direction) {
    if (parent.collapsed || !parent.children || parent.children.length === 0) return;
    let total = 0;
    parent.children.forEach((c) => { total += c.subtreeHeight; });
    total += (parent.children.length - 1) * verticalGap;
    let startY = parent.y - total / 2;
    parent.children.forEach((c) => {
      c.x = parent.x + direction * levelSeparation;
      c.y = startY + c.subtreeHeight / 2;
      startY += c.subtreeHeight + verticalGap;
      layoutSubnodes(c, direction);
    });
  }

  // ---- Render ----
  function render() {
    linksGroup.innerHTML = "";
    nodesGroup.innerHTML = "";
    drawNode(DATA);
  }

  function drawNode(node, parentNode = null) {
    if (parentNode) {
      const link = document.createElementNS("http://www.w3.org/2000/svg", "path");
      link.setAttribute("class", "link-path");
      const startX = parentNode.x + (parentNode.x === 0 ? 0 : (parentNode.x > 0 ? nodeWidth / 2 : -nodeWidth / 2));
      const startY = parentNode.y;
      const endX = node.x - (node.x > 0 ? nodeWidth / 2 : -nodeWidth / 2);
      const endY = node.y;
      const controlX = (startX + endX) / 2;
      link.setAttribute("d", `M ${startX} ${startY} C ${controlX} ${startY}, ${controlX} ${endY}, ${endX} ${endY}`);
      linksGroup.appendChild(link);
    }

    const nodeG = document.createElementNS("http://www.w3.org/2000/svg", "g");
    nodeG.setAttribute("class", `node-group${node.x === 0 ? " root" : ""}${node.id === activeNodeId ? " active" : ""}`);
    nodeG.setAttribute("transform", `translate(${node.x}, ${node.y})`);

    const bg = document.createElementNS("http://www.w3.org/2000/svg", "rect");
    bg.setAttribute("class", "node-bg");
    bg.setAttribute("x", -nodeWidth / 2);
    bg.setAttribute("y", -nodeHeight / 2);
    bg.setAttribute("width", nodeWidth);
    bg.setAttribute("height", nodeHeight);
    nodeG.appendChild(bg);

    const text = document.createElementNS("http://www.w3.org/2000/svg", "text");
    text.setAttribute("class", "node-text");
    text.setAttribute("text-anchor", "middle");
    text.setAttribute("dominant-baseline", "central");
    let label = node.label || node.title || "";
    if (label.length > 22) label = label.substring(0, 20) + "...";
    text.textContent = label;
    nodeG.appendChild(text);

    nodeG.addEventListener("click", (e) => {
      e.stopPropagation();
      if (e.target.classList.contains("toggle-circle")) return;
      activeNodeId = node.id || "root";
      selectNode(node, parentNode);
      updateLayout();
    });

    nodesGroup.appendChild(nodeG);

    if (node.children && node.children.length > 0) {
      const toggleG = document.createElementNS("http://www.w3.org/2000/svg", "g");
      toggleG.setAttribute("class", "toggle-group");
      const direction = node.x === 0 ? 1 : (node.x > 0 ? 1 : -1);
      const toggleX = node.x + direction * (nodeWidth / 2);
      toggleG.setAttribute("transform", `translate(${toggleX}, ${node.y})`);

      const circle = document.createElementNS("http://www.w3.org/2000/svg", "circle");
      circle.setAttribute("class", "toggle-circle");
      circle.setAttribute("r", 7);
      toggleG.appendChild(circle);

      const toggleText = document.createElementNS("http://www.w3.org/2000/svg", "text");
      toggleText.setAttribute("class", "toggle-text");
      toggleText.textContent = node.collapsed ? "+" : "-";
      toggleG.appendChild(toggleText);

      toggleG.addEventListener("click", (e) => {
        e.stopPropagation();
        node.collapsed = !node.collapsed;
        updateLayout();
      });
      nodesGroup.appendChild(toggleG);
    }

    if (!node.collapsed && node.children) {
      node.children.forEach((child) => drawNode(child, node));
    }
  }

  function selectNode(node, parentNode) {
    document.getElementById("node-label-text").textContent = node.label || node.title;
    const parentLabel = parentNode ? (parentNode.label || parentNode.title) : "None (Root)";
    document.getElementById("node-context-text").textContent = `Parent: ${parentLabel}`;
    document.getElementById("node-description-text").textContent =
      node.description || `Detailed explanation of "${node.label || node.title}" within the context of "${parentLabel}".`;
    const prompt = `Continue from this mindmap node:
Title: ${node.label || node.title}
Context: Parent Node is "${parentLabel}" in the mindmap "${DATA.title}"
Task: Expand this node into deeper explanation, action items, and implementation plan.`;
    document.getElementById("node-prompt-box").textContent = prompt;
    detailPanel.classList.add("open");
  }

  // ---- Exports ----
  function getSlug() {
    if (DATA.slug) return DATA.slug;
    return (DATA.title || "mindmap")
      .toLowerCase()
      .replace(/\s+/g, "-")
      .replace(/[^a-z0-9\-]/g, "")
      .replace(/\-+/g, "-")
      .replace(/^-+|-+$/g, "");
  }

  document.getElementById("export-md-btn").addEventListener("click", () => {
    let md = `# ${DATA.title}\n\n`;
    if (DATA.summary) md += `*Summary: ${DATA.summary}*\n\n`;
    function buildMd(node, indent = "") {
      if (node !== DATA) md += `${indent}- ${node.label || node.title}\n`;
      if (node.children) {
        node.children.forEach((child) => {
          buildMd(child, node === DATA ? indent : indent + "  ");
        });
      }
    }
    buildMd(DATA);
    const blob = new Blob([md], { type: "text/markdown" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `${getSlug()}.md`;
    a.click();
  });

  document.getElementById("export-png-btn").addEventListener("click", () => {
    let minX = 0, maxX = 0, minY = 0, maxY = 0;
    (function findLimits(node) {
      if (node.x - nodeWidth / 2 < minX) minX = node.x - nodeWidth / 2;
      if (node.x + nodeWidth / 2 > maxX) maxX = node.x + nodeWidth / 2;
      if (node.y - nodeHeight / 2 < minY) minY = node.y - nodeHeight / 2;
      if (node.y + nodeHeight / 2 > maxY) maxY = node.y + nodeHeight / 2;
      if (!node.collapsed && node.children) node.children.forEach(findLimits);
    })(DATA);

    const padding = 60;
    const width = maxX - minX + padding * 2;
    const height = maxY - minY + padding * 2;
    const activeTheme = document.documentElement.getAttribute("data-theme") || "light";
    const isDark = activeTheme === "dark";

    const exportSvg = svg.cloneNode(true);
    exportSvg.setAttribute("width", width);
    exportSvg.setAttribute("height", height);
    exportSvg.setAttribute("viewBox", `${minX - padding} ${minY - padding} ${width} ${height}`);

    const styleElement = document.createElementNS("http://www.w3.org/2000/svg", "style");
    styleElement.textContent = `
      .node-bg { fill: ${isDark ? "#1f1f1e" : "#e8e6dc"}; stroke: ${isDark ? "rgba(176,174,165,0.15)" : "rgba(20,20,19,0.1)"}; stroke-width: 1.5px; rx: 8px; ry: 8px; }
      .node-group.root .node-bg { fill: #d97757; }
      .node-text { font-family: 'Poppins', sans-serif; font-size: 13px; fill: ${isDark ? "#faf9f5" : "#141413"}; font-weight: 500; text-anchor: middle; dominant-baseline: central; }
      .node-group.root .node-text { fill: #faf9f5; font-weight: 600; font-size: 14px; }
      .link-path { fill: none; stroke: #6a9bcc; stroke-width: 2px; }
      .toggle-circle { fill: ${isDark ? "#1f1f1e" : "#e8e6dc"}; stroke: #d97757; stroke-width: 1.5px; }
      .toggle-text { font-family: 'Poppins', sans-serif; font-size: 10px; fill: #d97757; text-anchor: middle; dominant-baseline: central; }
    `;
    exportSvg.insertBefore(styleElement, exportSvg.firstChild);
    const exportViewport = exportSvg.querySelector("#viewport");
    exportViewport.removeAttribute("transform");

    const serializer = new XMLSerializer();
    const svgString = serializer.serializeToString(exportSvg);
    const svgBlob = new Blob([svgString], { type: "image/svg+xml;charset=utf-8" });
    const url = URL.createObjectURL(svgBlob);

    const img = new Image();
    img.onload = function () {
      const canvas = document.createElement("canvas");
      canvas.width = width;
      canvas.height = height;
      const ctx = canvas.getContext("2d");
      ctx.fillStyle = isDark ? "#141413" : "#faf9f5";
      ctx.fillRect(0, 0, width, height);
      ctx.drawImage(img, 0, 0);
      URL.revokeObjectURL(url);
      const pngUrl = canvas.toDataURL("image/png");
      const a = document.createElement("a");
      a.href = pngUrl;
      a.download = `${getSlug()}.png`;
      a.click();
    };
    img.src = url;
  });
})();

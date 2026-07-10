<?php
declare(strict_types=1);

function inkwall_template_replace_once(string $source, string $search, string $replace, string $label): string {
    $search = str_replace('\\n', "\n", $search);
    $replace = str_replace('\\n', "\n", $replace);
    $offset = strpos($source, $search);
    if ($offset === false) throw new RuntimeException('InkWall template override target missing: ' . $label);
    return substr_replace($source, $replace, $offset, strlen($search));
}

function inkwall_apply_template_overrides(string $source): string {
    $source = inkwall_template_replace_once($source,
        '    .composer { position: sticky; top: 82px; display: grid; gap: 25px; min-width: 0; }',
        '    .composer { position: sticky; top: 82px; display: grid; gap: 18px; min-width: 0; padding: 20px; border: 1px solid color-mix(in srgb, var(--line) 82%, transparent); border-radius: 16px; background: color-mix(in srgb, var(--paper) 36%, transparent); box-shadow: 0 18px 54px rgba(35, 39, 34, .07); }', 'composer surface');
    $source = inkwall_template_replace_once($source,
        '    .field { display: grid; gap: 11px; }',
        '    .field { display: grid; gap: 9px; padding: 12px 14px; border: 1px solid var(--line); border-radius: 9px; background: color-mix(in srgb, var(--paper) 58%, transparent); transition: border-color .18s ease, background .18s ease, box-shadow .18s ease; }\n    .field:focus-within { border-color: var(--line-strong); background: color-mix(in srgb, var(--paper) 82%, transparent); box-shadow: 0 0 0 3px var(--focus); }\n    .field input, .field textarea, .field input:focus, .field textarea:focus { border-bottom: 0; box-shadow: none; }', 'field surfaces');
    $source = inkwall_template_replace_once($source,
        '    .image-field { position: relative; display: grid; gap: 10px; }',
        <<<'CSS'
    .layout-field { display: grid; gap: 10px; padding: 14px; border: 1px solid var(--line); border-radius: 9px; background: color-mix(in srgb, var(--paper) 48%, transparent); }
    .layout-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .layout-option { display: grid; gap: 5px; color: var(--muted); font-family: var(--mono); font-size: 8px; font-weight: 720; letter-spacing: .05em; text-transform: uppercase; }
    .layout-option select { width: 100%; min-height: 38px; padding: 0 30px 0 10px; border: 1px solid var(--line); border-radius: 5px; color: var(--ink); background: var(--paper); font-family: var(--ui); font-size: 12px; font-weight: 650; text-transform: none; }
    .image-field { position: relative; display: grid; gap: 10px; padding: 14px; border: 1px solid var(--line); border-radius: 9px; background: color-mix(in srgb, var(--paper) 48%, transparent); }
CSS, 'layout CSS');

    $source = inkwall_template_replace_once($source,
        '    .display-content.has-image .display-message { font-size: clamp(29px, 3.25vw, 45px); }',
        <<<'CSS'
    .display-content.has-image .display-message { font-size: clamp(29px, 3.25vw, 45px); }
    .display-content[data-density="compact"] .display-message { font-size: clamp(30px, 3vw, 44px); line-height: 1.08; letter-spacing: -.038em; }
    .display-content.has-image[data-density="compact"] .display-message { font-size: clamp(25px, 2.55vw, 37px); }
    .display-content[data-layout-align="center"] .display-message { text-align: center; }
    .display-content[data-layout-align="right"] .display-message { text-align: right; }
    .display-content[data-layout-align="center"] .display-author { justify-content: center; }
    .display-content[data-layout-align="right"] .display-author { justify-content: flex-end; }
    .display-content.has-image[data-layout-media="left"] .display-body,
    .display-content.has-image[data-layout-media="right"] .display-body { grid-template-rows: auto auto; align-items: center; column-gap: 4%; }
    .display-content.has-image[data-layout-media="left"] .display-body { grid-template-columns: 28% 68%; grid-template-areas: "media message" "media author"; }
    .display-content.has-image[data-layout-media="right"] .display-body { grid-template-columns: 68% 28%; grid-template-areas: "message media" "author media"; }
    .display-content.has-image[data-layout-media="left"] .display-media,
    .display-content.has-image[data-layout-media="right"] .display-media { grid-area: media; height: 100%; min-height: 168px; }
    .display-content.has-image[data-layout-media="left"] .display-message,
    .display-content.has-image[data-layout-media="right"] .display-message { grid-area: message; }
    .display-content.has-image[data-layout-media="left"] .display-author,
    .display-content.has-image[data-layout-media="right"] .display-author { grid-area: author; }
    .display { min-height: 0; aspect-ratio: 1200 / 340; container-type: inline-size; }
    .display-content, .display-ghost { padding: 2cqw 5.15cqw; }
    .display-meta, .display-foot { gap: 1.2cqw; font-size: 1.05cqw; }
    .display-meta { padding-bottom: 1.2cqw; }
    .display-foot { padding-top: 1.2cqw; }
    .display-body { gap: 1.5cqw; padding: 1.4cqw 0; }
    .display-message,
    .display-content[data-density="compact"] .display-message { font-family: var(--mono); font-size: 2.67cqw; font-weight: 700; line-height: 1.15; letter-spacing: 0; }
    .display-content.has-image .display-message,
    .display-content.has-image[data-density="compact"] .display-message { font-size: 2.3cqw; }
    .display-author { gap: .9cqw; margin-top: .35cqw; font-size: 1.25cqw; }
    .display-author::before { width: 2.5cqw; }
    .display-media { height: 7.17cqw; }
    .display-content.has-image[data-layout-media="left"] .display-media,
    .display-content.has-image[data-layout-media="right"] .display-media { height: 14.33cqw; min-height: 0; }
CSS, 'display layout CSS');

    $source = inkwall_template_replace_once($source,
        '      .display-content.has-image .display-message { font-size: clamp(22px, 7vw, 31px); }',
        <<<'CSS'
      .display-content.has-image .display-message { font-size: clamp(22px, 7vw, 31px); }
      .layout-options { grid-template-columns: 1fr; }
      .composer { gap: 15px; padding: 14px; border-radius: 13px; }
      .field, .layout-field, .image-field { padding: 12px; }
      .device { width: 100%; overflow: hidden; }
      .display { min-height: 0; aspect-ratio: 1200 / 340; border-radius: 6px; }
      .display-content, .display-ghost { padding: 2cqw 5.15cqw; }
      .display-message, .display-content[data-density="compact"] .display-message { font-size: 2.67cqw; line-height: 1.15; }
      .display-content.has-image .display-message, .display-content.has-image[data-density="compact"] .display-message { font-size: 2.3cqw; }
      .display-content.has-image .display-media { height: 7.17cqw; min-height: 0; }
      .display-content.has-image[data-layout-media="left"] .display-media,
      .display-content.has-image[data-layout-media="right"] .display-media { height: 14.33cqw; }
      .display-meta span, .display-foot span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
CSS, 'mobile layout CSS');

    $source = inkwall_template_replace_once($source,
        '        <div class="image-field" id="imageField">',
        <<<'HTML'
        <div class="layout-field">
          <div class="field-head"><span class="field-label">Layout</span><span class="counter">Preserved in SVG</span></div>
          <div class="layout-options">
            <label class="layout-option">Text alignment
              <select id="layoutAlignSelect"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select>
            </label>
            <label class="layout-option">Image position
              <select id="layoutMediaSelect"><option value="auto">Automatic</option><option value="top">Above text</option><option value="left">Left of text</option><option value="right">Right of text</option></select>
            </label>
          </div>
        </div>

        <div class="image-field" id="imageField">
HTML, 'layout controls');

    $source = inkwall_template_replace_once($source,
        '        showFavicons: true,\n        reportable: false,',
        '        showFavicons: true,\n        layout: Object.freeze({ align: "left", media: "auto" }),\n        reportable: false,', 'prepared layout');
    $source = inkwall_template_replace_once($source,
        '      faviconToggleText: document.getElementById("faviconToggleText"),',
        '      faviconToggleText: document.getElementById("faviconToggleText"),\n      layoutAlignSelect: document.getElementById("layoutAlignSelect"),\n      layoutMediaSelect: document.getElementById("layoutMediaSelect"),', 'layout DOM');
    $source = inkwall_template_replace_once($source,
        '          showFavicons: record?.showFavicons !== false,\n          reportable:',
        '          showFavicons: record?.showFavicons !== false,\n          layout: {\n            align: ["left", "center", "right"].includes(record?.layout?.align) ? record.layout.align : "left",\n            media: ["auto", "top", "left", "right"].includes(record?.layout?.media) ? record.layout.media : "auto"\n          },\n          reportable:', 'normalize layout');
    $source = inkwall_template_replace_once($source,
        '        return this.includePreparedMessage(Array.isArray(records) ? records : []);',
        '        return (Array.isArray(records) ? records : []).map(item => this.normalize(item)).sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));', 'remove live prepared ink');
    $source = inkwall_template_replace_once($source,
        '        if (!normalized.some(item => item.id === AppConfig.preparedInk.id)) normalized.push(this.preparedMessage());\n        return normalized.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));',
        '        return normalized.filter(item => item.id !== AppConfig.preparedInk.id).sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));', 'remove prepared fallback ink');
    $source = inkwall_template_replace_once($source,
        '        Dom.displayContent.classList.toggle("has-image", Boolean(payload.image));',
        '        const layout = payload.layout || {};\n        const visualLines = String(payload.message || "").split(String.fromCharCode(10)).length;\n        const visualLength = Array.from(String(payload.message || "")).length;\n        Dom.displayContent.dataset.density = visualLength > 52 || visualLines > 1 || payload.image ? "compact" : "normal";\n        Dom.displayContent.dataset.layoutAlign = ["left", "center", "right"].includes(layout.align) ? layout.align : "left";\n        Dom.displayContent.dataset.layoutMedia = ["auto", "top", "left", "right"].includes(layout.media) ? layout.media : "auto";\n        Dom.displayContent.classList.toggle("has-image", Boolean(payload.image));', 'display layout');
    $source = inkwall_template_replace_once($source,
        '        this.processing = false;\n        this.bindEvents();',
        '        this.processing = false;\n        this.frame = { key: "top", ratio: 1076 / 86, width: 1076, height: 86, label: "wide top" };\n        this.bindEvents();\n        this.setLayout(Dom.layoutMediaSelect.value, false);', 'image frame constructor');
    $source = inkwall_template_replace_once($source,
        '      renderPreview() {',
        '      setLayout(media, scheduleBuild = true) {\n        const top = media === "auto" || media === "top";\n        this.frame = top\n          ? { key: "top", ratio: 1076 / 86, width: 1076, height: 86, label: "wide top" }\n          : { key: media, ratio: 300 / 172, width: 800, height: Math.round(800 * 172 / 300), label: `${media} 16:9` };\n        Dom.cropCanvas.width = this.frame.width;\n        Dom.cropCanvas.height = this.frame.height;\n        Dom.cropStage.style.aspectRatio = `${this.frame.width} / ${this.frame.height}`;\n        if (!this.source) return;\n        Dom.imageEditorState.textContent = `Adjusting ${this.frame.label} frame`;\n        this.renderPreview();\n        if (scheduleBuild) this.schedule(40);\n      }\n\n      renderPreview() {', 'image layout method');
    $source = inkwall_template_replace_once($source,
        '          canvas.height = Math.round(width * 9 / 16);',
        '          canvas.height = Math.max(1, Math.round(width / this.frame.ratio));', 'layout output ratio');
    $source = inkwall_template_replace_once($source,
        '          signature: `${this.source.signature}:${this.crop.x.toFixed(2)}:${this.crop.y.toFixed(2)}:${this.crop.zoom.toFixed(2)}:${this.crop.invert ? 1 : 0}:${result.blob.size}`',
        '          signature: `${this.source.signature}:${this.frame.key}:${this.crop.x.toFixed(2)}:${this.crop.y.toFixed(2)}:${this.crop.zoom.toFixed(2)}:${this.crop.invert ? 1 : 0}:${result.blob.size}`', 'layout image signature');
    $source = inkwall_template_replace_once($source,
        '        Dom.imageMeta.textContent = `${cleanName} · ${Math.max(1, Math.round(result.blob.size / 1024))} KB · 16:9`;',
        '        Dom.imageMeta.textContent = `${cleanName} · ${Math.max(1, Math.round(result.blob.size / 1024))} KB · ${this.frame.label}`;', 'layout image meta');
    $source = inkwall_template_replace_once($source,
        '          Dom.imageMeta.textContent = `${file.name} · choose the frame`;\n          this.renderPreview();',
        '          Dom.imageMeta.textContent = `${file.name} · choose the frame`;\n          this.setLayout(Dom.layoutMediaSelect.value, false);\n          this.renderPreview();', 'select layout frame');
    $source = inkwall_template_replace_once($source,
        '        Dom.faviconToggle.addEventListener("click", () => {',
        '        Dom.layoutAlignSelect.addEventListener("change", () => this.updateState());\n        Dom.layoutMediaSelect.addEventListener("change", () => { this.imageWorkbench.setLayout(Dom.layoutMediaSelect.value); this.updateState(); });\n        Dom.faviconToggle.addEventListener("click", () => {', 'layout events');
    $source = inkwall_template_replace_once($source,
        '        const signature = [\n          nameResult.clean,',
        '        const layout = { align: Dom.layoutAlignSelect.value, media: Dom.layoutMediaSelect.value };\n        const signature = [\n          nameResult.clean,', 'draft layout');
    $source = inkwall_template_replace_once($source,
        '          this.showFavicons ? "favicons:1" : "favicons:0"',
        '          this.showFavicons ? "favicons:1" : "favicons:0",\n          `layout:${layout.align}:${layout.media}`', 'layout signature');
    $source = inkwall_template_replace_once($source,
        '        return { nameResult, messageResult, bindings, signature, image: this.imageWorkbench.output };',
        '        return { nameResult, messageResult, bindings, signature, image: this.imageWorkbench.output, layout };', 'draft return layout');
    $source = inkwall_template_replace_once($source,
        '          showFavicons: this.showFavicons,\n          date: new Date()',
        '          showFavicons: this.showFavicons,\n          layout: draft.layout,\n          date: new Date()', 'preview layout');
    $source = inkwall_template_replace_once($source,
        '            showFavicons: this.showFavicons,\n            createdAt:',
        '            showFavicons: this.showFavicons,\n            layout: draft.layout,\n            createdAt:', 'publish layout');
    return $source;
}

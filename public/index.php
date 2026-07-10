<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
inkwall_begin_public_request('view');
?><!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <meta name="description" content="Publish a moderated E-Ink note to the IamAngusU GitHub profile surface.">
  <title>GitHub E-Ink Message Surface</title>
  <style>
    :root {
      --page: #f3f2ec;
      --page-deep: #ebeae3;
      --ink: #272a26;
      --ink-soft: #5b6058;
      --muted: #858b82;
      --line: #c9ccc2;
      --line-strong: #a7aca2;
      --paper: #ebeae1;
      --paper-deep: #dad9d0;
      --device: #d7d6ce;
      --device-edge: #a7aaa1;
      --screen-ink: #292d28;
      --screen-muted: #666b63;
      --screen-line: rgba(42, 47, 41, .28);
      --cta: #272b26;
      --cta-ink: #f5f4ed;
      --positive: #526d5b;
      --warning: #8d704d;
      --danger: #9b5a5a;
      --focus: rgba(39, 43, 38, .18);
      --shadow: 0 28px 76px rgba(35, 39, 34, .13);
      --ui: "IBM Plex Sans", "Inter", "Aptos", "Helvetica Neue", Arial, sans-serif;
      --reader: "Literata", "Source Serif 4", "Charter", "Iowan Old Style", Georgia, serif;
      --mono: "IBM Plex Mono", "Fira Code", "SFMono-Regular", Consolas, monospace;
      --max: 1320px;
      --ease: cubic-bezier(.22, 1, .36, 1);
    }

    html[data-theme="dark"] {
      --page: #171a17;
      --page-deep: #101310;
      --ink: #dedfd7;
      --ink-soft: #b7bbb2;
      --muted: #92978f;
      --line: #3d423d;
      --line-strong: #646a62;
      --paper: #202420;
      --paper-deep: #171b17;
      --device: #292d29;
      --device-edge: #50554f;
      --screen-ink: #dadcd2;
      --screen-muted: #a8ada4;
      --screen-line: rgba(220, 223, 213, .25);
      --cta: #dedfd7;
      --cta-ink: #202420;
      --positive: #93b39d;
      --warning: #c6a97d;
      --danger: #d09292;
      --focus: rgba(222, 223, 215, .17);
      --shadow: 0 30px 84px rgba(0, 0, 0, .34);
    }

    * { box-sizing: border-box; }
    html { min-width: 320px; min-height: 100%; background: var(--page); scroll-behavior: smooth; }
    body {
      min-height: 100vh;
      margin: 0;
      color: var(--ink);
      background:
        radial-gradient(circle at 50% -12%, rgba(255, 255, 255, .56), transparent 40%),
        linear-gradient(180deg, var(--page), var(--page-deep));
      font-family: var(--ui);
      -webkit-font-smoothing: antialiased;
      text-rendering: geometricPrecision;
      transition: color .5s var(--ease), background .5s var(--ease);
    }
    html[data-theme="dark"] body {
      background:
        radial-gradient(circle at 50% -12%, rgba(116, 127, 114, .12), transparent 38%),
        linear-gradient(180deg, var(--page), var(--page-deep));
    }
    button, input, textarea { font: inherit; color: inherit; }
    button { border: 0; }
    a { color: inherit; }
    [hidden] { display: none !important; }
    :focus-visible { outline: 3px solid var(--focus); outline-offset: 3px; }

    .page {
      width: min(100%, calc(var(--max) + 64px));
      margin: 0 auto;
      padding: clamp(46px, 6vw, 82px) 32px 88px;
    }

    .page-back {
      position: fixed;
      top: 18px;
      left: 18px;
      z-index: 100;
      display: inline-flex;
      align-items: center;
      gap: 9px;
      min-height: 42px;
      padding: 0 13px 0 11px;
      border: 1px solid var(--line);
      border-radius: 999px;
      color: var(--ink-soft);
      background: color-mix(in srgb, var(--page) 92%, transparent);
      box-shadow: 0 10px 28px rgba(30, 33, 29, .07);
      backdrop-filter: blur(16px);
      cursor: pointer;
      font-family: var(--mono);
      font-size: 9px;
      font-weight: 720;
      letter-spacing: .07em;
      text-transform: uppercase;
      transition: color .35s var(--ease), background .35s var(--ease), border-color .35s var(--ease), transform .2s var(--ease);
    }
    .page-back:hover { border-color: var(--line-strong); color: var(--ink); transform: translateY(-1px); }
    .page-back svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

    .theme-toggle {
      position: fixed;
      top: 18px;
      right: 18px;
      z-index: 100;
      display: inline-flex;
      align-items: center;
      gap: 9px;
      min-height: 42px;
      padding: 0 11px 0 12px;
      border: 1px solid var(--line);
      border-radius: 999px;
      color: var(--ink-soft);
      background: color-mix(in srgb, var(--page) 92%, transparent);
      box-shadow: 0 10px 28px rgba(30, 33, 29, .07);
      backdrop-filter: blur(16px);
      cursor: pointer;
      font-family: var(--mono);
      font-size: 9px;
      font-weight: 720;
      letter-spacing: .07em;
      text-transform: uppercase;
      transition: color .35s var(--ease), background .35s var(--ease), border-color .35s var(--ease), transform .2s var(--ease);
    }
    .theme-toggle:hover:not(:disabled) { border-color: var(--line-strong); color: var(--ink); transform: translateY(-1px); }
    .theme-toggle:disabled { cursor: wait; opacity: .64; }
    .theme-toggle__track {
      position: relative;
      width: 40px;
      height: 23px;
      overflow: hidden;
      border: 1px solid var(--line-strong);
      border-radius: 999px;
      background: var(--paper);
      box-shadow: inset 0 1px 4px rgba(30, 33, 29, .08);
      transition: background .35s var(--ease), border-color .35s var(--ease);
    }
    .theme-toggle__track::before {
      position: absolute;
      top: 3px;
      left: 3px;
      width: 15px;
      height: 15px;
      border-radius: 50%;
      content: "";
      background: var(--screen-ink);
      box-shadow: 0 1px 5px rgba(25, 29, 24, .2);
      transition: transform .45s var(--ease), background .35s var(--ease);
    }
    .theme-toggle__track::after {
      position: absolute;
      inset: 0;
      content: "";
      opacity: .15;
      background: repeating-linear-gradient(0deg, transparent 0 2px, var(--screen-ink) 2px 3px, transparent 3px 5px);
    }
    html[data-theme="dark"] .theme-toggle__track::before { transform: translateX(17px); }

    .hero { max-width: 980px; margin-bottom: clamp(42px, 5vw, 68px); }
    .eyebrow, .step-label, .field-label, .counter, .status, .display-label, .display-meta, .display-foot,
    .publish-state, .destination-kicker, .recent-kicker, .recent-count, .recent-index, .recent-meta,
    .button, .entity-summary, .policy-kicker, .image-meta, .image-progress__head { font-family: var(--mono); }
    .eyebrow {
      display: block;
      margin-bottom: 15px;
      color: var(--muted);
      font-size: 9px;
      font-weight: 760;
      letter-spacing: .14em;
      text-transform: uppercase;
    }
    h1 {
      max-width: 920px;
      margin: 0;
      color: var(--ink);
      font-size: clamp(55px, 7vw, 96px);
      font-weight: 570;
      letter-spacing: -.072em;
      line-height: .92;
    }
    .hero__route {
      display: flex;
      align-items: center;
      gap: 11px;
      margin-top: 22px;
      color: var(--ink-soft);
      font-size: 14px;
      font-weight: 610;
      letter-spacing: -.015em;
    }
    .hero__route code {
      padding: 5px 8px;
      border: 1px solid var(--line);
      border-radius: 4px;
      color: var(--ink);
      background: color-mix(in srgb, var(--paper) 72%, transparent);
      font-family: var(--mono);
      font-size: 10px;
      font-weight: 700;
      letter-spacing: .01em;
    }

    .workflow {
      display: grid;
      grid-template-columns: minmax(300px, .75fr) minmax(520px, 1.25fr);
      gap: clamp(54px, 7vw, 104px);
      align-items: start;
    }
    .composer { position: sticky; top: 82px; display: grid; gap: 25px; min-width: 0; }
    .step-label {
      display: flex;
      align-items: center;
      gap: 10px;
      color: var(--muted);
      font-size: 9px;
      font-weight: 760;
      letter-spacing: .1em;
      text-transform: uppercase;
    }
    .step-label::after { height: 1px; flex: 1; content: ""; background: var(--line); }
    .field { display: grid; gap: 11px; }
    .field-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .field-label, .counter {
      color: var(--muted);
      font-size: 9px;
      font-weight: 700;
      letter-spacing: .09em;
      text-transform: uppercase;
    }
    .counter { letter-spacing: .02em; }
    input, textarea {
      width: 100%;
      border: 0;
      border-bottom: 1px solid var(--line);
      border-radius: 0;
      outline: 0;
      background: transparent;
      transition: border-color .18s ease, box-shadow .18s ease;
    }
    input { height: 48px; padding: 0 0 9px; font-size: 17px; font-weight: 630; letter-spacing: -.022em; }
    textarea { min-height: 132px; padding: 1px 0 15px; resize: vertical; font-size: 17px; line-height: 1.55; letter-spacing: -.016em; }
    input::placeholder, textarea::placeholder { color: color-mix(in srgb, var(--muted) 68%, transparent); }
    input:focus, textarea:focus { border-color: var(--line-strong); box-shadow: 0 1px 0 var(--line-strong); }
    .honeypot, .file-input { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }

    .entity-area { display: grid; gap: 8px; margin-top: -2px; }
    .entity-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; }
    .entity-summary { color: var(--muted); font-size: 8px; font-weight: 680; letter-spacing: .025em; }
    .favicon-toggle {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 0;
      color: var(--ink-soft);
      background: transparent;
      cursor: pointer;
      font-family: var(--mono);
      font-size: 8px;
      font-weight: 720;
      letter-spacing: .04em;
      text-transform: uppercase;
    }
    .favicon-toggle__switch {
      position: relative;
      display: inline-flex;
      width: 31px;
      height: 18px;
      padding: 2px;
      border: 1px solid var(--line-strong);
      border-radius: 999px;
      background: var(--paper);
    }
    .favicon-toggle__switch i {
      display: block;
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: var(--screen-ink);
      transition: transform .24s var(--ease);
    }
    .favicon-toggle[aria-pressed="true"] .favicon-toggle__switch i { transform: translateX(13px); }
    .entity-strip { display: flex; flex-wrap: wrap; gap: 7px; min-height: 0; }
    .entity-token {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      min-height: 30px;
      padding: 0 9px 0 7px;
      border: 1px solid var(--line);
      border-radius: 5px;
      color: var(--ink-soft);
      background: color-mix(in srgb, var(--paper) 66%, transparent);
      font-size: 10px;
      font-weight: 680;
      letter-spacing: -.01em;
      cursor: default;
    }
    button.entity-token { cursor: pointer; }
    button.entity-token:hover, button.entity-token:focus-visible { border-color: var(--line-strong); color: var(--ink); background: var(--paper); }
    .entity-token.is-unassigned {
      border-style: dashed;
      color: var(--ink);
      background:
        repeating-linear-gradient(0deg, transparent 0 3px, color-mix(in srgb, var(--screen-ink) 8%, transparent) 3px 4px),
        color-mix(in srgb, var(--paper) 78%, transparent);
    }
    .entity-token__icon { display: grid; width: 17px; height: 17px; place-items: center; flex: 0 0 auto; color: var(--ink-soft); }
    .entity-token__icon svg { display: block; width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
    .entity-token__hint { color: var(--muted); font-family: var(--mono); font-size: 8px; font-weight: 760; }

    .entity-picker {
      position: fixed;
      z-index: 140;
      width: min(388px, calc(100vw - 28px));
      padding: 14px;
      border: 1px solid var(--line-strong);
      border-radius: 8px;
      color: var(--ink);
      background: color-mix(in srgb, var(--page) 96%, var(--paper));
      box-shadow: 0 22px 58px rgba(31, 35, 30, .2);
      opacity: 0;
      pointer-events: none;
      transform: translateY(5px) scale(.985);
      transition: opacity .16s ease, transform .16s var(--ease), background .35s var(--ease), border-color .35s var(--ease);
    }
    html[data-theme="dark"] .entity-picker { box-shadow: 0 22px 58px rgba(0, 0, 0, .46); }
    .entity-picker.is-open { opacity: 1; pointer-events: auto; transform: none; }
    .entity-picker__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; padding-bottom: 11px; border-bottom: 1px solid var(--line); }
    .entity-picker__head div { display: grid; gap: 3px; }
    .entity-picker__kicker { color: var(--muted); font-family: var(--mono); font-size: 7px; font-weight: 760; letter-spacing: .09em; text-transform: uppercase; }
    .entity-picker__head strong { font-family: var(--reader); font-size: 20px; font-weight: 620; letter-spacing: -.035em; }
    .entity-picker__close { display: grid; width: 27px; height: 27px; place-items: center; border: 1px solid var(--line); border-radius: 4px; color: var(--ink-soft); background: transparent; cursor: pointer; font-size: 17px; }
    .entity-picker__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 11px; border-top: 1px solid var(--line); border-left: 1px solid var(--line); }
    .entity-choice {
      display: grid;
      grid-template-columns: 25px minmax(0, 1fr);
      align-items: center;
      gap: 8px;
      min-height: 42px;
      padding: 0 9px;
      border-right: 1px solid var(--line);
      border-bottom: 1px solid var(--line);
      color: var(--ink-soft);
      background: transparent;
      cursor: pointer;
      font-size: 11px;
      font-weight: 680;
      text-align: left;
    }
    .entity-choice:hover, .entity-choice.is-active { color: var(--ink); background: color-mix(in srgb, var(--paper) 78%, transparent); }
    .entity-choice .entity-token__icon { width: 22px; height: 22px; }
    .entity-choice .entity-token__icon svg { width: 18px; height: 18px; }
    .entity-picker__custom { display: grid; gap: 7px; padding-top: 11px; }
    .entity-picker__custom label { color: var(--muted); font-family: var(--mono); font-size: 7px; font-weight: 740; letter-spacing: .07em; text-transform: uppercase; }
    .entity-picker__custom-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 7px; }
    .entity-picker__custom input { height: 37px; padding: 0 9px; border: 1px solid var(--line); border-radius: 4px; background: var(--paper); font-size: 11px; }
    .entity-picker__save { min-width: 68px; border: 1px solid var(--line-strong); border-radius: 4px; color: var(--ink); background: transparent; cursor: pointer; font-family: var(--mono); font-size: 8px; font-weight: 760; letter-spacing: .05em; text-transform: uppercase; }

    .image-field { position: relative; display: grid; gap: 10px; }
    .image-field.is-drop-target .image-line { border-color: var(--ink); box-shadow: 0 1px 0 var(--ink); }
    .image-drop-popover {
      position: fixed;
      top: 50%;
      left: 50%;
      z-index: 180;
      display: grid;
      grid-template-columns: 42px minmax(0, 1fr);
      align-items: center;
      gap: 13px;
      width: min(380px, calc(100vw - 28px));
      padding: 14px;
      border: 1px solid var(--line-strong);
      border-radius: 12px;
      color: var(--ink);
      background: color-mix(in srgb, var(--page) 96%, transparent);
      box-shadow: 0 24px 70px rgba(22, 25, 21, .18);
      opacity: 0;
      pointer-events: none;
      transform: translate(-50%, -44%) scale(.98);
      backdrop-filter: blur(18px);
      transition: opacity .16s ease, transform .22s var(--ease), background .35s var(--ease), border-color .35s var(--ease);
    }
    .image-drop-popover.is-visible { opacity: 1; transform: translate(-50%, -50%) scale(1); }
    .image-drop-popover__mark {
      display: grid;
      width: 42px;
      height: 42px;
      place-items: center;
      border: 1px solid var(--line-strong);
      border-radius: 9px;
      color: var(--ink);
      background: var(--paper);
      font-family: var(--mono);
      font-size: 18px;
    }
    .image-drop-popover__copy { display: grid; gap: 3px; min-width: 0; }
    .image-drop-popover__copy strong { font-size: 13px; font-weight: 720; letter-spacing: -.018em; }
    .image-drop-popover__copy span { color: var(--muted); font-family: var(--mono); font-size: 8px; font-weight: 650; letter-spacing: .035em; text-transform: uppercase; }

    .image-line { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 12px; min-height: 48px; padding-bottom: 8px; border-bottom: 1px solid var(--line); }
    .image-picker { display: inline-flex; align-items: center; gap: 8px; color: var(--ink); cursor: pointer; font-size: 14px; font-weight: 650; letter-spacing: -.014em; }
    .image-picker:hover { color: var(--ink-soft); }
    .image-picker__plus { display: grid; width: 24px; height: 24px; place-items: center; border: 1px solid var(--line-strong); border-radius: 50%; font-size: 16px; font-weight: 400; }
    .image-meta { min-width: 0; overflow: hidden; color: var(--muted); font-size: 8px; font-weight: 650; text-align: right; text-overflow: ellipsis; white-space: nowrap; }
    .image-remove { padding: 0; border-bottom: 1px solid var(--line-strong); color: var(--ink-soft); background: transparent; cursor: pointer; font-family: var(--mono); font-size: 8px; font-weight: 720; letter-spacing: .05em; text-transform: uppercase; }
    .image-note { margin: 0; color: var(--muted); font-size: 11px; line-height: 1.48; }
    .image-progress { display: grid; gap: 7px; padding-top: 3px; }
    .image-progress__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; color: var(--muted); font-size: 8px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .image-progress__track { position: relative; height: 9px; overflow: hidden; border: 1px solid var(--line-strong); background: var(--paper); }
    .image-progress__track::after { position: absolute; inset: 0; content: ""; opacity: .24; background: repeating-linear-gradient(90deg, transparent 0 7px, var(--screen-ink) 7px 8px); }
    .image-progress__fill { display: block; width: 0; height: 100%; background: repeating-linear-gradient(135deg, var(--screen-ink) 0 2px, color-mix(in srgb, var(--screen-ink) 70%, var(--paper)) 2px 4px); transition: width .28s var(--ease); }
    .image-editor { display: grid; gap: 10px; padding: 4px 0 3px; }
    .image-editor__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; color: var(--muted); font-family: var(--mono); font-size: 8px; font-weight: 720; letter-spacing: .05em; text-transform: uppercase; }
    .crop-stage { position: relative; aspect-ratio: 16 / 9; overflow: hidden; border: 1px solid var(--line-strong); background: var(--paper); cursor: grab; touch-action: none; user-select: none; }
    .crop-stage:active { cursor: grabbing; }
    .crop-stage canvas { display: block; width: 100%; height: 100%; pointer-events: none; }
    .crop-stage::after {
      position: absolute;
      inset: 0;
      content: "";
      pointer-events: none;
      background:
        linear-gradient(90deg, transparent 33.2%, color-mix(in srgb, var(--screen-ink) 15%, transparent) 33.3%, transparent 33.5%, transparent 66.4%, color-mix(in srgb, var(--screen-ink) 15%, transparent) 66.5%, transparent 66.7%),
        linear-gradient(0deg, transparent 33.2%, color-mix(in srgb, var(--screen-ink) 15%, transparent) 33.3%, transparent 33.5%, transparent 66.4%, color-mix(in srgb, var(--screen-ink) 15%, transparent) 66.5%, transparent 66.7%);
      box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--paper) 45%, transparent);
    }
    .image-controls { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 12px; }
    .zoom-control { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 9px; color: var(--muted); font-family: var(--mono); font-size: 8px; font-weight: 700; text-transform: uppercase; }
    .zoom-control input { height: auto; padding: 0; border: 0; accent-color: var(--screen-ink); }
    .image-invert { display: inline-flex; align-items: center; gap: 7px; min-height: 30px; padding: 0 8px; border: 1px solid var(--line); border-radius: 4px; color: var(--ink-soft); background: transparent; cursor: pointer; font-family: var(--mono); font-size: 8px; font-weight: 720; letter-spacing: .04em; text-transform: uppercase; }
    .image-invert[aria-pressed="true"] { border-color: var(--line-strong); color: var(--ink); background: var(--paper); }

    .status { min-height: 17px; color: var(--muted); font-size: 8px; font-weight: 680; letter-spacing: .01em; line-height: 1.5; }
    .status[data-tone="success"] { color: var(--positive); }
    .status[data-tone="warning"] { color: var(--warning); }
    .status[data-tone="danger"] { color: var(--danger); }
    .button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      min-height: 49px;
      padding: 0 17px;
      border: 1px solid var(--line-strong);
      border-radius: 2px;
      color: var(--ink);
      background: transparent;
      cursor: pointer;
      font-size: 9px;
      font-weight: 790;
      letter-spacing: .055em;
      text-transform: uppercase;
      transition: color .2s var(--ease), background .2s var(--ease), border-color .2s var(--ease), transform .2s var(--ease), opacity .2s ease;
    }
    .button:hover:not(:disabled) { transform: translateY(-1px); }
    .button:disabled { color: color-mix(in srgb, var(--muted) 52%, transparent); border-color: color-mix(in srgb, var(--line) 58%, transparent); background: transparent; cursor: not-allowed; }
    .button--update:not(:disabled) { border-color: var(--cta); color: var(--cta-ink); background: var(--cta); box-shadow: 0 12px 30px rgba(37, 41, 36, .13); }
    .button--update:not(:disabled):hover { background: color-mix(in srgb, var(--cta) 88%, var(--ink-soft)); }

    .preview-column { display: grid; gap: 17px; min-width: 0; }
    .device {
      position: relative;
      padding: 16px;
      border: 1px solid var(--device-edge);
      border-radius: 22px;
      background: linear-gradient(145deg, color-mix(in srgb, var(--device) 92%, white), var(--device));
      box-shadow: var(--shadow);
      transition: background .45s var(--ease), border-color .45s var(--ease), box-shadow .45s var(--ease);
    }
    .device::before { position: absolute; inset: 7px; border: 1px solid color-mix(in srgb, var(--device-edge) 65%, transparent); border-radius: 16px; content: ""; pointer-events: none; }
    .display-label { position: relative; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: 15px; padding: 7px 10px 14px; color: var(--screen-muted); font-size: 8px; font-weight: 730; letter-spacing: .08em; text-transform: uppercase; }
    .display-live { display: inline-flex; align-items: center; gap: 6px; }
    .display-live::before { width: 6px; height: 6px; border-radius: 50%; content: ""; background: var(--positive); box-shadow: 0 0 0 3px color-mix(in srgb, var(--positive) 12%, transparent); }
    .display {
      position: relative;
      min-height: 510px;
      overflow: hidden;
      border: 1px solid var(--device-edge);
      border-radius: 4px;
      color: var(--screen-ink);
      background: var(--paper);
      isolation: isolate;
      transition: background .45s var(--ease), border-color .45s var(--ease), color .45s var(--ease);
    }
    .display::before {
      position: absolute;
      inset: 0;
      z-index: 7;
      content: "";
      pointer-events: none;
      opacity: .28;
      mix-blend-mode: multiply;
      background:
        radial-gradient(circle at 17% 23%, rgba(30, 34, 29, .12) 0 .42px, transparent .72px),
        radial-gradient(circle at 72% 61%, rgba(30, 34, 29, .09) 0 .45px, transparent .78px),
        repeating-linear-gradient(0deg, transparent 0 2px, rgba(32, 36, 31, .045) 2px 3px, transparent 3px 5px);
      background-size: 5px 5px, 7px 7px, auto;
    }
    html[data-theme="dark"] .display::before { mix-blend-mode: screen; opacity: .16; }
    .display-content, .display-ghost { position: absolute; inset: 0; display: grid; grid-template-rows: auto minmax(0, 1fr) auto; padding: clamp(34px, 4.4vw, 54px); }
    .display-content { z-index: 3; }
    .display-ghost { z-index: 2; opacity: 0; pointer-events: none; }
    .display-meta, .display-foot { display: flex; align-items: center; justify-content: space-between; gap: 14px; color: var(--screen-muted); font-size: 8px; font-weight: 720; letter-spacing: .07em; text-transform: uppercase; }
    .display-meta { padding-bottom: 15px; border-bottom: 1px solid var(--screen-line); }
    .display-foot { padding-top: 15px; border-top: 1px solid var(--screen-line); }
    .display-body { display: grid; align-content: center; gap: 18px; min-width: 0; padding: clamp(28px, 4.2vw, 58px) 0; }
    .display-media { position: relative; height: 168px; overflow: hidden; border: 1px solid var(--screen-line); background: var(--paper-deep); }
    .display-media img { display: block; width: 100%; height: 100%; object-fit: cover; filter: grayscale(1) contrast(1.08); }
    .display-message {
      margin: 0;
      color: var(--screen-ink);
      font-family: var(--reader);
      font-size: clamp(39px, 4.25vw, 62px);
      font-weight: 610;
      letter-spacing: -.047em;
      line-height: 1.02;
      white-space: pre-wrap;
      overflow-wrap: anywhere;
    }
    .display-content.has-image .display-message { font-size: clamp(29px, 3.25vw, 45px); }
    .display-author { display: flex; align-items: center; gap: 11px; margin-top: 5px; color: var(--screen-muted); font-family: var(--mono); font-size: 9px; font-weight: 760; letter-spacing: .06em; text-transform: uppercase; }
    .display-author::before { width: 30px; height: 1px; content: ""; background: var(--screen-ink); }

    .ink-link {
      display: inline-flex;
      align-items: baseline;
      gap: .16em;
      color: inherit;
      font-weight: inherit;
      text-decoration: none;
      background-image: linear-gradient(currentColor, currentColor);
      background-repeat: no-repeat;
      background-position: 0 96%;
      background-size: 100% 1px;
      transition: opacity .16s ease;
    }
    .ink-link:hover { opacity: .67; }
    .ink-link__icon { display: inline-grid; width: .72em; height: .72em; place-items: center; flex: 0 0 auto; vertical-align: .04em; }
    .ink-link__icon svg { display: block; width: 100%; height: 100%; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .ink-link__favicon { width: .66em; height: .66em; object-fit: contain; filter: grayscale(1) contrast(1.25); opacity: .75; }
    .ink-link__favicon.is-failed { display: none; }
    .ink-handle { display: inline; border-bottom: 1px dotted currentColor; }
    .ink-redaction {
      display: inline-block;
      width: min(calc(var(--chars) * .48em), 9.5em);
      min-width: 1.9em;
      height: .68em;
      margin: 0 .07em;
      vertical-align: -.02em;
      opacity: .58;
      background:
        radial-gradient(circle, currentColor 0 .55px, transparent .8px) 0 0 / 4px 4px,
        repeating-linear-gradient(0deg, transparent 0 2px, color-mix(in srgb, currentColor 36%, transparent) 2px 3px);
      border-top: 1px solid color-mix(in srgb, currentColor 55%, transparent);
      border-bottom: 1px solid color-mix(in srgb, currentColor 42%, transparent);
      clip-path: polygon(0 18%, 2% 10%, 7% 16%, 13% 9%, 19% 15%, 28% 8%, 36% 14%, 46% 7%, 57% 14%, 67% 8%, 79% 15%, 90% 9%, 100% 17%, 98% 82%, 91% 90%, 83% 84%, 72% 92%, 61% 84%, 50% 92%, 39% 85%, 28% 91%, 18% 84%, 8% 90%, 0 82%);
    }

    .refresh-layer { position: absolute; inset: 0; z-index: 8; pointer-events: none; opacity: 0; }
    .refresh-layer--black { background: #111411; }
    .refresh-layer--paper { background: #f1f0e7; }
    .refresh-layer--scan { top: -28%; bottom: auto; height: 30%; background: linear-gradient(180deg, transparent, rgba(17, 20, 17, .9) 45%, rgba(241, 240, 231, .88) 54%, transparent); filter: blur(3px); }
    .display.is-refreshing .display-ghost { animation: ghost 1.35s var(--ease) both; }
    .display.is-refreshing .refresh-layer--black { animation: flashBlack 1.35s linear both; }
    .display.is-refreshing .refresh-layer--paper { animation: flashPaper 1.35s linear both; }
    .display.is-refreshing .refresh-layer--scan { animation: scan 1.35s var(--ease) both; }
    .display.is-refreshing .display-content { animation: settle 1.35s var(--ease) both; }
    .display.is-theme-refreshing .refresh-layer--black { animation: themeBlack .88s linear both; }
    .display.is-theme-refreshing .refresh-layer--paper { animation: themePaper .88s linear both; }
    .display.is-theme-refreshing .refresh-layer--scan { animation: themeScan .88s var(--ease) both; }
    .display.is-theme-refreshing .display-content { animation: themeSettle .88s var(--ease) both; }
    @keyframes ghost { 0%, 6% { opacity: 0; } 8%, 31% { opacity: .11; filter: invert(1) blur(.35px); } 45%, 67% { opacity: .055; filter: none; } 100% { opacity: 0; } }
    @keyframes flashBlack { 0%, 5% { opacity: 0; } 6%, 20% { opacity: 1; } 21%, 39% { opacity: 0; } 40%, 50% { opacity: .92; } 51%, 100% { opacity: 0; } }
    @keyframes flashPaper { 0%, 20% { opacity: 0; } 21%, 39% { opacity: 1; } 40%, 50% { opacity: 0; } 51%, 64% { opacity: .92; } 65%, 100% { opacity: 0; } }
    @keyframes scan { 0%, 29% { opacity: 0; transform: translateY(-30%); } 30% { opacity: .9; } 79% { opacity: .55; transform: translateY(430%); } 80%, 100% { opacity: 0; transform: translateY(430%); } }
    @keyframes settle { 0%, 50% { opacity: .08; filter: contrast(.48) blur(.55px); } 66% { opacity: .58; filter: contrast(.75) blur(.2px); } 82% { opacity: .9; filter: contrast(1.08); } 100% { opacity: 1; filter: none; } }
    @keyframes themeBlack { 0%, 6% { opacity: 0; } 7%, 28% { opacity: 1; } 29%, 50% { opacity: 0; } 51%, 61% { opacity: .88; } 62%, 100% { opacity: 0; } }
    @keyframes themePaper { 0%, 28% { opacity: 0; } 29%, 50% { opacity: 1; } 51%, 61% { opacity: 0; } 62%, 76% { opacity: .9; } 77%, 100% { opacity: 0; } }
    @keyframes themeScan { 0%, 30% { opacity: 0; transform: translateY(-35%); } 31% { opacity: .85; } 82% { opacity: .45; transform: translateY(430%); } 83%, 100% { opacity: 0; transform: translateY(430%); } }
    @keyframes themeSettle { 0%, 56% { opacity: .14; filter: contrast(.55) blur(.4px); } 74% { opacity: .74; filter: contrast(.84); } 100% { opacity: 1; filter: none; } }

    .publish-stage { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 22px; padding: 19px 2px 0; border-top: 1px solid var(--line); }
    .publish-copy { display: grid; gap: 5px; min-width: 0; }
    .publish-state { color: var(--muted); font-size: 9px; font-weight: 760; letter-spacing: .075em; text-transform: uppercase; }
    .publish-copy strong { color: var(--ink); font-size: 15px; font-weight: 690; letter-spacing: -.02em; }
    .publish-copy p { margin: 0; color: var(--muted); font-size: 12px; line-height: 1.45; }
    .publish-stage .button { min-width: 184px; }
    .publish-stage[data-state="ready"] .button:not(:disabled),
    .publish-stage[data-state="live"] .button:not(:disabled) { border-color: var(--cta); color: var(--cta-ink); background: var(--cta); box-shadow: 0 14px 34px rgba(36, 40, 36, .15); }
    .publish-stage[data-state="ready"] .button:not(:disabled):hover,
    .publish-stage[data-state="live"] .button:not(:disabled):hover { transform: translateY(-2px); }
    .publish-stage[data-state="live"] { border-top-color: var(--line-strong); }
    .publish-stage[data-state="live"] .publish-state { color: var(--positive); }

    .destination { margin-top: clamp(68px, 8vw, 112px); }
    .destination__media {
      overflow: hidden;
      border: 1px solid var(--line);
      border-radius: 34px 34px 12px 12px;
      background: var(--paper);
      box-shadow: 0 18px 52px rgba(35, 39, 34, .08);
      transition: border-color .35s var(--ease), background .35s var(--ease), box-shadow .35s var(--ease);
    }
    .destination__banner { display: block; width: 100%; aspect-ratio: 3 / 1; object-fit: cover; border-radius: inherit; filter: grayscale(.05); }
    .destination__line { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 28px; padding: 25px 2px; border-bottom: 1px solid var(--line); }
    .destination-kicker { display: block; margin-bottom: 7px; color: var(--muted); font-size: 9px; font-weight: 760; letter-spacing: .1em; text-transform: uppercase; }
    .destination__line strong { display: block; color: var(--ink); font-size: clamp(24px, 3vw, 42px); font-weight: 610; letter-spacing: -.05em; line-height: 1; }
    .destination__line p { max-width: 690px; margin: 8px 0 0; color: var(--muted); font-size: 13px; line-height: 1.5; }
    .destination__links { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 9px; }
    .destination__links a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 9px;
      min-height: 44px;
      padding: 0 14px;
      border: 1px solid var(--line);
      border-radius: 9px;
      color: var(--ink-soft);
      background: transparent;
      font-family: var(--mono);
      font-size: 9px;
      font-weight: 720;
      letter-spacing: .035em;
      text-decoration: none;
      text-transform: uppercase;
      transition: color .18s ease, border-color .18s ease, background .18s ease, transform .18s var(--ease);
    }
    .destination__links a:hover { border-color: var(--line-strong); color: var(--ink); background: var(--paper); transform: translateY(-1px); }
    .destination__links a:first-child { border-color: var(--cta); color: var(--cta-ink); background: var(--cta); }
    .destination__links a:first-child:hover { color: var(--cta-ink); background: color-mix(in srgb, var(--cta) 88%, var(--page)); }

    .recent { margin-top: clamp(70px, 8vw, 112px); }
    .recent-head { display: flex; align-items: end; justify-content: space-between; gap: 22px; padding-bottom: 17px; border-bottom: 1px solid var(--line); }
    .recent-kicker { display: block; margin-bottom: 7px; color: var(--muted); font-size: 9px; font-weight: 760; letter-spacing: .1em; text-transform: uppercase; }
    .recent h2 { margin: 0; font-family: var(--reader); font-size: clamp(34px, 4vw, 58px); font-weight: 610; letter-spacing: -.052em; }
    .recent-count { color: var(--muted); font-size: 9px; font-weight: 720; }
    .recent-list { display: grid; }
    .recent-entry {
      display: grid;
      grid-template-columns: 38px minmax(0, 1fr) auto;
      gap: 22px;
      align-items: start;
      padding: 24px 2px;
      border-bottom: 1px solid var(--line);
      transition: background .18s ease;
    }
    .recent-entry:hover { background: color-mix(in srgb, var(--paper) 38%, transparent); }
    .recent-entry.is-active { background: color-mix(in srgb, var(--paper) 58%, transparent); }
    .recent-index { padding-top: 5px; color: var(--muted); font-size: 9px; font-weight: 720; }
    .recent-main { display: grid; gap: 12px; min-width: 0; }
    .recent-message { margin: 0; color: var(--ink); font-family: var(--reader); font-size: clamp(22px, 2.3vw, 34px); font-weight: 600; letter-spacing: -.036em; line-height: 1.08; white-space: pre-wrap; overflow-wrap: anywhere; }
    .recent-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 10px 14px; color: var(--muted); font-size: 8px; font-weight: 680; letter-spacing: .025em; text-transform: uppercase; }
    .recent-meta strong { color: var(--ink-soft); }
    .recent-links { display: flex; flex-wrap: wrap; gap: 7px; }
    .recent-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      min-height: 28px;
      padding: 0 8px;
      border: 1px solid var(--line);
      border-radius: 4px;
      color: var(--ink-soft);
      background: transparent;
      font-family: var(--mono);
      font-size: 8px;
      font-weight: 700;
      text-decoration: none;
    }
    .recent-link:hover { border-color: var(--line-strong); color: var(--ink); background: var(--paper); }
    .recent-link .entity-token__icon { width: 15px; height: 15px; }
    .recent-actions { display: grid; gap: 8px; justify-items: end; }
    .recent-view, .recent-report {
      padding: 0;
      border-bottom: 1px solid var(--line-strong);
      color: var(--ink-soft);
      background: transparent;
      cursor: pointer;
      font-family: var(--mono);
      font-size: 8px;
      font-weight: 720;
      letter-spacing: .045em;
      text-decoration: none;
      text-transform: uppercase;
    }
    .recent-view:hover, .recent-report:hover { color: var(--ink); }
    .recent-empty { padding: 30px 2px; color: var(--muted); font-size: 13px; border-bottom: 1px solid var(--line); }
    .recent-tools { padding-top: 18px; }
    .load-more { padding: 0; border-bottom: 1px solid var(--line-strong); color: var(--ink-soft); background: transparent; cursor: pointer; font-family: var(--mono); font-size: 9px; font-weight: 720; letter-spacing: .05em; text-transform: uppercase; }

    .policy { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 28px; align-items: end; margin-top: 74px; padding-top: 20px; border-top: 1px solid var(--line); }
    .policy-kicker { display: block; margin-bottom: 6px; color: var(--muted); font-size: 8px; font-weight: 760; letter-spacing: .09em; text-transform: uppercase; }
    .policy p { max-width: 850px; margin: 0; color: var(--muted); font-size: 11px; line-height: 1.58; }
    .policy a { color: var(--ink-soft); text-underline-offset: 3px; }
    .policy__report { white-space: nowrap; font-family: var(--mono); font-size: 9px; font-weight: 720; }

    .site-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      margin-top: 28px;
      padding-top: 18px;
      border-top: 1px solid var(--line);
      color: var(--muted);
      font-family: var(--mono);
      font-size: 8px;
      font-weight: 650;
      letter-spacing: .035em;
      text-transform: uppercase;
    }
    .site-footer__credit { display: flex; align-items: center; flex-wrap: wrap; gap: 5px; }
    .site-footer__credit a { color: var(--ink-soft); text-decoration: none; }
    .site-footer__credit a:hover { color: var(--ink); text-decoration: underline; text-underline-offset: 3px; }
    .site-footer__repo {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      min-height: 34px;
      padding: 0 10px;
      border: 1px solid var(--line);
      border-radius: 8px;
      color: var(--ink-soft);
      text-decoration: none;
      transition: color .18s ease, border-color .18s ease, background .18s ease;
    }
    .site-footer__repo:hover { border-color: var(--line-strong); color: var(--ink); background: var(--paper); }
    .site-footer__repo svg { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

    .toast { position: fixed; right: 22px; bottom: 22px; z-index: 160; max-width: min(390px, calc(100vw - 32px)); padding: 12px 14px; border: 1px solid var(--line); border-radius: 4px; color: var(--ink); background: color-mix(in srgb, var(--page) 93%, transparent); box-shadow: 0 18px 50px rgba(20, 23, 20, .16); font-family: var(--mono); font-size: 9px; font-weight: 650; line-height: 1.5; opacity: 0; pointer-events: none; transform: translateY(10px); backdrop-filter: blur(12px); transition: opacity .18s ease, transform .18s var(--ease), background .35s var(--ease), border-color .35s var(--ease); }
    .toast.is-visible { opacity: 1; transform: none; }

    @media (max-width: 980px) {
      .workflow { grid-template-columns: 1fr; gap: 42px; }
      .composer { position: static; }
      .preview-column { max-width: 760px; width: 100%; margin: 0 auto; }
    }

    @media (max-width: 680px) {
      .page { padding: 68px 18px 62px; }
      .page-back { top: 12px; left: 12px; min-height: 39px; width: 39px; justify-content: center; padding: 0; }
      .page-back__label { display: none; }
      .theme-toggle { top: 12px; right: 12px; min-height: 39px; }
      .theme-toggle__label { display: none; }
      .hero { margin-bottom: 37px; }
      h1 { font-size: clamp(47px, 14vw, 66px); }
      .hero__route { align-items: flex-start; flex-direction: column; gap: 7px; margin-top: 17px; }
      .workflow { gap: 32px; }
      .composer { gap: 21px; }
      textarea { min-height: 106px; }
      .entity-head { align-items: flex-start; flex-direction: column; gap: 7px; }
      .entity-picker { right: 10px !important; left: 10px !important; bottom: 10px; top: auto !important; width: auto; }
      .entity-picker__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .image-line { grid-template-columns: auto minmax(0, 1fr); }
      .image-remove { grid-column: 1 / -1; justify-self: start; }
      .image-controls { grid-template-columns: 1fr; }
      .device { padding: 9px; border-radius: 17px; box-shadow: 0 18px 50px rgba(34, 38, 33, .13); }
      .device::before { inset: 4px; border-radius: 13px; }
      .display-label { padding: 6px 7px 9px; font-size: 7px; }
      .display { min-height: 270px; }
      .display-content, .display-ghost { padding: 19px; }
      .display-meta, .display-foot { font-size: 6.8px; letter-spacing: .055em; }
      .display-meta { padding-bottom: 10px; }
      .display-foot { padding-top: 10px; }
      .display-body { gap: 12px; padding: 16px 0; }
      .display-message { font-size: clamp(27px, 9vw, 38px); line-height: 1.02; }
      .display-content.has-image .display-media { height: 82px; }
      .display-content.has-image .display-message { font-size: clamp(22px, 7vw, 31px); }
      .display-author { margin-top: 8px; font-size: 7px; }
      .display-author::before { width: 22px; }
      .publish-stage { grid-template-columns: 1fr; gap: 11px; padding-top: 14px; }
      .publish-stage .button { width: 100%; min-width: 0; }
      .destination { margin-top: 68px; }
      .destination__media { border-radius: 22px 22px 8px 8px; }
      .destination__line { grid-template-columns: 1fr; gap: 18px; }
      .destination__links { justify-content: flex-start; }
      .destination__links a { flex: 1 1 180px; }
      .site-footer { align-items: flex-start; flex-direction: column; }
      .site-footer__repo { width: 100%; justify-content: center; }
      .recent-entry { grid-template-columns: 28px minmax(0, 1fr); gap: 12px; padding: 20px 0; }
      .recent-actions { grid-column: 2; display: flex; gap: 14px; justify-items: start; justify-content: flex-start; }
      .recent-message { font-size: clamp(22px, 7vw, 29px); }
      .policy { grid-template-columns: 1fr; gap: 14px; margin-top: 58px; }
      .toast { right: 12px; bottom: 12px; left: 12px; max-width: none; }
    }

    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { scroll-behavior: auto !important; animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
    }
  </style>

  <style id="eink-v9-polish">
    .hero__destination {
      display: inline-flex;
      align-items: baseline;
      gap: 4px;
      padding: 5px 8px;
      border: 1px solid var(--line);
      border-radius: 4px;
      color: var(--ink);
      background: color-mix(in srgb, var(--paper) 72%, transparent);
      font-family: var(--mono);
      font-size: 10px;
      font-weight: 700;
      letter-spacing: .01em;
      text-decoration: none;
      transition: color .18s ease, border-color .18s ease, background .18s ease, transform .18s var(--ease);
    }
    .hero__destination sup { position: relative; top: -.18em; font-size: .68em; line-height: 1; }
    .hero__destination:hover { border-color: var(--line-strong); background: var(--paper); transform: translateY(-1px); }

    .entity-token { gap: 4px; padding-inline: 9px; }
    .entity-token__platform {
      position: relative;
      top: -.32em;
      display: inline-grid;
      width: 13px;
      height: 13px;
      margin-left: 1px;
      place-items: center;
      color: var(--ink-soft);
      line-height: 1;
    }
    .entity-token__platform .entity-token__icon { width: 13px; height: 13px; }
    .entity-token__platform .entity-token__icon svg { width: 12px; height: 12px; stroke-width: 1.75; }

    .ink-link--identity { display: inline; background-position: 0 96%; }
    .ink-link__platform {
      position: relative;
      top: -.42em;
      display: inline-grid;
      width: .68em;
      height: .68em;
      margin-left: .14em;
      place-items: center;
      line-height: 1;
    }
    .ink-link__platform .ink-link__icon { width: 100%; height: 100%; }
    .ink-link__platform .ink-link__icon svg { width: 100%; height: 100%; }

    .destination__media {
      position: relative;
      isolation: isolate;
      border-radius: 34px 34px 12px 12px;
    }
    .destination__banner {
      position: relative;
      z-index: 1;
      border-radius: 33px 33px 0 0;
      transition: transform 1.15s var(--ease), filter .55s var(--ease);
      will-change: transform;
    }
    .destination__pixel-field {
      position: absolute;
      inset: 0;
      z-index: 2;
      display: block;
      width: 100%;
      height: 100%;
      border-radius: 33px 33px 0 0;
      pointer-events: none;
      image-rendering: pixelated;
      mix-blend-mode: multiply;
      opacity: .48;
      transition: opacity .5s var(--ease);
    }
    html[data-theme="dark"] .destination__pixel-field { mix-blend-mode: screen; opacity: .22; }
    .destination__media:hover .destination__banner { transform: scale(1.006); filter: grayscale(.08) contrast(1.015); }
    .destination__media:hover .destination__pixel-field { opacity: .66; }

    .recent-entry { position: relative; }
    .recent-actions { min-width: 72px; }
    .recent-view, .recent-report {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      min-height: 26px;
      border: 0;
      border-bottom: 1px solid var(--line-strong);
    }
    .recent-report {
      opacity: 0;
      transform: translateY(3px);
      transition: opacity .16s ease, transform .2s var(--ease), color .16s ease;
    }
    .recent-entry:hover .recent-report,
    .recent-entry:focus-within .recent-report,
    .recent-report[aria-expanded="true"] { opacity: 1; transform: none; }
    .recent-report svg { width: 12px; height: 12px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .recent-link--identity { gap: 3px; }
    .recent-link__platform {
      position: relative;
      top: -.28em;
      display: inline-grid;
      width: 12px;
      height: 12px;
      margin-left: 1px;
      place-items: center;
    }
    .recent-link__platform .entity-token__icon { width: 12px; height: 12px; }
    .recent-link__platform .entity-token__icon svg { width: 11px; height: 11px; }

    .report-popover {
      position: fixed;
      z-index: 190;
      width: min(420px, calc(100vw - 28px));
      padding: 15px;
      border: 1px solid var(--line-strong);
      border-radius: 10px;
      color: var(--ink);
      background: color-mix(in srgb, var(--page) 96%, var(--paper));
      box-shadow: 0 26px 70px rgba(28, 32, 27, .22);
      opacity: 0;
      pointer-events: none;
      transform: translateY(6px) scale(.985);
      transition: opacity .16s ease, transform .2s var(--ease), background .35s var(--ease), border-color .35s var(--ease);
    }
    html[data-theme="dark"] .report-popover { box-shadow: 0 28px 78px rgba(0, 0, 0, .5); }
    .report-popover.is-open { opacity: 1; pointer-events: auto; transform: none; }
    .report-popover__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--line); }
    .report-popover__head > div { display: grid; gap: 3px; }
    .report-popover__kicker, .report-popover__note, .report-reasons legend, .report-detail > span, .report-detail small, .report-popover__status { font-family: var(--mono); }
    .report-popover__kicker { color: var(--muted); font-size: 7px; font-weight: 760; letter-spacing: .1em; text-transform: uppercase; }
    .report-popover__head strong { font-family: var(--reader); font-size: 23px; font-weight: 620; letter-spacing: -.04em; }
    .report-popover__note { color: var(--muted); font-size: 8px; font-weight: 650; }
    .report-popover__close { display: grid; width: 29px; height: 29px; place-items: center; flex: 0 0 auto; border: 1px solid var(--line); border-radius: 4px; color: var(--ink-soft); background: transparent; cursor: pointer; font-size: 18px; }
    .report-reasons { display: grid; gap: 0; margin: 13px 0 0; padding: 0; border: 0; }
    .report-reasons legend { margin-bottom: 7px; color: var(--muted); font-size: 7px; font-weight: 760; letter-spacing: .09em; text-transform: uppercase; }
    .report-reason { position: relative; display: grid; grid-template-columns: 17px minmax(0, 1fr); gap: 9px; padding: 9px 7px; border-top: 1px solid var(--line); cursor: pointer; }
    .report-reason:last-child { border-bottom: 1px solid var(--line); }
    .report-reason input { width: 14px; height: 14px; margin: 2px 0 0; accent-color: var(--cta); }
    .report-reason span { display: grid; gap: 2px; }
    .report-reason strong { font-size: 11px; font-weight: 720; letter-spacing: -.014em; }
    .report-reason small { color: var(--muted); font-size: 9px; line-height: 1.35; }
    .report-reason:has(input:checked) { background: color-mix(in srgb, var(--paper) 72%, transparent); }
    .report-reason--severe:has(input:checked)::after { position: absolute; top: 12px; right: 7px; content: "Priority review"; color: var(--danger); font-family: var(--mono); font-size: 6px; font-weight: 760; letter-spacing: .07em; text-transform: uppercase; }
    .report-detail { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 7px; margin-top: 13px; }
    .report-detail > span { color: var(--muted); font-size: 7px; font-weight: 760; letter-spacing: .08em; text-transform: uppercase; }
    .report-detail textarea { grid-column: 1 / -1; width: 100%; min-height: 72px; padding: 9px 10px; resize: vertical; border: 1px solid var(--line); border-radius: 4px; outline: 0; color: var(--ink); background: var(--paper); font-size: 11px; line-height: 1.45; }
    .report-detail textarea:focus { border-color: var(--line-strong); box-shadow: 0 0 0 3px var(--focus); }
    .report-detail small { color: var(--muted); font-size: 7px; }
    .report-popover__status { min-height: 16px; margin: 9px 0 0; color: var(--danger); font-size: 8px; line-height: 1.4; }
    .report-popover__actions { display: flex; justify-content: flex-end; gap: 8px; padding-top: 10px; border-top: 1px solid var(--line); }
    .report-cancel, .report-submit { min-height: 37px; padding: 0 11px; border: 1px solid var(--line-strong); border-radius: 3px; cursor: pointer; font-family: var(--mono); font-size: 8px; font-weight: 760; letter-spacing: .055em; text-transform: uppercase; }
    .report-cancel { color: var(--ink-soft); background: transparent; }
    .report-submit { border-color: var(--cta); color: var(--cta-ink); background: var(--cta); }
    .report-submit:disabled { opacity: .48; cursor: wait; }

    @media (hover: none), (pointer: coarse), (max-width: 980px) {
      .recent-report { opacity: 1; transform: none; }
    }
    @media (max-width: 680px) {
      .destination__media { border-radius: 22px 22px 8px 8px; }
      .destination__banner, .destination__pixel-field { border-radius: 21px 21px 0 0; }
      .report-popover { right: 10px !important; bottom: 10px; left: 10px !important; top: auto !important; width: auto; max-height: calc(100svh - 20px); overflow: auto; border-radius: 12px; }
      .report-popover__head strong { font-size: 21px; }
      .report-reason { padding-block: 8px; }
      .recent-actions { min-width: 0; }
    }
    @media (prefers-reduced-motion: reduce) {
      .destination__banner { transform: none !important; }
      .destination__pixel-field { opacity: .28; }
    }
  </style>


  <style id="eink-v10-refinement">
    .entity-token__platform {
      top: -.18em;
      width: 12px;
      height: 12px;
      margin-left: -2px;
      transform: translateX(-1px);
    }
    .entity-token__platform .entity-token__icon,
    .entity-token__platform .entity-token__icon svg { width: 11px; height: 11px; }

    .ink-link__platform {
      top: -.22em;
      width: .6em;
      height: .6em;
      margin-left: .01em;
      transform: translateX(-.03em);
    }
    .recent-link__platform {
      top: -.16em;
      width: 11px;
      height: 11px;
      margin-left: -1px;
      transform: translateX(-1px);
    }
    .recent-link__platform .entity-token__icon,
    .recent-link__platform .entity-token__icon svg { width: 10px; height: 10px; }

    .destination__media {
      overflow: hidden;
      background: var(--paper);
    }
    .destination__pixel-field {
      z-index: 3;
      opacity: .9;
      mix-blend-mode: normal;
      filter: contrast(1.08);
    }
    html[data-theme="dark"] .destination__pixel-field { opacity: .82; mix-blend-mode: normal; }
    .destination__media::after {
      position: absolute;
      inset: 0;
      z-index: 2;
      border-radius: inherit;
      content: "";
      pointer-events: none;
      background-image:
        linear-gradient(90deg, transparent 0 49%, rgba(20,23,20,.055) 49% 51%, transparent 51%),
        linear-gradient(0deg, transparent 0 49%, rgba(250,251,244,.05) 49% 51%, transparent 51%);
      background-size: 10px 10px;
      opacity: .38;
      animation: eink-banner-grid-drift 12s steps(12, end) infinite;
    }
    .destination__media:hover .destination__pixel-field { opacity: 1; }

    @keyframes eink-banner-grid-drift {
      0% { background-position: 0 0, 0 0; opacity: .27; }
      33% { background-position: 10px 0, 0 10px; opacity: .42; }
      66% { background-position: -10px 10px, 10px -10px; opacity: .32; }
      100% { background-position: 0 0, 0 0; opacity: .27; }
    }

    .recent-report {
      min-height: 30px;
      padding: 0 8px;
      border: 1px solid var(--line);
      border-radius: 4px;
      background: color-mix(in srgb, var(--paper) 72%, transparent);
      border-bottom-color: var(--line-strong);
    }
    .recent-report:hover,
    .recent-report[aria-expanded="true"] {
      border-color: var(--line-strong);
      color: var(--ink);
      background: var(--paper);
    }
    .recent-report::after {
      content: attr(data-note-id);
      position: absolute;
      width: 1px;
      height: 1px;
      overflow: hidden;
      clip-path: inset(50%);
      white-space: nowrap;
    }

    .report-popover {
      --report-arrow-x: calc(100% - 24px);
      border-radius: 12px;
      box-shadow: 0 24px 64px rgba(22, 26, 22, .2), 0 2px 8px rgba(22, 26, 22, .08);
    }
    .report-popover::before,
    .report-popover::after {
      position: absolute;
      left: var(--report-arrow-x);
      width: 12px;
      height: 12px;
      content: "";
      transform: translateX(-50%) rotate(45deg);
    }
    .report-popover::before { z-index: -1; background: var(--line-strong); }
    .report-popover::after { background: color-mix(in srgb, var(--page) 96%, var(--paper)); }
    .report-popover[data-placement="bottom"]::before { top: -7px; }
    .report-popover[data-placement="bottom"]::after { top: -6px; }
    .report-popover[data-placement="top"]::before { bottom: -7px; }
    .report-popover[data-placement="top"]::after { bottom: -6px; }
    .report-popover__note {
      max-width: 330px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    @media (max-width: 680px) {
      .report-popover::before,
      .report-popover::after { display: none; }
      .recent-report { min-height: 34px; padding-inline: 10px; }
    }
    @media (prefers-reduced-motion: reduce) {
      .destination__media::after { animation: none; }
    }
  </style>


  <style id="eink-v11-archive-and-reactions">
    /* Keep the banner alive without a directional refresh sweep. */
    .destination__media::before { display: none !important; animation: none !important; content: none !important; }
    .destination__media::after { opacity: .34; animation-duration: 14s; }

    .recent-head { align-items: end; }
    .recent-head__tools { display: flex; align-items: center; justify-content: flex-end; gap: 14px; min-width: 0; }
    .archive-search {
      display: grid;
      grid-template-columns: 17px minmax(120px, 230px) 24px;
      align-items: center;
      min-height: 42px;
      padding: 0 8px 0 12px;
      border: 1px solid var(--line);
      border-radius: 999px;
      color: var(--muted);
      background: color-mix(in srgb, var(--paper) 58%, transparent);
      transition: border-color .18s ease, background .18s ease, box-shadow .18s ease;
    }
    .archive-search:focus-within { border-color: var(--line-strong); background: var(--paper); box-shadow: 0 0 0 3px var(--focus); }
    .archive-search svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
    .archive-search input { height: 40px; padding: 0 8px; border: 0; font-family: var(--mono); font-size: 9px; font-weight: 680; letter-spacing: .015em; }
    .archive-search input::-webkit-search-cancel-button { display: none; }
    .archive-search button { display: grid; width: 24px; height: 24px; place-items: center; border-radius: 50%; color: var(--muted); background: transparent; cursor: pointer; font-size: 16px; line-height: 1; }
    .archive-search button:hover { color: var(--ink); background: var(--paper-deep); }
    .recent-search-state { min-height: 18px; padding-top: 9px; color: var(--muted); font-family: var(--mono); font-size: 8px; font-weight: 650; letter-spacing: .02em; }

    .recent-entry.is-prepared { background: color-mix(in srgb, var(--paper) 44%, transparent); }
    .recent-entry.is-prepared::before { position: absolute; top: 0; bottom: 0; left: 0; width: 2px; content: ""; background: var(--ink-soft); opacity: .34; }
    .prepared-badge, .owner-managed {
      display: inline-flex;
      align-items: center;
      min-height: 20px;
      padding: 0 7px;
      border: 1px solid var(--line);
      border-radius: 999px;
      color: var(--ink-soft);
      background: color-mix(in srgb, var(--paper) 74%, transparent);
      font-family: var(--mono);
      font-size: 7px;
      font-weight: 760;
      letter-spacing: .06em;
      text-transform: uppercase;
      white-space: nowrap;
    }
    .owner-managed { min-height: 27px; border-style: dashed; color: var(--muted); background: transparent; }

    .reaction-bar { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; min-height: 30px; }
    .reaction-pill, .reaction-add {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      min-height: 29px;
      padding: 0 9px;
      border: 1px solid var(--line);
      border-radius: 999px;
      color: var(--ink-soft);
      background: color-mix(in srgb, var(--paper) 54%, transparent);
      cursor: pointer;
      transition: color .16s ease, border-color .16s ease, background .16s ease, transform .18s var(--ease);
    }
    .reaction-pill:hover, .reaction-add:hover { border-color: var(--line-strong); color: var(--ink); background: var(--paper); transform: translateY(-1px); }
    .reaction-pill.is-selected { border-color: var(--ink-soft); color: var(--ink); background: var(--paper); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--ink) 12%, transparent); }
    .reaction-pill:disabled { cursor: wait; opacity: .58; transform: none; }
    .reaction-pill__emoji, .reaction-choice__emoji { filter: grayscale(1) contrast(1.2); font-family: "Apple Color Emoji", "Segoe UI Emoji", sans-serif; }
    .reaction-pill__emoji { font-size: 13px; line-height: 1; }
    .reaction-pill__count { font-family: var(--mono); font-size: 8px; font-weight: 760; }
    .reaction-add { padding-inline: 10px; font-family: var(--mono); font-size: 8px; font-weight: 760; letter-spacing: .035em; text-transform: uppercase; }
    .reaction-add > span:first-child { font-size: 14px; line-height: 1; transform: translateY(-.5px); }

    .reaction-popover {
      position: fixed;
      z-index: 195;
      width: min(390px, calc(100vw - 28px));
      padding: 14px;
      border: 1px solid var(--line-strong);
      border-radius: 14px;
      color: var(--ink);
      background: color-mix(in srgb, var(--page) 96%, var(--paper));
      box-shadow: 0 24px 64px rgba(22, 26, 22, .22), 0 2px 8px rgba(22, 26, 22, .08);
      opacity: 0;
      pointer-events: none;
      transform: translateY(6px) scale(.985);
      transition: opacity .16s ease, transform .2s var(--ease), background .35s var(--ease), border-color .35s var(--ease);
    }
    html[data-theme="dark"] .reaction-popover { box-shadow: 0 28px 78px rgba(0, 0, 0, .5); }
    .reaction-popover.is-open { opacity: 1; pointer-events: auto; transform: none; }
    .reaction-popover__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; padding-bottom: 11px; border-bottom: 1px solid var(--line); }
    .reaction-popover__head > div { display: grid; gap: 3px; }
    .reaction-popover__kicker, .reaction-popover__note, .reaction-popover__hint { font-family: var(--mono); }
    .reaction-popover__kicker { color: var(--muted); font-size: 7px; font-weight: 760; letter-spacing: .1em; text-transform: uppercase; }
    .reaction-popover__head strong { font-family: var(--reader); font-size: 23px; font-weight: 620; letter-spacing: -.04em; }
    .reaction-popover__note { max-width: 310px; overflow: hidden; color: var(--muted); font-size: 8px; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
    .reaction-popover__close { display: grid; width: 29px; height: 29px; place-items: center; flex: 0 0 auto; border: 1px solid var(--line); border-radius: 4px; color: var(--ink-soft); background: transparent; cursor: pointer; font-size: 18px; }
    .reaction-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 7px; padding-top: 12px; }
    .reaction-grid.is-busy { opacity: .55; pointer-events: none; }
    .reaction-choice { display: grid; grid-template-columns: 31px minmax(0, 1fr); align-items: center; gap: 9px; min-height: 54px; padding: 7px 9px; border: 1px solid var(--line); border-radius: 8px; color: var(--ink-soft); background: transparent; cursor: pointer; text-align: left; }
    .reaction-choice:hover { border-color: var(--line-strong); color: var(--ink); background: var(--paper); }
    .reaction-choice.is-selected { border-color: var(--ink-soft); color: var(--ink); background: var(--paper); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--ink) 10%, transparent); }
    .reaction-choice__emoji { display: grid; width: 31px; height: 31px; place-items: center; border: 1px solid var(--line); border-radius: 50%; font-size: 17px; }
    .reaction-choice__copy { display: grid; gap: 2px; }
    .reaction-choice__copy strong { font-size: 10px; font-weight: 740; letter-spacing: -.01em; }
    .reaction-choice__copy small { color: var(--muted); font-family: var(--mono); font-size: 8px; font-weight: 720; }
    .reaction-popover__hint { margin: 11px 0 0; color: var(--muted); font-size: 7px; line-height: 1.5; }

    @media (max-width: 820px) {
      .recent-head { align-items: stretch; flex-direction: column; }
      .recent-head__tools { width: 100%; justify-content: space-between; }
      .archive-search { flex: 1; grid-template-columns: 17px minmax(0, 1fr) 24px; }
    }
    @media (max-width: 680px) {
      .recent-head__tools { align-items: stretch; flex-direction: column; gap: 8px; }
      .recent-count { align-self: flex-end; }
      .archive-search { width: 100%; }
      .reaction-popover { right: 10px !important; bottom: 10px; left: 10px !important; top: auto !important; width: auto; max-height: calc(100svh - 20px); overflow: auto; border-radius: 12px; }
      .reaction-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .reaction-choice { min-height: 50px; }
      .owner-managed { min-height: 30px; }
    }
    @media (max-width: 390px) {
      .reaction-grid { grid-template-columns: 1fr; }
      .reaction-choice { grid-template-columns: 29px minmax(0, 1fr); min-height: 44px; }
      .reaction-choice__emoji { width: 29px; height: 29px; font-size: 15px; }
    }
    @media (prefers-reduced-motion: reduce) {
      .destination__media::after { animation: none !important; }
      .reaction-pill, .reaction-add { transform: none !important; }
    }
  </style>

</head>
<body>
  <button class="page-back" id="pageBackButton" type="button" aria-label="Return to angusu.de">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
    <span class="page-back__label">Back</span>
  </button>

  <button class="theme-toggle" id="themeToggle" type="button" aria-label="Switch to dark mode">
    <span class="theme-toggle__label" id="themeLabel">Dark mode</span>
    <span class="theme-toggle__track" aria-hidden="true"></span>
  </button>

  <main class="page">
    <header class="hero">
      <span class="eyebrow">Public E-Ink to GitHub</span>
      <h1>Put a note on my GitHub.</h1>
      <div class="hero__route">
        <span>The newest published ink is rendered into</span>
        <a class="hero__destination" id="heroDestinationLink" href="https://github.com/IamAngusU" target="_blank" rel="noopener noreferrer">github.com/IamAngusU<sup aria-hidden="true">↗</sup></a>
      </div>
    </header>

    <section class="workflow" aria-label="Compose, preview and publish">
      <form class="composer" id="messageForm" novalidate>
        <div class="step-label">01 / Write</div>

        <label class="field">
          <span class="field-head">
            <span class="field-label">Name</span>
            <span class="counter" id="nameCounter">0 / 28</span>
          </span>
          <input id="nameInput" name="name" maxlength="28" autocomplete="name" placeholder="Your name" required>
        </label>

        <label class="field">
          <span class="field-head">
            <span class="field-label">Message</span>
            <span class="counter" id="messageCounter">0 / 120</span>
          </span>
          <textarea id="messageInput" name="message" maxlength="120" placeholder="Leave a short public note" required></textarea>
        </label>

        <div class="entity-area" id="entityArea" hidden>
          <div class="entity-head">
            <span class="entity-summary" id="entitySummary"></span>
            <button class="favicon-toggle" id="faviconToggle" type="button" aria-pressed="true">
              <span class="favicon-toggle__switch" aria-hidden="true"><i></i></span>
              <span id="faviconToggleText">Site icons on</span>
            </button>
          </div>
          <div class="entity-strip" id="entityStrip" aria-label="Detected destinations"></div>
        </div>

        <div class="image-field" id="imageField">
          <div class="field-head">
            <span class="field-label">Image</span>
            <span class="counter">Optional</span>
          </div>
          <div class="image-line">
            <label class="image-picker" for="imageInput">
              <span class="image-picker__plus" aria-hidden="true">+</span>
              <span>Add image</span>
            </label>
            <span class="image-meta" id="imageMeta">No image selected</span>
            <button class="image-remove" id="removeImageButton" type="button" hidden>Remove</button>
          </div>
          <input class="file-input" id="imageInput" type="file" accept="image/jpeg,image/png,image/webp,image/avif">
          <p class="image-note">Converted locally to a four-tone WebP. Drag the crop, zoom, or invert the image independently from the page theme.</p>

          <div class="image-progress" id="imageProgress" hidden>
            <div class="image-progress__head">
              <span id="imageProgressLabel">Reading image</span>
              <span id="imageProgressValue">0%</span>
            </div>
            <div class="image-progress__track"><span class="image-progress__fill" id="imageProgressFill"></span></div>
          </div>

          <div class="image-editor" id="imageEditor" hidden>
            <div class="image-editor__head">
              <span>Visible frame</span>
              <span id="imageEditorState">Drag to reposition</span>
            </div>
            <div class="crop-stage" id="cropStage" tabindex="0" aria-label="Drag to reposition the selected image">
              <canvas id="cropCanvas" width="800" height="450"></canvas>
            </div>
            <div class="image-controls">
              <label class="zoom-control">
                <span>Zoom</span>
                <input id="imageZoom" type="range" min="100" max="220" step="1" value="100">
                <span id="imageZoomValue">100%</span>
              </label>
              <button class="image-invert" id="imageInvertButton" type="button" aria-pressed="false">Invert image</button>
            </div>
          </div>
        </div>

        <input class="honeypot" id="websiteInput" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

        <div class="status" id="formStatus" role="status" aria-live="polite">Write a name and a message.</div>
        <button class="button button--update" id="updateInkButton" type="button" disabled>Update ink</button>
      </form>

      <div class="preview-column">
        <div class="step-label">02 / Ink preview</div>
        <section class="device" aria-label="E-Ink preview">
          <div class="display-label">
            <span>Paper display / GitHub target</span>
            <span class="display-live" id="deviceState">Live surface</span>
          </div>
          <div class="display" id="display">
            <div class="display-ghost" id="displayGhost" aria-hidden="true"></div>
            <div class="display-content" id="displayContent">
              <div class="display-meta">
                <span id="displayMode">Latest public note</span>
                <time id="displayDate"></time>
              </div>
              <div class="display-body">
                <div class="display-media" id="displayMedia" hidden><img id="displayImage" alt=""></div>
                <p class="display-message" id="displayMessage">No public ink yet.</p>
                <span class="display-author" id="displayName">Anonymous</span>
              </div>
              <div class="display-foot">
                <span>github.com/IamAngusU</span>
                <span id="displayScope">Public surface</span>
              </div>
            </div>
            <div class="refresh-layer refresh-layer--black"></div>
            <div class="refresh-layer refresh-layer--paper"></div>
            <div class="refresh-layer refresh-layer--scan"></div>
          </div>
        </section>

        <div class="publish-stage" id="publishStage" data-state="blocked">
          <div class="publish-copy">
            <span class="publish-state" id="publishState">03 / Publish to GitHub</span>
            <strong id="publishHeadline">Preview required.</strong>
            <p id="publishHint">Update the display, review the visible ink, then publish it to the public GitHub surface.</p>
          </div>
          <button class="button" id="publishButton" type="button" data-action="publish" disabled>Publish note</button>
        </div>
      </div>
    </section>

    <section class="destination" aria-labelledby="destinationTitle">
      <div class="destination__media">
      <img class="destination__banner" src="assets/github-destination.png" alt="Angus Uelsmann GitHub profile banner">
        <canvas class="destination__pixel-field" id="destinationPixelField" aria-hidden="true"></canvas>
      </div>
      <div class="destination__line">
        <div>
          <span class="destination-kicker">Where the ink lands</span>
          <strong id="destinationTitle">The latest note lives on my profile.</strong>
          <p>Publishing replaces the current E-Ink message in the profile README. Reload the GitHub profile to see the newest public version.</p>
        </div>
        <div class="destination__links">
          <a id="liveProfileLink" href="https://github.com/IamAngusU" target="_blank" rel="noopener noreferrer">Open live profile <span aria-hidden="true">↗</span></a>
          <a id="repositoryLink" href="https://github.com/IamAngusU/IamAngusU" target="_blank" rel="noopener noreferrer">Profile repository <span aria-hidden="true">↗</span></a>
        </div>
      </div>
    </section>

    <section class="recent" id="recentInks" aria-labelledby="recentTitle">
      <div class="recent-head">
        <div>
          <span class="recent-kicker">Public archive</span>
          <h2 id="recentTitle">Recent inks.</h2>
        </div>
        <div class="recent-head__tools">
          <label class="archive-search" for="recentSearch">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path></svg>
            <input id="recentSearch" type="search" autocomplete="off" spellcheck="false" placeholder="Search every ink">
            <button id="recentSearchClear" type="button" aria-label="Clear archive search" hidden>×</button>
          </label>
          <span class="recent-count" id="recentCount">0 notes</span>
        </div>
      </div>
      <div class="recent-search-state" id="recentSearchState" aria-live="polite"></div>
      <div class="recent-list" id="recentList"></div>
      <div class="recent-tools" id="recentTools" hidden>
        <button class="load-more" id="loadMoreButton" type="button">Show more</button>
      </div>
    </section>

    <footer class="policy">
      <div>
        <span class="policy-kicker">Visitor content and external destinations</span>
        <p>Notes and linked destinations are submitted by visitors and do not represent Angus Uelsmann. External destinations are not endorsed or verified. Visitor inks can be reported. The prepared ink from Angus is owner managed and excluded from public reports. Priority safety reports place a visitor ink on immediate review hold. Other report categories require two independent signals before the note is hidden for review. Moderation rules, reporting procedures, and security controls are continuously maintained. Usage is correlated with a random browser pseudonym; only country hints and referrer domains are retained. Raw IP addresses, identities, user agents, and complete referrer URLs are not stored by InkWall.</p>
      </div>
      <a class="policy__report" href="#recentInks">Report a note</a>
    </footer>

    <footer class="site-footer">
      <div class="site-footer__credit">
        <span>Designed and programmed by</span>
        <a href="https://angusu.de" target="_blank" rel="noopener noreferrer">angusu.de</a>
        <span aria-hidden="true">·</span>
        <span>Angus Uelsmann</span>
      </div>
      <a class="site-footer__repo" id="footerRepositoryLink" href="https://github.com/IamAngusU/IamAngusU" target="_blank" rel="noopener noreferrer">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8a9.2 9.2 0 0 0-2.9 17.9c.5.1.7-.2.7-.5v-1.8c-2.8.6-3.4-1.2-3.4-1.2-.5-1.2-1.1-1.5-1.1-1.5-.9-.7.1-.7.1-.7 1 .1 1.6 1.1 1.6 1.1.9 1.6 2.4 1.1 2.9.9.1-.7.4-1.1.7-1.4-2.2-.3-4.6-1.1-4.6-5a3.9 3.9 0 0 1 1-2.7 3.6 3.6 0 0 1 .1-2.7s.8-.3 2.8 1a9.5 9.5 0 0 1 5.1 0c2-1.3 2.8-1 2.8-1a3.6 3.6 0 0 1 .1 2.7 3.9 3.9 0 0 1 1 2.7c0 3.9-2.4 4.7-4.6 5 .4.3.7 1 .7 2v3c0 .3.2.6.7.5A9.2 9.2 0 0 0 12 2.8Z"></path></svg>
        <span>View repository</span>
      </a>
    </footer>
  </main>

  <div class="entity-picker" id="entityPicker" role="dialog" aria-label="Choose a destination for the handle">
    <div class="entity-picker__head">
      <div>
        <span class="entity-picker__kicker">Handle destination</span>
        <strong id="entityPickerHandle">@username</strong>
      </div>
      <button class="entity-picker__close" id="entityPickerClose" type="button" aria-label="Close">×</button>
    </div>
    <div class="entity-picker__grid" id="entityPickerChoices"></div>
    <div class="entity-picker__custom" id="entityPickerCustom" hidden>
      <label for="entityCustomUrl">HTTPS destination</label>
      <div class="entity-picker__custom-row">
        <input id="entityCustomUrl" type="url" placeholder="https://example.com/profile">
        <button class="entity-picker__save" id="entityCustomSave" type="button">Save</button>
      </div>
    </div>
  </div>

  <div class="image-drop-popover" id="imageDropPopover" role="status" aria-live="polite" aria-hidden="true">
    <span class="image-drop-popover__mark" aria-hidden="true">+</span>
    <span class="image-drop-popover__copy">
      <strong>Drop the image here</strong>
      <span>JPEG, PNG, WebP or AVIF up to 12 MB</span>
    </span>
  </div>


  <div class="report-popover" id="reportPopover" role="dialog" aria-labelledby="reportTitle" aria-hidden="true">
    <form id="reportForm" novalidate>
      <div class="report-popover__head">
        <div>
          <span class="report-popover__kicker">Content review</span>
          <strong id="reportTitle">Report this ink</strong>
          <span class="report-popover__note" id="reportNoteReference"></span>
        </div>
        <button class="report-popover__close" id="reportCloseButton" type="button" aria-label="Close report form">×</button>
      </div>
      <fieldset class="report-reasons">
        <legend>Reason</legend>
        <label class="report-reason"><input type="radio" name="reportReason" value="spam"><span><strong>Spam or manipulation</strong><small>Repeated promotion, scams, or coordinated noise</small></span></label>
        <label class="report-reason"><input type="radio" name="reportReason" value="harassment"><span><strong>Harassment or abuse</strong><small>Targeted insults, bullying, or degrading content</small></span></label>
        <label class="report-reason report-reason--severe"><input type="radio" name="reportReason" value="hate"><span><strong>Hate or dehumanization</strong><small>Attacks based on protected characteristics</small></span></label>
        <label class="report-reason report-reason--severe"><input type="radio" name="reportReason" value="threat"><span><strong>Threat or immediate danger</strong><small>Violence, self-harm encouragement, or credible threats</small></span></label>
        <label class="report-reason report-reason--severe"><input type="radio" name="reportReason" value="privacy"><span><strong>Personal or private information</strong><small>Exposed contact details, credentials, or identifying data</small></span></label>
        <label class="report-reason"><input type="radio" name="reportReason" value="other"><span><strong>Something else</strong><small>Explain the concern below</small></span></label>
      </fieldset>
      <label class="report-detail">
        <span>Optional context</span>
        <textarea id="reportDetail" maxlength="240" placeholder="Add details that help with the review"></textarea>
        <small><span id="reportDetailCount">0</span> / 240</small>
      </label>
      <p class="report-popover__status" id="reportStatus" role="status" aria-live="polite"></p>
      <div class="report-popover__actions">
        <button class="report-cancel" id="reportCancelButton" type="button">Cancel</button>
        <button class="report-submit" id="reportSubmitButton" type="submit">Submit report</button>
      </div>
    </form>
  </div>

  <div class="reaction-popover" id="reactionPopover" role="dialog" aria-labelledby="reactionTitle" aria-hidden="true">
    <div class="reaction-popover__head">
      <div>
        <span class="reaction-popover__kicker">React to ink</span>
        <strong id="reactionTitle">Leave a reaction</strong>
        <span class="reaction-popover__note" id="reactionNoteReference"></span>
      </div>
      <button class="reaction-popover__close" id="reactionCloseButton" type="button" aria-label="Close reaction picker">×</button>
    </div>
    <div class="reaction-grid" id="reactionGrid" aria-label="Available reactions"></div>
    <p class="reaction-popover__hint">Choose more than one. Tap an active reaction again to remove it.</p>
  </div>

  <div class="toast" id="toast" role="status" aria-live="polite"></div>

  <script type="module">
    const AppConfig = Object.freeze({
      apiBase: `${location.pathname.replace(/\/(?:index\.php)?$/, "")}/api`.replace(/^\/\//, "/"),
      storageKey: "angusu-eink-wall-v11",
      themeKey: "angusu-eink-theme-v4",
      destinationUrl: "https://github.com/IamAngusU",
      repositoryUrl: "https://github.com/IamAngusU/InkWall",
      fallbackBackUrl: "https://angusu.de/connect",
      reportStorageKey: "angusu-eink-reports-v2",
      reporterStorageKey: "angusu-eink-reporter-v1",
      reactionStorageKey: "angusu-eink-reactions-v1",
      reactorStorageKey: "angusu-eink-reactor-v1",
      preparedInk: Object.freeze({
        id: "angusu-prepared-ink",
        name: "Angus Uelsmann",
        message: "This surface is yours for a moment. Leave the next ink on my GitHub profile.",
        image: null,
        bindings: Object.freeze({}),
        showFavicons: true,
        reportable: false,
        prepared: true,
        createdAt: "2026-07-10T12:00:00.000Z"
      }),
      limits: Object.freeze({ name: 28, message: 120, imageInputBytes: 12 * 1024 * 1024, imageOutputBytes: 480 * 1024 }),
      archivePageSize: 5,
      archiveLocalLimit: 250,
      refreshDuration: 1350,
      refreshSwapDelay: 575,
      themeDuration: 880,
      themeSwapDelay: 305
    });

    const Dom = Object.freeze({
      html: document.documentElement,
      heroDestinationLink: document.getElementById("heroDestinationLink"),
      destinationPixelField: document.getElementById("destinationPixelField"),
      pageBackButton: document.getElementById("pageBackButton"),
      form: document.getElementById("messageForm"),
      nameInput: document.getElementById("nameInput"),
      messageInput: document.getElementById("messageInput"),
      websiteInput: document.getElementById("websiteInput"),
      nameCounter: document.getElementById("nameCounter"),
      messageCounter: document.getElementById("messageCounter"),
      formStatus: document.getElementById("formStatus"),
      updateButton: document.getElementById("updateInkButton"),
      publishButton: document.getElementById("publishButton"),
      publishStage: document.getElementById("publishStage"),
      publishState: document.getElementById("publishState"),
      publishHeadline: document.getElementById("publishHeadline"),
      publishHint: document.getElementById("publishHint"),
      entityArea: document.getElementById("entityArea"),
      entitySummary: document.getElementById("entitySummary"),
      entityStrip: document.getElementById("entityStrip"),
      faviconToggle: document.getElementById("faviconToggle"),
      faviconToggleText: document.getElementById("faviconToggleText"),
      entityPicker: document.getElementById("entityPicker"),
      entityPickerHandle: document.getElementById("entityPickerHandle"),
      entityPickerChoices: document.getElementById("entityPickerChoices"),
      entityPickerClose: document.getElementById("entityPickerClose"),
      entityPickerCustom: document.getElementById("entityPickerCustom"),
      entityCustomUrl: document.getElementById("entityCustomUrl"),
      entityCustomSave: document.getElementById("entityCustomSave"),
      imageField: document.getElementById("imageField"),
      imageInput: document.getElementById("imageInput"),
      imageMeta: document.getElementById("imageMeta"),
      removeImageButton: document.getElementById("removeImageButton"),
      imageProgress: document.getElementById("imageProgress"),
      imageProgressLabel: document.getElementById("imageProgressLabel"),
      imageProgressValue: document.getElementById("imageProgressValue"),
      imageProgressFill: document.getElementById("imageProgressFill"),
      imageEditor: document.getElementById("imageEditor"),
      imageEditorState: document.getElementById("imageEditorState"),
      cropStage: document.getElementById("cropStage"),
      cropCanvas: document.getElementById("cropCanvas"),
      imageZoom: document.getElementById("imageZoom"),
      imageZoomValue: document.getElementById("imageZoomValue"),
      imageInvertButton: document.getElementById("imageInvertButton"),
      imageDropPopover: document.getElementById("imageDropPopover"),
      display: document.getElementById("display"),
      displayContent: document.getElementById("displayContent"),
      displayGhost: document.getElementById("displayGhost"),
      displayMode: document.getElementById("displayMode"),
      displayDate: document.getElementById("displayDate"),
      displayMedia: document.getElementById("displayMedia"),
      displayImage: document.getElementById("displayImage"),
      displayMessage: document.getElementById("displayMessage"),
      displayName: document.getElementById("displayName"),
      displayScope: document.getElementById("displayScope"),
      deviceState: document.getElementById("deviceState"),
      themeToggle: document.getElementById("themeToggle"),
      themeLabel: document.getElementById("themeLabel"),
      recentList: document.getElementById("recentList"),
      recentCount: document.getElementById("recentCount"),
      recentTools: document.getElementById("recentTools"),
      recentSearch: document.getElementById("recentSearch"),
      recentSearchClear: document.getElementById("recentSearchClear"),
      recentSearchState: document.getElementById("recentSearchState"),
      loadMoreButton: document.getElementById("loadMoreButton"),
      liveProfileLink: document.getElementById("liveProfileLink"),
      repositoryLink: document.getElementById("repositoryLink"),
      footerRepositoryLink: document.getElementById("footerRepositoryLink"),
      reportPopover: document.getElementById("reportPopover"),
      reportForm: document.getElementById("reportForm"),
      reportTitle: document.getElementById("reportTitle"),
      reportNoteReference: document.getElementById("reportNoteReference"),
      reportCloseButton: document.getElementById("reportCloseButton"),
      reportCancelButton: document.getElementById("reportCancelButton"),
      reportSubmitButton: document.getElementById("reportSubmitButton"),
      reportDetail: document.getElementById("reportDetail"),
      reportDetailCount: document.getElementById("reportDetailCount"),
      reportStatus: document.getElementById("reportStatus"),
      reactionPopover: document.getElementById("reactionPopover"),
      reactionTitle: document.getElementById("reactionTitle"),
      reactionNoteReference: document.getElementById("reactionNoteReference"),
      reactionCloseButton: document.getElementById("reactionCloseButton"),
      reactionGrid: document.getElementById("reactionGrid"),
      toast: document.getElementById("toast")
    });

    const PrimaryAction = Object.freeze({
      PUBLISH: "publish",
      VIEW_LIVE: "view-live"
    });

    class NavigationController {
      static isAngusuReferrer() {
        if (!document.referrer) return false;
        try {
          const referrer = new URL(document.referrer);
          return referrer.hostname === "angusu.de" || referrer.hostname.endsWith(".angusu.de");
        } catch {
          return false;
        }
      }

      static returnToPreviousSurface() {
        if (history.length > 1 && this.isAngusuReferrer()) {
          history.back();
          return;
        }
        location.assign(AppConfig.fallbackBackUrl);
      }
    }

    const PlatformCatalog = Object.freeze({
      instagram: { label: "Instagram", icon: "instagram", build: handle => `https://www.instagram.com/${encodeURIComponent(handle)}/` },
      threads: { label: "Threads", icon: "threads", build: handle => `https://www.threads.com/@${encodeURIComponent(handle)}` },
      x: { label: "X", icon: "x", build: handle => `https://x.com/${encodeURIComponent(handle)}` },
      github: { label: "GitHub", icon: "github", build: handle => `https://github.com/${encodeURIComponent(handle)}` },
      tiktok: { label: "TikTok", icon: "tiktok", build: handle => `https://www.tiktok.com/@${encodeURIComponent(handle)}` },
      youtube: { label: "YouTube", icon: "youtube", build: handle => `https://www.youtube.com/@${encodeURIComponent(handle)}` },
      bluesky: { label: "Bluesky", icon: "bluesky", build: handle => `https://bsky.app/profile/${encodeURIComponent(handle)}` },
      linkedin: { label: "LinkedIn", icon: "linkedin", build: handle => `https://www.linkedin.com/in/${encodeURIComponent(handle)}/` },
      text: { label: "Plain text", icon: "at", build: () => null },
      custom: { label: "Other link", icon: "link", build: () => null }
    });

    const IconRegistry = Object.freeze({
      path(name) {
        const icons = {
          mail: '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 7 9-7"></path>',
          link: '<path d="M10 13a5 5 0 0 0 7.1.1l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1"></path><path d="M14 11a5 5 0 0 0-7.1-.1l-2 2a5 5 0 0 0 7.1 7.1l1.1-1.1"></path>',
          at: '<circle cx="12" cy="12" r="8"></circle><path d="M16 12v-1a4 4 0 1 0-1.2 2.8c.7.7 2.2.4 2.2-.8v-1"></path>',
          instagram: '<rect x="4" y="4" width="16" height="16" rx="5"></rect><circle cx="12" cy="12" r="3.5"></circle><circle cx="17.2" cy="6.8" r=".7" fill="currentColor" stroke="none"></circle>',
          threads: '<path d="M8.2 8.4c1.7-2.2 6.5-2.1 7.8.7 1.3 2.8-.8 7.1-4.3 7-3.2-.1-4.4-3.7-2.2-5.5 2.2-1.8 6.4-.4 6.7 2.7.3 3.1-2.2 5.6-5.3 5.7-4.9.2-8.1-4.6-6.4-9.1C6.2 5.4 12 3.6 16.1 6"></path>',
          x: '<path d="m5 4 14 16"></path><path d="M19 4 5 20"></path>',
          github: '<path d="M12 2.8a9.2 9.2 0 0 0-2.9 17.9c.5.1.7-.2.7-.5v-1.8c-2.8.6-3.4-1.2-3.4-1.2-.5-1.2-1.1-1.5-1.1-1.5-.9-.7.1-.7.1-.7 1 .1 1.6 1.1 1.6 1.1.9 1.6 2.4 1.1 2.9.9.1-.7.4-1.1.7-1.4-2.2-.3-4.6-1.1-4.6-5a3.9 3.9 0 0 1 1-2.7 3.6 3.6 0 0 1 .1-2.7s.8-.3 2.8 1a9.5 9.5 0 0 1 5.1 0c2-1.3 2.8-1 2.8-1a3.6 3.6 0 0 1 .1 2.7 3.9 3.9 0 0 1 1 2.7c0 3.9-2.4 4.7-4.6 5 .4.3.7 1 .7 2v3c0 .3.2.6.7.5A9.2 9.2 0 0 0 12 2.8Z"></path>',
          tiktok: '<path d="M14 4v10.2a4.2 4.2 0 1 1-3.3-4.1"></path><path d="M14 4c1.1 2.2 2.6 3.4 5 3.7"></path>',
          youtube: '<rect x="3" y="6" width="18" height="12" rx="4"></rect><path d="m10 9 5 3-5 3Z"></path>',
          bluesky: '<path d="M6.2 5.6c2.4 1.7 4.9 5.1 5.8 6.9.9-1.8 3.4-5.2 5.8-6.9 1.7-1.2 4.4-2.1 3.1 1.7-.2.8-1.4 6.4-2.2 7.3-2.4 2.6-5.5-.7-5.9-1.5.1 1.4.6 5.5-2.8 5.5s-2.9-4.1-2.8-5.5c-.4.8-3.5 4.1-5.9 1.5-.8-.9-2-6.5-2.2-7.3-1.3-3.8 1.4-2.9 3.1-1.7Z"></path>',
          linkedin: '<rect x="4" y="9" width="4" height="11"></rect><circle cx="6" cy="5.5" r="2"></circle><path d="M12 20V9h4v1.8c.9-1.4 4-2.1 4 2.7V20"></path>',
          flag: '<path d="M5 21V4"></path><path d="M5 5h10l-1.8 3L15 11H5"></path>'
        };
        return icons[name] || icons.link;
      },
      create(name, className = "entity-token__icon") {
        const span = document.createElement("span");
        span.className = className;
        span.setAttribute("aria-hidden", "true");
        span.innerHTML = `<svg viewBox="0 0 24 24">${this.path(name)}</svg>`;
        return span;
      }
    });

    class TextCanonicalizer {
      static characterMap = Object.freeze({
        "0": "o", "1": "i", "!": "i", "|": "i", "3": "e", "4": "a", "@": "a", "5": "s", "$": "s", "7": "t", "+": "t", "8": "b", "9": "g", "6": "g",
        "а": "a", "е": "e", "о": "o", "р": "p", "с": "c", "х": "x", "у": "y", "к": "k", "м": "m", "т": "t", "в": "b", "н": "h", "і": "i", "ј": "j"
      });

      static canonical(value) {
        const normalized = String(value || "")
          .normalize("NFKD")
          .replace(/[\u0300-\u036f\u200B-\u200D\uFEFF]/g, "")
          .replace(/ß/g, "ss")
          .toLowerCase();
        let result = "";
        for (const character of normalized) {
          const mapped = this.characterMap[character] ?? character;
          if (/[a-z]/.test(mapped)) result += mapped;
        }
        return result.replace(/(.)\1{2,}/g, "$1$1");
      }

      static tokenize(source) {
        const tokens = [];
        const pattern = /[\p{L}\p{N}!|@$+]+/gu;
        let match;
        while ((match = pattern.exec(source))) {
          tokens.push({ raw: match[0], start: match.index, end: match.index + match[0].length });
        }
        return tokens;
      }
    }

    class EntityParser {
      static emailExact = /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i;
      static pattern = /\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b|https?:\/\/[^\s<]+|www\.[^\s<]+|\b(?:[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?\.)+(?:[a-z]{2,24})(?:\/[^\s<]*)?|\B@[A-Z0-9][A-Z0-9._-]{0,31}/gi;

      static normalizeWebUrl(raw) {
        try { return new URL(/^https?:\/\//i.test(raw) ? raw : `https://${raw}`).href; }
        catch { return null; }
      }

      static parse(source) {
        const text = String(source || "");
        const entities = [];
        this.pattern.lastIndex = 0;
        let match;
        while ((match = this.pattern.exec(text))) {
          let raw = match[0];
          let tail = "";
          if (!this.emailExact.test(raw) && !raw.startsWith("@")) {
            while (/[),.!?;:]$/.test(raw)) {
              tail = raw.slice(-1) + tail;
              raw = raw.slice(0, -1);
            }
          }
          const start = match.index;
          const end = start + raw.length;
          if (this.emailExact.test(raw)) {
            entities.push({ type: "email", raw, key: `email:${raw.toLowerCase()}`, href: `mailto:${raw}`, start, end, tail });
          } else if (raw.startsWith("@")) {
            const handle = raw.slice(1).toLowerCase();
            entities.push({ type: "mention", raw, handle, key: `mention:${handle}`, href: null, start, end, tail });
          } else {
            const href = this.normalizeWebUrl(raw);
            entities.push({ type: "url", raw, key: `url:${href || raw}`, href, start, end, tail });
          }
        }
        this.pattern.lastIndex = 0;
        return entities;
      }
    }

    class ModerationPolicy {
      static redactionMarker = /⟦ink:(\d{1,3}):([a-z-]+)⟧/g;
      static hardRules = Object.freeze([
        { id: "hate-en", reason: "Hate speech cannot be published.", terms: ["nigger", "nigga", "faggot", "kike", "chink", "spic"] },
        { id: "hate-de", reason: "Hate speech cannot be published.", terms: ["kanake", "neger"] },
        { id: "self-harm", reason: "Threats or self-harm encouragement cannot be published.", terms: ["kys", "killyourself", "gokillyourself", "bringdichum", "toetedich", "totedich"] },
        { id: "threat", reason: "Direct threats cannot be published.", terms: ["ikillyou", "iwillkillyou", "illkillyou", "ichbringdichum", "ichwerdedichumbringen", "ichmachdichfertig"] }
      ]);
      static softRules = Object.freeze([
        { id: "profanity", terms: ["fuck", "fucking", "fking", "fck", "fucker", "fuckers", "fuckyou", "fuckoff", "fcuk", "phuck", "fick", "ficken", "ficker", "fickdich", "verfickt", "gefickt"] },
        { id: "family-insult", terms: ["motherfucker", "motherfuckers", "motherfucking", "mutterficker", "mutterfickers", "mutterficken", "mutterfick"] },
        { id: "profanity", terms: ["shit", "shitty", "shithead", "bullshit", "scheisse", "scheiss", "scheisser", "scheissdreck", "kacke"] },
        { id: "insult", terms: ["asshole", "dumbass", "jackass", "dickhead", "bastard", "bitch", "cunt", "pussy", "whore", "slut"] },
        { id: "insult", terms: ["arschloch", "huso", "huansohn", "hurensohn", "hurensoehne", "hurentochter", "fotze", "schlampe", "wichser", "wixer", "missgeburt", "drecksau", "drecksschwein", "verpissdich", "pimmel"] }
      ]);

      static overlaps(a, b) { return a.start < b.end && b.start < a.end; }

      static isException(source, span, canonical, tokenCount) {
        const raw = source.slice(span.start, span.end).trim();
        if (canonical === "fking" && /^[A-Z]\.\s+King$/.test(raw)) return true;
        if (canonical === "fck" && tokenCount === 1 && raw.replace(/[^A-Za-z]/g, "") === "FCK") return true;
        return false;
      }

      static findRule(canonical, rules) {
        return rules.find(rule => rule.terms.includes(canonical)) || null;
      }

      static scan(source, { protectEntities = true } = {}) {
        const tokens = TextCanonicalizer.tokenize(source);
        const protectedRanges = protectEntities ? EntityParser.parse(source).map(entity => ({ start: entity.start, end: entity.end })) : [];
        const matches = [];

        for (let size = Math.min(5, tokens.length); size >= 1; size -= 1) {
          for (let index = 0; index <= tokens.length - size; index += 1) {
            const windowTokens = tokens.slice(index, index + size);
            const span = { start: windowTokens[0].start, end: windowTokens.at(-1).end };
            if (matches.some(match => this.overlaps(match, span))) continue;
            if (protectedRanges.some(range => this.overlaps(range, span))) continue;

            const canonical = TextCanonicalizer.canonical(windowTokens.map(token => token.raw).join(""));
            if (canonical.length < 3 || this.isException(source, span, canonical, size)) continue;

            const hard = this.findRule(canonical, this.hardRules);
            if (hard) return { allowed: false, reason: hard.reason, matches: [] };

            const soft = this.findRule(canonical, this.softRules);
            if (soft) matches.push({ ...span, category: soft.id, canonical });
          }
        }

        return { allowed: true, reason: null, matches: matches.sort((a, b) => a.start - b.start) };
      }

      static marker(source, category) {
        const length = Math.min(Math.max(Array.from(source).length, 3), 36);
        return `⟦ink:${length}:${category}⟧`;
      }

      static inspectMessage(source) {
        const clean = String(source || "").replace(/\r\n?/g, "\n").replace(/[\t\f\v]+/g, " ").replace(/[ ]{2,}/g, " ").replace(/\n{3,}/g, "\n\n").trim();
        const result = this.scan(clean, { protectEntities: true });
        if (!result.allowed) return { allowed: false, reason: result.reason, moderated: "", count: 0, clean };
        if (!result.matches.length) return { allowed: true, reason: null, moderated: clean, count: 0, clean };

        let cursor = 0;
        let moderated = "";
        for (const match of result.matches) {
          moderated += clean.slice(cursor, match.start);
          moderated += this.marker(clean.slice(match.start, match.end), match.category);
          cursor = match.end;
        }
        moderated += clean.slice(cursor);
        return { allowed: true, reason: null, moderated, count: result.matches.length, clean };
      }

      static inspectName(source) {
        const clean = String(source || "").replace(/\s+/g, " ").trim();
        if (!clean) return { allowed: false, reason: "Enter a display name.", clean };
        const result = this.scan(clean, { protectEntities: false });
        if (!result.allowed || result.matches.length) return { allowed: false, reason: "Choose a neutral display name.", clean };
        return { allowed: true, reason: null, clean };
      }
    }

    class BindingStore {
      constructor() { this.bindings = {}; }

      sanitize(input) {
        const output = {};
        if (!input || typeof input !== "object") return output;
        for (const [rawHandle, rawBinding] of Object.entries(input)) {
          const handle = String(rawHandle || "").replace(/^@/, "").trim().toLowerCase();
          if (!/^[a-z0-9][a-z0-9._-]{0,31}$/i.test(handle)) continue;
          const platform = String(rawBinding?.platform || "text");
          if (!PlatformCatalog[platform]) continue;
          let url = "";
          if (platform === "custom") {
            try {
              const parsed = new URL(String(rawBinding?.url || ""));
              if (parsed.protocol !== "https:") continue;
              url = parsed.href;
            } catch { continue; }
          }
          output[handle] = { platform, url };
        }
        return output;
      }

      set(handle, binding) { this.bindings = this.sanitize({ ...this.bindings, [handle]: binding }); }
      get(handle) { return this.bindings[handle] || null; }
      activeFor(source) {
        const active = {};
        for (const entity of EntityParser.parse(source)) {
          if (entity.type === "mention" && this.bindings[entity.handle]) active[entity.handle] = this.bindings[entity.handle];
        }
        return this.sanitize(active);
      }
      signature(source) {
        const active = this.activeFor(source);
        return Object.keys(active).sort().map(handle => `${handle}:${active[handle].platform}:${active[handle].url || ""}`).join("|");
      }
    }

    class LinkRenderer {
      static mentionHref(handle, binding) {
        if (!binding) return null;
        if (binding.platform === "custom") return binding.url || null;
        const platform = PlatformCatalog[binding.platform];
        return platform?.build ? platform.build(handle) : null;
      }

      static appendPlain(target, value) {
        const parts = String(value).split("\n");
        parts.forEach((part, index) => {
          if (index) target.append(document.createElement("br"));
          if (part) target.append(document.createTextNode(part));
        });
      }

      static createFavicon(href) {
        const image = document.createElement("img");
        image.className = "ink-link__favicon";
        image.alt = "";
        image.loading = "lazy";
        image.decoding = "async";
        image.referrerPolicy = "no-referrer";
        try {
          const origin = new URL(href).origin;
          image.src = `${origin}/favicon.ico`;
        } catch { image.classList.add("is-failed"); }
        image.addEventListener("error", () => image.classList.add("is-failed"), { once: true });
        return image;
      }

      static createAnchor(entity, binding, showFavicons) {
        const href = entity.type === "mention" ? this.mentionHref(entity.handle, binding) : entity.href;
        if (!href) return null;
        const anchor = document.createElement("a");
        const identityLink = entity.type === "email" || entity.type === "mention";
        anchor.className = `ink-link${identityLink ? " ink-link--identity" : ""}`;
        anchor.href = href;
        anchor.rel = "ugc nofollow noopener noreferrer";
        anchor.referrerPolicy = "no-referrer";
        if (/^https?:\/\//i.test(href)) anchor.target = "_blank";

        const display = entity.type === "url"
          ? entity.raw.replace(/^https?:\/\/(?:www\.)?/i, "").replace(/\/$/, "")
          : entity.raw;

        if (entity.type === "url") {
          if (showFavicons) anchor.append(this.createFavicon(href));
          else anchor.append(IconRegistry.create("link", "ink-link__icon"));
          anchor.append(document.createTextNode(display));
          return anchor;
        }

        anchor.append(document.createTextNode(display));
        const platform = document.createElement("sup");
        platform.className = "ink-link__platform";
        const iconName = entity.type === "email" ? "mail" : PlatformCatalog[binding.platform]?.icon || "at";
        platform.append(IconRegistry.create(iconName, "ink-link__icon"));
        anchor.append(platform);
        return anchor;
      }

      static render(target, text, options = {}) {
        target.replaceChildren();
        const source = String(text || "");
        const bindings = options.bindings || {};
        const showFavicons = options.showFavicons !== false;
        const events = [];

        for (const entity of EntityParser.parse(source)) events.push({ kind: "entity", ...entity });
        ModerationPolicy.redactionMarker.lastIndex = 0;
        let marker;
        while ((marker = ModerationPolicy.redactionMarker.exec(source))) {
          events.push({ kind: "redaction", start: marker.index, end: marker.index + marker[0].length, length: Number(marker[1]), category: marker[2] });
        }
        ModerationPolicy.redactionMarker.lastIndex = 0;
        events.sort((a, b) => a.start - b.start || a.end - b.end);

        let cursor = 0;
        for (const event of events) {
          if (event.start < cursor) continue;
          this.appendPlain(target, source.slice(cursor, event.start));
          if (event.kind === "redaction") {
            const span = document.createElement("span");
            span.className = "ink-redaction";
            span.style.setProperty("--chars", String(event.length));
            span.setAttribute("aria-label", "Moderated expression");
            target.append(span);
          } else {
            const binding = event.type === "mention" ? bindings[event.handle] : null;
            const anchor = this.createAnchor(event, binding, showFavicons);
            if (anchor) target.append(anchor);
            else {
              const span = document.createElement("span");
              span.className = event.type === "mention" ? "ink-handle" : "";
              span.textContent = event.raw;
              target.append(span);
            }
            if (event.tail) target.append(document.createTextNode(event.tail));
          }
          cursor = event.end + (event.tail?.length || 0);
        }
        this.appendPlain(target, source.slice(cursor));
      }

      static destinationList(text, bindings, showFavicons) {
        const list = [];
        const seen = new Set();
        for (const entity of EntityParser.parse(text)) {
          const binding = entity.type === "mention" ? bindings?.[entity.handle] : null;
          const href = entity.type === "mention" ? this.mentionHref(entity.handle, binding) : entity.href;
          if (!href || seen.has(`${entity.type}:${href}`)) continue;
          seen.add(`${entity.type}:${href}`);
          list.push({ entity, binding, href, showFavicons });
        }
        return list;
      }
    }

    class MessageRepository {
      constructor() { this.local = !AppConfig.apiBase; }

      normalizeReactions(reactions) {
        if (!Array.isArray(reactions)) return [];
        return reactions
          .map(item => ({ emoji: String(item?.emoji || ""), count: Math.max(0, Number(item?.count) || 0), reacted: Boolean(item?.reacted) }))
          .filter(item => item.emoji && item.count > 0);
      }

      normalize(record) {
        const date = new Date(record?.createdAt || Date.now());
        const createdAt = Number.isNaN(date.getTime()) ? new Date().toISOString() : date.toISOString();
        const prepared = Boolean(record?.prepared || record?.id === AppConfig.preparedInk.id);
        return {
          id: String(record?.id || crypto.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`),
          name: String(record?.name || "Anonymous").slice(0, AppConfig.limits.name),
          message: String(record?.message || "No note."),
          image: this.safeImage(record?.image),
          bindings: new BindingStore().sanitize(record?.bindings),
          showFavicons: record?.showFavicons !== false,
          reportable: prepared ? false : record?.reportable !== false,
          prepared,
          reactions: this.normalizeReactions(record?.reactions),
          createdAt
        };
      }

      safeImage(image) {
        if (!image || typeof image !== "object") return null;
        const src = String(image.src || "");
        if (!/^data:image\/webp;base64,[A-Za-z0-9+/=]+$/.test(src) && !/^\/inkwall\/media\.php\?id=[a-f0-9-]{20,40}$/i.test(src)) return null;
        return {
          src,
          width: Number(image.width) || 0,
          height: Number(image.height) || 0,
          bytes: Number(image.bytes) || 0,
          name: String(image.name || "image.webp").slice(0, 96),
          inverted: Boolean(image.inverted),
          signature: String(image.signature || "")
        };
      }

      preparedMessage() {
        return this.normalize(AppConfig.preparedInk);
      }

      includePreparedMessage(messages) {
        const normalized = Array.isArray(messages) ? messages.map(item => this.normalize(item)) : [];
        if (!normalized.some(item => item.id === AppConfig.preparedInk.id)) normalized.push(this.preparedMessage());
        return normalized.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
      }

      loadLocal() {
        try {
          const parsed = JSON.parse(localStorage.getItem(AppConfig.storageKey) || "[]");
          return this.includePreparedMessage(Array.isArray(parsed) ? parsed : []);
        } catch { return this.includePreparedMessage([]); }
      }

      saveLocal(messages) {
        const visitorMessages = messages.filter(message => !message.prepared && message.id !== AppConfig.preparedInk.id);
        try { localStorage.setItem(AppConfig.storageKey, JSON.stringify(visitorMessages.slice(0, AppConfig.archiveLocalLimit))); }
        catch { /* Storage can be unavailable in private contexts. */ }
      }

      async list() {
        if (this.local) return this.loadLocal();
        const response = await fetch(`${AppConfig.apiBase}/messages`, { headers: { Accept: "application/json" }, credentials: "same-origin" });
        if (!response.ok) throw new Error("The live archive could not be loaded.");
        const payload = await response.json();
        const records = Array.isArray(payload) ? payload : payload.messages;
        return this.includePreparedMessage(Array.isArray(records) ? records : []);
      }

      async publish(record, currentMessages) {
        const normalized = this.normalize({ ...record, prepared: false, reportable: true });
        if (this.local) {
          const next = [normalized, ...currentMessages.filter(item => item.id !== normalized.id)];
          this.saveLocal(next);
          return normalized;
        }
        const response = await fetch(`${AppConfig.apiBase}/messages`, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          credentials: "same-origin",
          body: JSON.stringify(normalized)
        });
        if (!response.ok) throw new Error("The note could not be published.");
        return this.normalize(await response.json());
      }
    }

    class EInkDisplay {
      constructor() { this.currentPayload = null; this.refreshing = false; }

      formatDate(date) {
        return new Intl.DateTimeFormat("en-GB", {
          day: "2-digit", month: "2-digit", year: "numeric",
          hour: "2-digit", minute: "2-digit", second: "2-digit", hour12: false
        }).format(date).replace(",", "");
      }

      snapshotGhost() { Dom.displayGhost.innerHTML = Dom.displayContent.innerHTML; }

      set(payload) {
        this.currentPayload = payload;
        Dom.displayMode.textContent = payload.mode === "draft" ? "Draft preview" : payload.mode === "archive" ? "Archive note" : "Latest public note";
        Dom.displayScope.textContent = payload.mode === "draft" ? "Not published" : "Public surface";
        Dom.displayDate.dateTime = payload.date.toISOString();
        Dom.displayDate.textContent = this.formatDate(payload.date);
        Dom.displayName.textContent = payload.name || "Anonymous";
        LinkRenderer.render(Dom.displayMessage, payload.message, { bindings: payload.bindings, showFavicons: payload.showFavicons });
        Dom.displayContent.classList.toggle("has-image", Boolean(payload.image));
        Dom.displayMedia.hidden = !payload.image;
        if (payload.image) {
          Dom.displayImage.src = payload.image.src;
          Dom.displayImage.alt = `E-Ink image submitted by ${payload.name || "Anonymous"}`;
        } else {
          Dom.displayImage.removeAttribute("src");
          Dom.displayImage.alt = "";
        }
      }

      async refresh(payload, { revealOnMobile = false } = {}) {
        if (this.refreshing) return false;
        this.refreshing = true;
        this.snapshotGhost();
        Dom.deviceState.textContent = "Refreshing";
        Dom.display.classList.remove("is-refreshing");
        void Dom.display.offsetWidth;
        Dom.display.classList.add("is-refreshing");
        window.setTimeout(() => this.set(payload), AppConfig.refreshSwapDelay);
        await new Promise(resolve => window.setTimeout(resolve, AppConfig.refreshDuration));
        Dom.display.classList.remove("is-refreshing");
        Dom.deviceState.textContent = "Live surface";
        this.refreshing = false;
        if (revealOnMobile && matchMedia("(max-width: 680px)").matches) {
          Dom.display.scrollIntoView({ behavior: matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth", block: "center" });
        }
        return true;
      }

      async refreshTheme(theme) {
        if (this.refreshing) return;
        this.refreshing = true;
        Dom.display.classList.remove("is-theme-refreshing");
        void Dom.display.offsetWidth;
        Dom.display.classList.add("is-theme-refreshing");
        window.setTimeout(() => {
          Dom.html.dataset.theme = theme;
          Dom.themeLabel.textContent = theme === "dark" ? "Light mode" : "Dark mode";
          Dom.themeToggle.setAttribute("aria-label", theme === "dark" ? "Switch to light mode" : "Switch to dark mode");
          try { localStorage.setItem(AppConfig.themeKey, theme); } catch { /* Ignore unavailable storage. */ }
        }, AppConfig.themeSwapDelay);
        await new Promise(resolve => window.setTimeout(resolve, AppConfig.themeDuration));
        Dom.display.classList.remove("is-theme-refreshing");
        this.refreshing = false;
      }
    }

    class ImageWorkbench {
      constructor(onStateChange, onReady) {
        this.onStateChange = onStateChange;
        this.onReady = onReady;
        this.source = null;
        this.output = null;
        this.crop = { x: 50, y: 50, zoom: 1, invert: false };
        this.drag = null;
        this.job = 0;
        this.timer = null;
        this.processing = false;
        this.bindEvents();
      }

      bindEvents() {
        Dom.imageInput.addEventListener("change", () => this.selectFile(Dom.imageInput.files?.[0]));
        Dom.removeImageButton.addEventListener("click", () => this.remove());
        Dom.imageZoom.addEventListener("input", () => this.zoom());
        Dom.imageInvertButton.addEventListener("click", () => this.invert());
        Dom.cropStage.addEventListener("pointerdown", event => this.beginDrag(event));
        Dom.cropStage.addEventListener("pointermove", event => this.moveDrag(event));
        Dom.cropStage.addEventListener("pointerup", event => this.endDrag(event));
        Dom.cropStage.addEventListener("pointercancel", event => this.endDrag(event));
      }

      setProgress(percent, label) {
        Dom.imageProgress.hidden = false;
        Dom.imageProgressLabel.textContent = label;
        Dom.imageProgressValue.textContent = `${Math.round(percent)}%`;
        Dom.imageProgressFill.style.width = `${Math.max(0, Math.min(100, percent))}%`;
      }

      sourceSize(element) {
        return { width: element.naturalWidth || element.width || 0, height: element.naturalHeight || element.height || 0 };
      }

      async decode(file) {
        if (typeof createImageBitmap === "function") return createImageBitmap(file, { imageOrientation: "from-image" });
        const url = URL.createObjectURL(file);
        try {
          const image = new Image();
          image.decoding = "async";
          image.src = url;
          await image.decode();
          return image;
        } finally { URL.revokeObjectURL(url); }
      }

      clearSource() {
        if (this.source?.element && typeof this.source.element.close === "function") this.source.element.close();
        this.source = null;
      }

      drawCrop(canvas, element) {
        const { width: sourceWidth, height: sourceHeight } = this.sourceSize(element);
        const context = canvas.getContext("2d", { willReadFrequently: true, alpha: false });
        context.fillStyle = "#ecebe2";
        context.fillRect(0, 0, canvas.width, canvas.height);
        if (!sourceWidth || !sourceHeight) return;
        const scale = Math.max(canvas.width / sourceWidth, canvas.height / sourceHeight) * this.crop.zoom;
        const drawWidth = sourceWidth * scale;
        const drawHeight = sourceHeight * scale;
        const overflowX = Math.max(0, drawWidth - canvas.width);
        const overflowY = Math.max(0, drawHeight - canvas.height);
        const drawX = -overflowX * (this.crop.x / 100);
        const drawY = -overflowY * (this.crop.y / 100);
        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = "high";
        context.drawImage(element, drawX, drawY, drawWidth, drawHeight);
      }

      dither(canvas) {
        const context = canvas.getContext("2d", { willReadFrequently: true, alpha: false });
        const frame = context.getImageData(0, 0, canvas.width, canvas.height);
        const levels = [34, 102, 170, 232];
        const matrix = [[0, 8, 2, 10], [12, 4, 14, 6], [3, 11, 1, 9], [15, 7, 13, 5]];
        for (let y = 0; y < canvas.height; y += 1) {
          for (let x = 0; x < canvas.width; x += 1) {
            const offset = (y * canvas.width + x) * 4;
            const luminance = .2126 * frame.data[offset] + .7152 * frame.data[offset + 1] + .0722 * frame.data[offset + 2];
            const base = this.crop.invert ? 255 - luminance : luminance;
            const adjusted = Math.max(0, Math.min(255, (base - 128) * 1.09 + 128 + (matrix[y & 3][x & 3] - 7.5) * 7.2));
            let selected = levels[0];
            let distance = Infinity;
            for (const level of levels) {
              const currentDistance = Math.abs(adjusted - level);
              if (currentDistance < distance) { distance = currentDistance; selected = level; }
            }
            frame.data[offset] = frame.data[offset + 1] = frame.data[offset + 2] = selected;
            frame.data[offset + 3] = 255;
          }
        }
        context.putImageData(frame, 0, 0);
      }

      renderPreview() {
        if (!this.source) return;
        this.drawCrop(Dom.cropCanvas, this.source.element);
        this.dither(Dom.cropCanvas);
      }

      canvasBlob(canvas, quality) {
        return new Promise((resolve, reject) => canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error("Image encoding failed.")), "image/webp", quality));
      }

      blobDataUrl(blob) {
        return new Promise((resolve, reject) => {
          const reader = new FileReader();
          reader.onload = () => resolve(String(reader.result));
          reader.onerror = () => reject(new Error("Image reading failed."));
          reader.readAsDataURL(blob);
        });
      }

      schedule(delay = 190) {
        clearTimeout(this.timer);
        this.processing = true;
        const job = ++this.job;
        this.setProgress(9, "Frame changed");
        this.onStateChange();
        this.timer = window.setTimeout(() => this.build(job), delay);
      }

      async build(job) {
        if (!this.source || job !== this.job) return;
        this.processing = true;
        this.setProgress(16, "Reading visible frame");
        this.onStateChange();
        await new Promise(requestAnimationFrame);
        let result = null;
        for (const [index, width] of [1120, 960, 800].entries()) {
          if (job !== this.job) return;
          this.setProgress(31 + index * 17, "Mapping four ink tones");
          await new Promise(requestAnimationFrame);
          const canvas = document.createElement("canvas");
          canvas.width = width;
          canvas.height = Math.round(width * 9 / 16);
          this.drawCrop(canvas, this.source.element);
          this.dither(canvas);
          this.setProgress(68 + index * 8, "Encoding WebP");
          const blob = await this.canvasBlob(canvas, .78);
          result = { blob, width: canvas.width, height: canvas.height };
          if (blob.size <= AppConfig.limits.imageOutputBytes || width === 800) break;
        }
        if (!result || job !== this.job) return;
        this.setProgress(95, "Removing metadata");
        const src = await this.blobDataUrl(result.blob);
        if (job !== this.job) return;
        const cleanName = this.source.name.replace(/\.[^.]+$/, "").slice(0, 90) + ".webp";
        this.output = {
          src,
          width: result.width,
          height: result.height,
          bytes: result.blob.size,
          name: cleanName,
          inverted: this.crop.invert,
          signature: `${this.source.signature}:${this.crop.x.toFixed(2)}:${this.crop.y.toFixed(2)}:${this.crop.zoom.toFixed(2)}:${this.crop.invert ? 1 : 0}:${result.blob.size}`
        };
        Dom.imageMeta.textContent = `${cleanName} · ${Math.max(1, Math.round(result.blob.size / 1024))} KB · 16:9`;
        Dom.imageEditorState.textContent = "Drag to reposition";
        this.processing = false;
        this.setProgress(100, "E-Ink image ready");
        this.onStateChange();
        this.onReady();
      }

      async selectFile(file) {
        if (!file) return;
        if (!/^image\/(jpeg|png|webp|avif)$/i.test(file.type)) {
          this.onStateChange("Use a JPEG, PNG, WebP or AVIF image.", "danger");
          return;
        }
        if (file.size > AppConfig.limits.imageInputBytes) {
          this.onStateChange("The image is too large. Maximum size is 12 MB.", "danger");
          return;
        }
        this.job += 1;
        this.processing = true;
        this.output = null;
        this.clearSource();
        Dom.imageEditor.hidden = true;
        Dom.removeImageButton.hidden = true;
        Dom.imageMeta.textContent = "Reading image";
        this.setProgress(4, "Reading image");
        this.onStateChange();
        try {
          const element = await this.decode(file);
          const size = this.sourceSize(element);
          if (!size.width || !size.height) throw new Error("Image dimensions could not be read.");
          this.source = { element, name: file.name, signature: `${file.name}:${file.size}:${file.lastModified}:${size.width}x${size.height}` };
          this.crop = { x: 50, y: 50, zoom: 1, invert: false };
          Dom.imageZoom.value = "100";
          Dom.imageZoomValue.textContent = "100%";
          Dom.imageInvertButton.setAttribute("aria-pressed", "false");
          Dom.imageEditor.hidden = false;
          Dom.removeImageButton.hidden = false;
          Dom.imageMeta.textContent = `${file.name} · choose the frame`;
          this.renderPreview();
          this.schedule(20);
        } catch (error) {
          this.processing = false;
          this.output = null;
          this.clearSource();
          Dom.imageInput.value = "";
          Dom.imageMeta.textContent = "No image selected";
          Dom.imageProgress.hidden = true;
          this.onStateChange(error?.message || "The image could not be processed.", "danger");
        }
      }

      remove() {
        clearTimeout(this.timer);
        this.job += 1;
        this.processing = false;
        this.output = null;
        this.clearSource();
        Dom.imageInput.value = "";
        Dom.imageMeta.textContent = "No image selected";
        Dom.removeImageButton.hidden = true;
        Dom.imageEditor.hidden = true;
        Dom.imageProgress.hidden = true;
        this.onStateChange();
        this.onReady();
      }

      zoom() {
        this.crop.zoom = Number(Dom.imageZoom.value) / 100;
        Dom.imageZoomValue.textContent = `${Dom.imageZoom.value}%`;
        Dom.imageEditorState.textContent = "Updating frame";
        this.renderPreview();
        this.schedule();
      }

      invert() {
        if (!this.source) return;
        this.crop.invert = !this.crop.invert;
        Dom.imageInvertButton.setAttribute("aria-pressed", String(this.crop.invert));
        Dom.imageEditorState.textContent = this.crop.invert ? "Inverted pigments" : "Normal pigments";
        this.renderPreview();
        this.schedule(70);
      }

      beginDrag(event) {
        if (!this.source) return;
        Dom.cropStage.setPointerCapture?.(event.pointerId);
        this.drag = { pointerId: event.pointerId, startX: event.clientX, startY: event.clientY, x: this.crop.x, y: this.crop.y };
        Dom.imageEditorState.textContent = "Positioning";
      }

      moveDrag(event) {
        if (!this.drag || event.pointerId !== this.drag.pointerId) return;
        const rect = Dom.cropStage.getBoundingClientRect();
        this.crop.x = Math.max(0, Math.min(100, this.drag.x - (event.clientX - this.drag.startX) / Math.max(rect.width, 1) * 125));
        this.crop.y = Math.max(0, Math.min(100, this.drag.y - (event.clientY - this.drag.startY) / Math.max(rect.height, 1) * 125));
        this.renderPreview();
      }

      endDrag(event) {
        if (!this.drag || event.pointerId !== this.drag.pointerId) return;
        this.drag = null;
        Dom.imageEditorState.textContent = "Updating frame";
        this.schedule(40);
      }
    }

    class ImageDropController {
      constructor(onImageSelected) {
        this.onImageSelected = onImageSelected;
        this.dragDepth = 0;
        this.bindEvents();
      }

      bindEvents() {
        document.addEventListener("dragenter", event => this.handleDragEnter(event));
        document.addEventListener("dragover", event => this.handleDragOver(event));
        document.addEventListener("dragleave", event => this.handleDragLeave(event));
        document.addEventListener("dragend", () => this.hide());
        document.addEventListener("drop", event => this.handleDrop(event));
        window.addEventListener("blur", () => this.hide());
      }

      containsFiles(event) {
        return Array.from(event.dataTransfer?.types || []).includes("Files");
      }

      handleDragEnter(event) {
        if (!this.containsFiles(event)) return;
        event.preventDefault();
        this.dragDepth += 1;
        this.show();
      }

      handleDragOver(event) {
        if (!this.containsFiles(event)) return;
        event.preventDefault();
        if (event.dataTransfer) event.dataTransfer.dropEffect = "copy";
        this.show();
      }

      handleDragLeave() {
        if (!this.dragDepth) return;
        this.dragDepth = Math.max(0, this.dragDepth - 1);
        if (!this.dragDepth) this.hide();
      }

      handleDrop(event) {
        if (!this.containsFiles(event)) return;
        event.preventDefault();
        const file = Array.from(event.dataTransfer?.files || []).find(candidate => candidate.type.startsWith("image/"));
        this.hide();
        if (file) this.onImageSelected(file);
      }

      positionPopover() {
        const fieldBounds = Dom.imageField.getBoundingClientRect();
        const fieldIsVisible = fieldBounds.bottom > 0 && fieldBounds.top < window.innerHeight;
        const horizontalCenter = fieldIsVisible
          ? fieldBounds.left + fieldBounds.width / 2
          : window.innerWidth / 2;
        const verticalCenter = fieldIsVisible
          ? fieldBounds.top + Math.min(fieldBounds.height, 150) / 2
          : window.innerHeight / 2;
        const clampedLeft = Math.max(190, Math.min(window.innerWidth - 190, horizontalCenter));
        const clampedTop = Math.max(92, Math.min(window.innerHeight - 92, verticalCenter));
        Dom.imageDropPopover.style.left = `${clampedLeft}px`;
        Dom.imageDropPopover.style.top = `${clampedTop}px`;
      }

      show() {
        this.positionPopover();
        Dom.imageField.classList.add("is-drop-target");
        Dom.imageDropPopover.classList.add("is-visible");
        Dom.imageDropPopover.setAttribute("aria-hidden", "false");
      }

      hide() {
        this.dragDepth = 0;
        Dom.imageField.classList.remove("is-drop-target");
        Dom.imageDropPopover.classList.remove("is-visible");
        Dom.imageDropPopover.setAttribute("aria-hidden", "true");
      }
    }

    class EntityPickerController {
      constructor(bindingStore, onChange) {
        this.bindingStore = bindingStore;
        this.onChange = onChange;
        this.activeHandle = null;
        this.anchor = null;
        this.closeTimer = null;
        this.buildChoices();
        this.bindEvents();
      }

      buildChoices() {
        Dom.entityPickerChoices.replaceChildren();
        for (const [key, platform] of Object.entries(PlatformCatalog)) {
          const button = document.createElement("button");
          button.type = "button";
          button.className = "entity-choice";
          button.dataset.platform = key;
          button.append(IconRegistry.create(platform.icon));
          const label = document.createElement("span");
          label.textContent = platform.label;
          button.append(label);
          Dom.entityPickerChoices.append(button);
        }
      }

      bindEvents() {
        Dom.entityPickerChoices.addEventListener("click", event => {
          const button = event.target.closest("button[data-platform]");
          if (button) this.choose(button.dataset.platform);
        });
        Dom.entityPickerClose.addEventListener("click", () => this.close());
        Dom.entityCustomSave.addEventListener("click", () => this.saveCustom());
        Dom.entityCustomUrl.addEventListener("keydown", event => {
          if (event.key === "Enter") { event.preventDefault(); this.saveCustom(); }
        });
        Dom.entityPicker.addEventListener("mouseenter", () => this.cancelClose());
        Dom.entityPicker.addEventListener("mouseleave", () => this.scheduleClose());
        window.addEventListener("resize", () => this.position());
        window.addEventListener("scroll", () => this.position(), { passive: true });
      }

      open(handle, anchor) {
        this.cancelClose();
        this.activeHandle = handle;
        this.anchor = anchor;
        Dom.entityPickerHandle.textContent = `@${handle}`;
        const binding = this.bindingStore.get(handle);
        for (const button of Dom.entityPickerChoices.querySelectorAll("button[data-platform]")) {
          button.classList.toggle("is-active", button.dataset.platform === binding?.platform);
        }
        Dom.entityPickerCustom.hidden = binding?.platform !== "custom";
        Dom.entityCustomUrl.value = binding?.url || "";
        Dom.entityPicker.classList.add("is-open");
        this.position();
      }

      position() {
        if (!this.anchor || !Dom.entityPicker.classList.contains("is-open") || matchMedia("(max-width: 680px)").matches) return;
        const rect = this.anchor.getBoundingClientRect();
        const pickerRect = Dom.entityPicker.getBoundingClientRect();
        const left = Math.max(14, Math.min(window.innerWidth - pickerRect.width - 14, rect.left));
        const preferredTop = rect.bottom + 8;
        const top = preferredTop + pickerRect.height <= window.innerHeight - 14
          ? preferredTop
          : Math.max(14, rect.top - pickerRect.height - 8);
        Dom.entityPicker.style.left = `${left}px`;
        Dom.entityPicker.style.top = `${top}px`;
      }

      choose(platform) {
        if (!this.activeHandle || !PlatformCatalog[platform]) return;
        if (platform === "custom") {
          Dom.entityPickerCustom.hidden = false;
          Dom.entityCustomUrl.focus();
          return;
        }
        this.bindingStore.set(this.activeHandle, { platform, url: "" });
        this.onChange();
        this.close();
      }

      saveCustom() {
        if (!this.activeHandle) return;
        try {
          const parsed = new URL(Dom.entityCustomUrl.value.trim());
          if (parsed.protocol !== "https:") throw new Error();
          this.bindingStore.set(this.activeHandle, { platform: "custom", url: parsed.href });
          this.onChange();
          this.close();
        } catch { Dom.entityCustomUrl.setCustomValidity("Use a complete HTTPS URL."); Dom.entityCustomUrl.reportValidity(); Dom.entityCustomUrl.setCustomValidity(""); }
      }

      scheduleClose() { clearTimeout(this.closeTimer); this.closeTimer = window.setTimeout(() => this.close(), 220); }
      cancelClose() { clearTimeout(this.closeTimer); }
      close() { this.cancelClose(); Dom.entityPicker.classList.remove("is-open"); this.activeHandle = null; this.anchor = null; }
    }


    const ReportReasonCatalog = Object.freeze({
      spam: Object.freeze({ label: "Spam or manipulation", threshold: 2, priority: false }),
      harassment: Object.freeze({ label: "Harassment or abuse", threshold: 2, priority: false }),
      hate: Object.freeze({ label: "Hate or dehumanization", threshold: 1, priority: true }),
      threat: Object.freeze({ label: "Threat or immediate danger", threshold: 1, priority: true }),
      privacy: Object.freeze({ label: "Personal or private information", threshold: 1, priority: true }),
      other: Object.freeze({ label: "Something else", threshold: 2, priority: false })
    });

    class ReportRepository {
      constructor() {
        this.local = !AppConfig.apiBase;
        this.reporterId = this.resolveReporterId();
      }

      resolveReporterId() {
        try {
          const existing = localStorage.getItem(AppConfig.reporterStorageKey);
          if (existing) return existing;
          const created = crypto.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`;
          localStorage.setItem(AppConfig.reporterStorageKey, created);
          return created;
        } catch {
          return `session-${Date.now()}-${Math.random().toString(16).slice(2)}`;
        }
      }

      loadLocalState() {
        try {
          const parsed = JSON.parse(localStorage.getItem(AppConfig.reportStorageKey) || "{}");
          return parsed && typeof parsed === "object" ? parsed : {};
        } catch {
          return {};
        }
      }

      saveLocalState(state) {
        try { localStorage.setItem(AppConfig.reportStorageKey, JSON.stringify(state)); }
        catch { /* Reporting still works for the current session when storage is unavailable. */ }
      }

      hiddenMessageIds() {
        const state = this.loadLocalState();
        return new Set(Object.entries(state).filter(([, value]) => value?.hidden === true).map(([messageId]) => messageId));
      }

      async submit(messageId, reason, detail) {
        if (messageId === AppConfig.preparedInk.id) throw new Error("The prepared owner ink is not reportable.");
        const policy = ReportReasonCatalog[reason];
        if (!policy) throw new Error("Choose a valid report reason.");
        const normalizedDetail = String(detail || "").trim().slice(0, 240);

        if (!this.local) {
          const response = await fetch(`${AppConfig.apiBase}/messages/${encodeURIComponent(messageId)}/reports`, {
            method: "POST",
            headers: { "Content-Type": "application/json", "Accept": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ reason, detail: normalizedDetail })
          });
          if (!response.ok) throw new Error("The report could not be submitted.");
          const result = await response.json();
          return { accepted: result.accepted !== false, duplicate: Boolean(result.duplicate), hidden: Boolean(result.hidden), reason };
        }

        const state = this.loadLocalState();
        const note = state[messageId] && typeof state[messageId] === "object"
          ? state[messageId]
          : { hidden: false, reports: [] };
        note.reports = Array.isArray(note.reports) ? note.reports : [];

        if (note.reports.some(report => report.reporterId === this.reporterId)) {
          return { accepted: false, duplicate: true, hidden: Boolean(note.hidden), reason };
        }

        note.reports.push({
          reporterId: this.reporterId,
          reason,
          detail: normalizedDetail,
          createdAt: new Date().toISOString()
        });
        const reasonCount = note.reports.filter(report => report.reason === reason).length;
        note.hidden = Boolean(note.hidden || policy.priority || reasonCount >= policy.threshold);
        state[messageId] = note;
        this.saveLocalState(state);
        return { accepted: true, duplicate: false, hidden: note.hidden, reason, count: reasonCount };
      }
    }

    class ReportPopoverController {
      constructor(repository, onResult) {
        this.repository = repository;
        this.onResult = onResult;
        this.activeMessage = null;
        this.anchor = null;
        this.submitting = false;
        this.bindEvents();
      }

      bindEvents() {
        Dom.reportCloseButton.addEventListener("click", () => this.close());
        Dom.reportCancelButton.addEventListener("click", () => this.close());
        Dom.reportDetail.addEventListener("input", () => {
          Dom.reportDetailCount.textContent = String(Array.from(Dom.reportDetail.value).length);
        });
        Dom.reportForm.addEventListener("submit", event => {
          event.preventDefault();
          this.submit();
        });
        document.addEventListener("pointerdown", event => {
          if (!Dom.reportPopover.classList.contains("is-open")) return;
          if (Dom.reportPopover.contains(event.target) || this.anchor?.contains(event.target)) return;
          this.close();
        });
        document.addEventListener("keydown", event => {
          if (event.key === "Escape" && Dom.reportPopover.classList.contains("is-open")) this.close();
        });
        window.addEventListener("resize", () => this.position(), { passive: true });
        window.addEventListener("scroll", () => this.position(), { passive: true, capture: true });
      }

      open(message, anchor) {
        this.close(false, false);
        this.activeMessage = message;
        this.dirty = false;
        this.anchor = anchor;
        Dom.reportForm.reset();
        Dom.reportDetail.value = "";
        Dom.reportDetailCount.textContent = "0";
        Dom.reportStatus.textContent = "";
        Dom.reportSubmitButton.disabled = false;
        Dom.reportNoteReference.textContent = `Note ID ${message.id} · ${new Date(message.createdAt).toLocaleString("en-GB", { dateStyle: "medium", timeStyle: "medium" })}`;
        Dom.reportPopover.dataset.noteId = message.id;
        anchor.setAttribute("aria-expanded", "true");
        Dom.reportPopover.classList.add("is-open");
        Dom.reportPopover.setAttribute("aria-hidden", "false");
        this.position();
        Dom.reportPopover.querySelector('input[name="reportReason"]')?.focus({ preventScroll: true });
      }

      position() {
        if (!this.anchor || !Dom.reportPopover.classList.contains("is-open") || matchMedia("(max-width: 680px)").matches) return;
        const viewportGap = 14;
        const anchorGap = 12;
        const anchorRect = this.anchor.getBoundingClientRect();
        const popoverRect = Dom.reportPopover.getBoundingClientRect();
        const preferredLeft = anchorRect.right - popoverRect.width;
        const left = Math.max(viewportGap, Math.min(window.innerWidth - popoverRect.width - viewportGap, preferredLeft));
        const fitsBelow = anchorRect.bottom + anchorGap + popoverRect.height <= window.innerHeight - viewportGap;
        const placement = fitsBelow ? "bottom" : "top";
        const top = fitsBelow
          ? anchorRect.bottom + anchorGap
          : Math.max(viewportGap, anchorRect.top - popoverRect.height - anchorGap);
        const anchorCenter = anchorRect.left + anchorRect.width / 2;
        const arrowX = Math.max(18, Math.min(popoverRect.width - 18, anchorCenter - left));
        Dom.reportPopover.dataset.placement = placement;
        Dom.reportPopover.style.setProperty("--report-arrow-x", `${arrowX}px`);
        Dom.reportPopover.style.left = `${left}px`;
        Dom.reportPopover.style.top = `${top}px`;
      }

      async submit() {
        if (!this.activeMessage || this.submitting) return;
        const selected = Dom.reportForm.querySelector('input[name="reportReason"]:checked');
        if (!selected) {
          Dom.reportStatus.textContent = "Choose the reason that best describes the concern.";
          return;
        }

        this.submitting = true;
        Dom.reportSubmitButton.disabled = true;
        Dom.reportStatus.textContent = "Submitting report.";
        try {
          const result = await this.repository.submit(this.activeMessage.id, selected.value, Dom.reportDetail.value);
          await this.onResult(this.activeMessage, result);
          this.close();
        } catch (error) {
          Dom.reportStatus.textContent = error?.message || "The report could not be submitted.";
          Dom.reportSubmitButton.disabled = false;
        } finally {
          this.submitting = false;
        }
      }

      close(resetAnchor = true) {
        if (resetAnchor && this.anchor) this.anchor.setAttribute("aria-expanded", "false");
        Dom.reportPopover.classList.remove("is-open");
        Dom.reportPopover.setAttribute("aria-hidden", "true");
        Dom.reportPopover.style.removeProperty("left");
        Dom.reportPopover.style.removeProperty("top");
        Dom.reportPopover.style.removeProperty("--report-arrow-x");
        Dom.reportPopover.removeAttribute("data-placement");
        Dom.reportPopover.removeAttribute("data-note-id");
        this.activeMessage = null;
        this.anchor = null;
      }
    }


    const ReactionCatalog = Object.freeze([
      Object.freeze({ emoji: "❤", label: "Love" }),
      Object.freeze({ emoji: "🔥", label: "Fire" }),
      Object.freeze({ emoji: "👏", label: "Applause" }),
      Object.freeze({ emoji: "💡", label: "Insightful" }),
      Object.freeze({ emoji: "😂", label: "Funny" }),
      Object.freeze({ emoji: "🤝", label: "Support" }),
      Object.freeze({ emoji: "👀", label: "Watching" }),
      Object.freeze({ emoji: "🚀", label: "Launch" })
    ]);

    class ReactionRepository {
      constructor() {
        this.local = !AppConfig.apiBase;
        this.reactorId = this.resolveReactorId();
      }

      resolveReactorId() {
        try {
          const existing = localStorage.getItem(AppConfig.reactorStorageKey);
          if (existing) return existing;
          const created = crypto.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`;
          localStorage.setItem(AppConfig.reactorStorageKey, created);
          return created;
        } catch {
          return `session-${Date.now()}-${Math.random().toString(16).slice(2)}`;
        }
      }

      loadLocalState() {
        try {
          const parsed = JSON.parse(localStorage.getItem(AppConfig.reactionStorageKey) || "{}");
          return parsed && typeof parsed === "object" ? parsed : {};
        } catch { return {}; }
      }

      saveLocalState(state) {
        try { localStorage.setItem(AppConfig.reactionStorageKey, JSON.stringify(state)); }
        catch { /* Reactions remain available for the current session when storage is unavailable. */ }
      }

      normalizeSummary(summary) {
        if (!Array.isArray(summary)) return [];
        return summary
          .map(item => ({ emoji: String(item?.emoji || ""), count: Math.max(0, Number(item?.count) || 0), reacted: Boolean(item?.reacted) }))
          .filter(item => ReactionCatalog.some(option => option.emoji === item.emoji) && item.count > 0)
          .sort((a, b) => b.count - a.count || ReactionCatalog.findIndex(option => option.emoji === a.emoji) - ReactionCatalog.findIndex(option => option.emoji === b.emoji));
      }

      summary(message) {
        if (!this.local) return this.normalizeSummary(message.reactions);
        const state = this.loadLocalState();
        const note = state[message.id] && typeof state[message.id] === "object" ? state[message.id] : {};
        return ReactionCatalog.map(option => {
          const reactors = Array.isArray(note[option.emoji]) ? note[option.emoji] : [];
          return { emoji: option.emoji, count: reactors.length, reacted: reactors.includes(this.reactorId) };
        }).filter(item => item.count > 0).sort((a, b) => b.count - a.count || ReactionCatalog.findIndex(option => option.emoji === a.emoji) - ReactionCatalog.findIndex(option => option.emoji === b.emoji));
      }

      async toggle(message, emoji) {
        if (!ReactionCatalog.some(option => option.emoji === emoji)) throw new Error("Choose a supported reaction.");
        if (!this.local) {
          const response = await fetch(`${AppConfig.apiBase}/messages/${encodeURIComponent(message.id)}/reactions`, {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ emoji })
          });
          if (!response.ok) throw new Error("The reaction could not be saved.");
          const payload = await response.json();
          return this.normalizeSummary(payload.reactions || payload);
        }

        const state = this.loadLocalState();
        const note = state[message.id] && typeof state[message.id] === "object" ? state[message.id] : {};
        const reactors = Array.isArray(note[emoji]) ? note[emoji] : [];
        const existingIndex = reactors.indexOf(this.reactorId);
        if (existingIndex >= 0) reactors.splice(existingIndex, 1);
        else reactors.push(this.reactorId);
        if (reactors.length) note[emoji] = reactors;
        else delete note[emoji];
        state[message.id] = note;
        this.saveLocalState(state);
        return this.summary(message);
      }
    }

    class ReactionPopoverController {
      constructor(repository, onToggle, onClose) {
        this.repository = repository;
        this.onToggle = onToggle;
        this.onClose = onClose;
        this.activeMessage = null;
        this.anchor = null;
        this.busy = false;
        this.dirty = false;
        this.bindEvents();
      }

      bindEvents() {
        Dom.reactionCloseButton.addEventListener("click", () => this.close());
        document.addEventListener("pointerdown", event => {
          if (!Dom.reactionPopover.classList.contains("is-open")) return;
          if (Dom.reactionPopover.contains(event.target) || this.anchor?.contains(event.target)) return;
          this.close();
        });
        document.addEventListener("keydown", event => {
          if (event.key === "Escape" && Dom.reactionPopover.classList.contains("is-open")) this.close();
        });
        window.addEventListener("resize", () => this.position(), { passive: true });
        window.addEventListener("scroll", () => this.position(), { passive: true, capture: true });
      }

      open(message, anchor) {
        if (message.reportable === false || message.prepared) return;
        this.close(false);
        this.activeMessage = message;
        this.anchor = anchor;
        Dom.reactionNoteReference.textContent = `Note ID ${message.id}`;
        anchor.setAttribute("aria-expanded", "true");
        Dom.reactionPopover.classList.add("is-open");
        Dom.reactionPopover.setAttribute("aria-hidden", "false");
        this.render();
        this.position();
        Dom.reactionGrid.querySelector("button")?.focus({ preventScroll: true });
      }

      render() {
        if (!this.activeMessage) return;
        const summary = this.repository.summary(this.activeMessage);
        const byEmoji = new Map(summary.map(item => [item.emoji, item]));
        Dom.reactionGrid.replaceChildren();
        ReactionCatalog.forEach(option => {
          const current = byEmoji.get(option.emoji) || { count: 0, reacted: false };
          const button = document.createElement("button");
          button.type = "button";
          button.className = `reaction-choice${current.reacted ? " is-selected" : ""}`;
          button.setAttribute("aria-pressed", String(current.reacted));
          button.setAttribute("aria-label", `${option.label}, ${current.count} reaction${current.count === 1 ? "" : "s"}`);
          const emoji = document.createElement("span");
          emoji.className = "reaction-choice__emoji";
          emoji.textContent = option.emoji;
          const copy = document.createElement("span");
          copy.className = "reaction-choice__copy";
          const label = document.createElement("strong");
          label.textContent = option.label;
          const count = document.createElement("small");
          count.textContent = String(current.count);
          copy.append(label, count);
          button.append(emoji, copy);
          button.addEventListener("click", () => this.toggle(option.emoji));
          Dom.reactionGrid.append(button);
        });
      }

      async toggle(emoji) {
        if (!this.activeMessage || this.busy) return;
        this.busy = true;
        Dom.reactionGrid.classList.add("is-busy");
        try {
          const reactions = await this.onToggle(this.activeMessage, emoji);
          this.activeMessage.reactions = reactions;
          this.dirty = true;
          this.render();
          this.position();
        } finally {
          this.busy = false;
          Dom.reactionGrid.classList.remove("is-busy");
        }
      }

      position() {
        if (!this.anchor || !Dom.reactionPopover.classList.contains("is-open") || matchMedia("(max-width: 680px)").matches) return;
        const viewportGap = 14;
        const anchorGap = 10;
        const anchorRect = this.anchor.getBoundingClientRect();
        const popoverRect = Dom.reactionPopover.getBoundingClientRect();
        const left = Math.max(viewportGap, Math.min(window.innerWidth - popoverRect.width - viewportGap, anchorRect.left));
        const fitsBelow = anchorRect.bottom + anchorGap + popoverRect.height <= window.innerHeight - viewportGap;
        const top = fitsBelow ? anchorRect.bottom + anchorGap : Math.max(viewportGap, anchorRect.top - popoverRect.height - anchorGap);
        Dom.reactionPopover.dataset.placement = fitsBelow ? "bottom" : "top";
        Dom.reactionPopover.style.left = `${left}px`;
        Dom.reactionPopover.style.top = `${top}px`;
      }

      close(resetAnchor = true, notify = true) {
        const shouldRefresh = notify && this.dirty;
        this.dirty = false;
        if (resetAnchor && this.anchor) this.anchor.setAttribute("aria-expanded", "false");
        Dom.reactionPopover.classList.remove("is-open");
        Dom.reactionPopover.setAttribute("aria-hidden", "true");
        Dom.reactionPopover.style.removeProperty("left");
        Dom.reactionPopover.style.removeProperty("top");
        Dom.reactionPopover.removeAttribute("data-placement");
        this.activeMessage = null;
        this.anchor = null;
        if (shouldRefresh) this.onClose();
      }
    }

    class BannerPixelField {
      constructor(canvas) {
        this.canvas = canvas;
        this.context = canvas.getContext("2d", { alpha: true });
        this.pixelSize = 5;
        this.particles = [];
        this.animationFrame = null;
        this.previousFrameTime = 0;
        this.frameInterval = 1000 / 24;
        this.nextClusterAt = 0;
        this.reducedMotion = matchMedia("(prefers-reduced-motion: reduce)");
        this.resizeObserver = new ResizeObserver(() => this.resize());
        this.resizeObserver.observe(canvas);
        document.addEventListener("visibilitychange", () => document.hidden ? this.stop() : this.start());
        this.resize();
        this.start();
      }

      resize() {
        const bounds = this.canvas.getBoundingClientRect();
        const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
        this.canvas.width = Math.max(1, Math.round(bounds.width * pixelRatio));
        this.canvas.height = Math.max(1, Math.round(bounds.height * pixelRatio));
        this.context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        this.width = bounds.width;
        this.height = bounds.height;
        this.particles = this.particles.filter(particle => particle.x < this.width && particle.y < this.height);
        this.render(performance.now());
      }

      randomInteger(maximum) {
        if (maximum <= 0) return 0;
        const values = new Uint32Array(1);
        crypto.getRandomValues(values);
        return values[0] % maximum;
      }

      randomFloat() { return this.randomInteger(10000) / 10000; }

      createParticle(origin = null) {
        const columns = Math.max(1, Math.floor(this.width / this.pixelSize));
        const rows = Math.max(1, Math.floor(this.height / this.pixelSize));
        const x = origin
          ? Math.max(0, Math.min(this.width - this.pixelSize, origin.x + (this.randomInteger(15) - 7) * this.pixelSize))
          : this.randomInteger(columns) * this.pixelSize;
        const y = origin
          ? Math.max(0, Math.min(this.height - this.pixelSize, origin.y + (this.randomInteger(11) - 5) * this.pixelSize))
          : this.randomInteger(rows) * this.pixelSize;
        return {
          x,
          y,
          width: this.pixelSize * (1 + this.randomInteger(3)),
          height: this.pixelSize * (this.randomInteger(8) === 0 ? 2 : 1),
          age: 0,
          lifetime: 850 + this.randomInteger(2400),
          opacity: .09 + this.randomFloat() * .24,
          tone: this.randomInteger(2) === 0 ? "light" : "dark",
          flicker: .55 + this.randomFloat() * 1.05
        };
      }

      seedStaticTexture() {
        this.particles = [];
        const count = Math.max(70, Math.round((this.width * this.height) / 5600));
        for (let index = 0; index < count; index += 1) {
          const particle = this.createParticle();
          particle.age = this.randomInteger(Math.max(1, Math.floor(particle.lifetime)));
          this.particles.push(particle);
        }
      }

      update(timestamp, deltaTime) {
        const ambientSpawnCount = Math.max(2, Math.round(this.width / 230));
        for (let index = 0; index < ambientSpawnCount; index += 1) this.particles.push(this.createParticle());

        if (timestamp >= this.nextClusterAt) {
          const origin = { x: this.randomInteger(Math.max(1, Math.floor(this.width))), y: this.randomInteger(Math.max(1, Math.floor(this.height))) };
          const clusterSize = 8 + this.randomInteger(18);
          for (let index = 0; index < clusterSize; index += 1) this.particles.push(this.createParticle(origin));
          this.nextClusterAt = timestamp + 420 + this.randomInteger(1450);
        }

        this.particles.forEach(particle => { particle.age += deltaTime; });
        this.particles = this.particles.filter(particle => particle.age < particle.lifetime);
        const maximumParticles = Math.max(220, Math.round((this.width * this.height) / 1250));
        if (this.particles.length > maximumParticles) this.particles.splice(0, this.particles.length - maximumParticles);
      }

      render(timestamp) {
        if (!this.context) return;
        const width = this.width || 0;
        const height = this.height || 0;
        this.context.clearRect(0, 0, width, height);
        const darkTheme = Dom.html.dataset.theme === "dark";

        this.particles.forEach(particle => {
          const progress = particle.age / particle.lifetime;
          const envelope = Math.sin(Math.PI * Math.min(1, progress));
          const flicker = .72 + Math.sin((timestamp / 105) * particle.flicker + particle.x * .07 + particle.y * .05) * .28;
          const alpha = Math.max(.015, particle.opacity * envelope * flicker);
          const isLight = particle.tone === "light";
          this.context.fillStyle = isLight
            ? (darkTheme ? `rgba(245,246,238,${alpha * .82})` : `rgba(247,248,241,${alpha})`)
            : (darkTheme ? `rgba(8,10,9,${alpha * .75})` : `rgba(18,21,18,${alpha * .9})`);
          this.context.fillRect(particle.x, particle.y, particle.width, particle.height);
        });
      }

      animate = timestamp => {
        if (document.hidden || this.reducedMotion.matches) {
          this.animationFrame = null;
          return;
        }
        if (!this.previousFrameTime) this.previousFrameTime = timestamp;
        const elapsed = timestamp - this.previousFrameTime;
        if (elapsed >= this.frameInterval) {
          this.previousFrameTime = timestamp - (elapsed % this.frameInterval);
          this.update(timestamp, elapsed);
          this.render(timestamp);
        }
        this.animationFrame = requestAnimationFrame(this.animate);
      };

      start() {
        if (this.animationFrame || document.hidden) return;
        if (this.reducedMotion.matches) {
          this.seedStaticTexture();
          this.render(0);
          return;
        }
        this.previousFrameTime = 0;
        this.nextClusterAt = performance.now() + 180;
        this.animationFrame = requestAnimationFrame(this.animate);
      }

      stop() {
        if (!this.animationFrame) return;
        cancelAnimationFrame(this.animationFrame);
        this.animationFrame = null;
        this.previousFrameTime = 0;
      }
    }

    class AppController {
      constructor() {
        this.repository = new MessageRepository();
        this.reportRepository = new ReportRepository();
        this.reactionRepository = new ReactionRepository();
        this.hiddenMessageIds = this.reportRepository.hiddenMessageIds();
        this.heldSignatures = new Set();
        this.display = new EInkDisplay();
        this.bindingStore = new BindingStore();
        this.messages = [];
        this.visibleCount = AppConfig.archivePageSize;
        this.searchQuery = "";
        this.activeId = null;
        this.appliedSignature = null;
        this.publishedSignature = null;
        this.showFavicons = true;
        this.previewMode = "latest";
        this.toastTimer = null;
        this.autoApplyTimer = null;
        this.imageWorkbench = new ImageWorkbench(
          (message, tone) => this.updateState(message, tone),
          () => this.queueAutomaticApply(60)
        );
        this.imageDropController = new ImageDropController(file => this.imageWorkbench.selectFile(file));
        this.bannerPixelField = new BannerPixelField(Dom.destinationPixelField);
        this.reportPopover = new ReportPopoverController(this.reportRepository, (message, result) => this.handleReportResult(message, result));
        this.reactionPopover = new ReactionPopoverController(
          this.reactionRepository,
          (message, emoji) => this.handleReaction(message, emoji),
          () => this.renderRecent()
        );
        this.entityPicker = new EntityPickerController(this.bindingStore, () => {
          this.renderEntities();
          this.updateState();
        });
        this.bindEvents();
      }

      publicMessages() {
        return this.messages.filter(message => message.prepared || !this.hiddenMessageIds.has(message.id));
      }

      filteredMessages() {
        const messages = this.publicMessages();
        const query = this.searchQuery.trim().toLocaleLowerCase("en");
        if (!query) return messages;
        return messages.filter(message => {
          const destinations = LinkRenderer.destinationList(message.message, message.bindings, message.showFavicons)
            .map(item => `${item.entity.raw} ${item.href}`).join(" ");
          const searchable = `${message.id} ${message.name} ${message.message} ${destinations}`.toLocaleLowerCase("en");
          return searchable.includes(query);
        });
      }

      async handleReaction(message, emoji) {
        try {
          const reactions = await this.reactionRepository.toggle(message, emoji);
          message.reactions = reactions;
          return reactions;
        } catch (error) {
          this.showToast(error?.message || "The reaction could not be saved.");
          return this.reactionRepository.summary(message);
        }
      }

      async handleReportResult(message, result) {
        if (result.duplicate) {
          this.showToast("A report from this browser has already been received for that note.");
          return;
        }
        if (!result.accepted) {
          this.showToast("The report could not be accepted.");
          return;
        }
        if (result.hidden) {
          this.hiddenMessageIds.add(message.id);
          const hiddenWasVisible = this.activeId === message.id;
          if (hiddenWasVisible && this.publishedSignature) this.heldSignatures.add(this.publishedSignature);
          this.publishedSignature = null;
          this.appliedSignature = null;
          this.renderRecent();
          if (hiddenWasVisible || !this.publicMessages().some(item => item.id === this.activeId)) this.showLatest(true);
          this.updateState();
          this.showToast("The note is hidden pending review.");
          return;
        }
        this.showToast("Report received. Thank you for the signal.");
      }

      bindEvents() {
        Dom.pageBackButton.addEventListener("click", () => NavigationController.returnToPreviousSurface());
        Dom.nameInput.addEventListener("input", () => this.updateState());
        Dom.nameInput.addEventListener("blur", () => this.queueAutomaticApply(30));
        Dom.messageInput.addEventListener("input", () => { this.renderEntities(); this.updateState(); });
        Dom.faviconToggle.addEventListener("click", () => {
          this.showFavicons = !this.showFavicons;
          Dom.faviconToggle.setAttribute("aria-pressed", String(this.showFavicons));
          Dom.faviconToggleText.textContent = this.showFavicons ? "Site icons on" : "Site icons off";
          this.updateState();
        });
        Dom.updateButton.addEventListener("click", () => this.applyDraft({ revealOnMobile: true }));
        Dom.publishButton.addEventListener("click", () => this.handlePrimaryAction());
        Dom.form.addEventListener("submit", event => { event.preventDefault(); this.handlePrimaryAction(); });
        Dom.themeToggle.addEventListener("click", () => this.toggleTheme());
        Dom.loadMoreButton.addEventListener("click", () => { this.visibleCount += AppConfig.archivePageSize; this.renderRecent(); });
        Dom.recentSearch.addEventListener("input", () => {
          this.searchQuery = Dom.recentSearch.value;
          Dom.recentSearchClear.hidden = !this.searchQuery;
          this.renderRecent();
        });
        Dom.recentSearchClear.addEventListener("click", () => {
          Dom.recentSearch.value = "";
          this.searchQuery = "";
          Dom.recentSearchClear.hidden = true;
          Dom.recentSearch.focus();
          this.renderRecent();
        });
      }

      currentDraft() {
        const nameResult = ModerationPolicy.inspectName(Dom.nameInput.value);
        const messageResult = ModerationPolicy.inspectMessage(Dom.messageInput.value);
        const bindings = this.bindingStore.activeFor(messageResult.clean);
        const signature = [
          nameResult.clean,
          messageResult.moderated,
          this.imageWorkbench.output?.signature || "",
          this.bindingStore.signature(messageResult.clean),
          this.showFavicons ? "favicons:1" : "favicons:0"
        ].join("\n--\n");
        return { nameResult, messageResult, bindings, signature, image: this.imageWorkbench.output };
      }

      setStatus(message, tone = "neutral") {
        Dom.formStatus.textContent = message;
        Dom.formStatus.dataset.tone = tone;
      }

      setPrimaryAction(action) {
        const isLiveAction = action === PrimaryAction.VIEW_LIVE;
        Dom.publishButton.dataset.action = action;
        Dom.publishButton.textContent = isLiveAction ? "View live ink" : "Publish note";
      }

      handlePrimaryAction() {
        if (Dom.publishButton.disabled) return;
        if (Dom.publishButton.dataset.action === PrimaryAction.VIEW_LIVE) {
          window.open(AppConfig.destinationUrl, "_blank", "noopener,noreferrer");
          return;
        }
        this.publish();
      }

      updateState(message = null, tone = "neutral") {
        const draft = this.currentDraft();
        Dom.nameCounter.textContent = `${Array.from(Dom.nameInput.value).length} / ${AppConfig.limits.name}`;
        Dom.messageCounter.textContent = `${Array.from(Dom.messageInput.value).length} / ${AppConfig.limits.message}`;

        const valid = draft.nameResult.allowed && draft.messageResult.allowed && Boolean(draft.messageResult.clean);
        const heldDraft = this.heldSignatures.has(draft.signature);
        const processing = this.imageWorkbench.processing || this.display.refreshing;
        const draftDiffersFromDisplay = valid && !heldDraft && this.appliedSignature !== draft.signature;
        const previewMatchesDraft = valid && !heldDraft && this.appliedSignature === draft.signature;
        const publishedDraftIsVisible = previewMatchesDraft
          && this.publishedSignature === draft.signature;

        Dom.updateButton.disabled = !draftDiffersFromDisplay || processing;

        if (publishedDraftIsVisible) {
          this.setPrimaryAction(PrimaryAction.VIEW_LIVE);
          Dom.publishButton.disabled = processing;
          Dom.publishStage.dataset.state = "live";
          Dom.publishState.textContent = "03 / Live on GitHub";
          Dom.publishHeadline.textContent = "See it while it is still the latest.";
          Dom.publishHint.textContent = "Open the profile now. The next public note will replace it at the top.";
        } else {
          this.setPrimaryAction(PrimaryAction.PUBLISH);
          Dom.publishButton.disabled = !previewMatchesDraft || processing;
          Dom.publishStage.dataset.state = Dom.publishButton.disabled ? "blocked" : "ready";
          Dom.publishState.textContent = "03 / Publish to GitHub";

          if (!draft.nameResult.allowed) {
            Dom.publishHeadline.textContent = "Name needs attention.";
            Dom.publishHint.textContent = draft.nameResult.reason;
          } else if (!draft.messageResult.allowed) {
            Dom.publishHeadline.textContent = "Message cannot be published.";
            Dom.publishHint.textContent = draft.messageResult.reason;
          } else if (heldDraft) {
            Dom.publishHeadline.textContent = "This exact note is under review.";
            Dom.publishHint.textContent = "Change the draft before preparing another public ink.";
          } else if (this.imageWorkbench.processing) {
            Dom.publishHeadline.textContent = "Preparing the image.";
            Dom.publishHint.textContent = "The display will refresh when the E-Ink conversion is ready.";
          } else if (draftDiffersFromDisplay) {
            Dom.publishHeadline.textContent = "Display is out of date.";
            Dom.publishHint.textContent = "Update the ink to review the exact public result.";
          } else if (previewMatchesDraft) {
            Dom.publishHeadline.textContent = "Preview synchronized.";
            Dom.publishHint.textContent = "Publish this exact ink to the GitHub profile surface.";
          } else {
            Dom.publishHeadline.textContent = "Preview required.";
            Dom.publishHint.textContent = "Write a name and a message, then update the display.";
          }
        }

        if (message) return this.setStatus(message, tone);
        if (!draft.nameResult.allowed && Dom.nameInput.value.trim()) return this.setStatus(draft.nameResult.reason, "danger");
        if (!draft.messageResult.allowed) return this.setStatus(draft.messageResult.reason, "danger");
        if (!draft.messageResult.clean) return this.setStatus("Write a name and a message.");
        if (heldDraft) return this.setStatus("This exact note is under review. Change the draft to continue.", "warning");
        if (this.imageWorkbench.processing) return this.setStatus("Preparing the E-Ink image.");
        if (publishedDraftIsVisible) return this.setStatus("Published. Open the live profile while this note is still the latest.", "success");
        if (previewMatchesDraft && draft.messageResult.count) return this.setStatus(`${draft.messageResult.count} expression${draft.messageResult.count === 1 ? "" : "s"} obscured. Preview ready to publish.`, "warning");
        if (previewMatchesDraft) return this.setStatus("Ink is current. Ready to publish.", "success");
        if (draft.messageResult.count) return this.setStatus(`${draft.messageResult.count} expression${draft.messageResult.count === 1 ? "" : "s"} will be obscured on the public surface.`, "warning");
        return this.setStatus("Draft changed. Update the display to continue.");
      }

      renderEntities() {
        const source = Dom.messageInput.value;
        const entities = EntityParser.parse(source);
        Dom.entityStrip.replaceChildren();
        Dom.entityArea.hidden = entities.length === 0;
        if (!entities.length) { this.entityPicker.close(); return; }

        const urlCount = entities.filter(entity => entity.type === "url").length;
        const mentionCount = entities.filter(entity => entity.type === "mention").length;
        Dom.entitySummary.textContent = `${entities.length} destination${entities.length === 1 ? "" : "s"} detected`;
        Dom.faviconToggle.hidden = urlCount === 0;

        for (const entity of entities) {
          if (entity.type === "mention") {
            const binding = this.bindingStore.get(entity.handle);
            const button = document.createElement("button");
            button.type = "button";
            button.className = `entity-token${binding ? "" : " is-unassigned"}`;
            const label = document.createElement("span");
            label.textContent = entity.raw;
            button.append(label);
            const platform = document.createElement("sup");
            platform.className = "entity-token__platform";
            platform.append(IconRegistry.create(binding ? PlatformCatalog[binding.platform]?.icon || "at" : "at"));
            button.append(platform);
            const hint = document.createElement("span");
            hint.className = "entity-token__hint";
            hint.textContent = binding ? PlatformCatalog[binding.platform].label : "Choose";
            button.append(hint);
            button.addEventListener("mouseenter", () => this.entityPicker.open(entity.handle, button));
            button.addEventListener("mouseleave", () => this.entityPicker.scheduleClose());
            button.addEventListener("focus", () => this.entityPicker.open(entity.handle, button));
            button.addEventListener("click", () => this.entityPicker.open(entity.handle, button));
            Dom.entityStrip.append(button);
          } else {
            const token = document.createElement("span");
            token.className = "entity-token";
            const label = document.createElement("span");
            label.textContent = entity.raw;
            if (entity.type === "url") token.append(IconRegistry.create("link"), label);
            else {
              token.append(label);
              const platform = document.createElement("sup");
              platform.className = "entity-token__platform";
              platform.append(IconRegistry.create("mail"));
              token.append(platform);
            }
            Dom.entityStrip.append(token);
          }
        }

        if (!mentionCount) this.entityPicker.close();
      }

      queueAutomaticApply(delay) {
        clearTimeout(this.autoApplyTimer);
        this.autoApplyTimer = window.setTimeout(() => {
          const draft = this.currentDraft();
          if (draft.nameResult.allowed && draft.messageResult.allowed && draft.messageResult.clean && !this.imageWorkbench.processing && this.appliedSignature !== draft.signature) {
            this.applyDraft({ revealOnMobile: false });
          }
        }, delay);
      }

      async applyDraft({ revealOnMobile = false } = {}) {
        if (this.display.refreshing || this.imageWorkbench.processing) return;
        const draft = this.currentDraft();
        if (!draft.nameResult.allowed) return this.setStatus(draft.nameResult.reason, "danger");
        if (!draft.messageResult.allowed) return this.setStatus(draft.messageResult.reason, "danger");
        if (!draft.messageResult.clean) return this.setStatus("Write a message first.", "danger");
        if (this.heldSignatures.has(draft.signature)) return this.setStatus("Change the draft before preparing another public ink.", "warning");
        this.previewMode = "draft";
        this.activeId = null;
        this.appliedSignature = draft.signature;
        this.updateState();
        await this.display.refresh({
          mode: "draft",
          name: draft.nameResult.clean,
          message: draft.messageResult.moderated,
          image: draft.image,
          bindings: draft.bindings,
          showFavicons: this.showFavicons,
          date: new Date()
        }, { revealOnMobile });
        this.renderRecent();
        this.updateState();
      }

      async publish() {
        if (Dom.publishButton.disabled || this.display.refreshing || this.imageWorkbench.processing) return;
        if (Dom.websiteInput.value) return this.setStatus("The note could not be accepted.", "danger");
        const draft = this.currentDraft();
        if (!draft.nameResult.allowed || !draft.messageResult.allowed || this.heldSignatures.has(draft.signature) || this.appliedSignature !== draft.signature) return this.updateState();

        Dom.publishButton.disabled = true;
        this.setStatus("Publishing to GitHub surface.");
        try {
          const record = await this.repository.publish({
            id: crypto.randomUUID?.(),
            name: draft.nameResult.clean,
            message: draft.messageResult.moderated,
            image: draft.image,
            bindings: draft.bindings,
            showFavicons: this.showFavicons,
            createdAt: new Date().toISOString()
          }, this.messages);
          this.messages = [record, ...this.messages.filter(item => item.id !== record.id)].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
          if (this.repository.local) this.repository.saveLocal(this.messages);
          this.previewMode = "latest";
          this.activeId = record.id;
          this.publishedSignature = draft.signature;
          this.appliedSignature = draft.signature;
          await this.display.refresh({ mode: "latest", ...record, date: new Date(record.createdAt) });
          this.visibleCount = Math.max(this.visibleCount, AppConfig.archivePageSize);
          this.renderRecent();
          this.updateState();
          this.showToast("Published. Open the profile now while this ink is still the latest.");
        } catch (error) {
          this.setStatus(error?.message || "The note could not be published.", "danger");
          this.updateState();
        }
      }

      createRecentLink(item) {
        const anchor = document.createElement("a");
        const identityLink = item.entity.type === "email" || item.entity.type === "mention";
        anchor.className = `recent-link${identityLink ? " recent-link--identity" : ""}`;
        anchor.href = item.href;
        anchor.rel = "ugc nofollow noopener noreferrer";
        anchor.referrerPolicy = "no-referrer";
        if (/^https?:\/\//i.test(item.href)) anchor.target = "_blank";
        const label = document.createElement("span");
        label.textContent = item.entity.type === "url"
          ? item.entity.raw.replace(/^https?:\/\/(?:www\.)?/i, "").replace(/\/$/, "")
          : item.entity.raw;

        if (!identityLink) {
          anchor.append(IconRegistry.create("link"), label);
          return anchor;
        }

        anchor.append(label);
        const platform = document.createElement("sup");
        platform.className = "recent-link__platform";
        const iconName = item.entity.type === "email" ? "mail" : PlatformCatalog[item.binding?.platform]?.icon || "at";
        platform.append(IconRegistry.create(iconName));
        anchor.append(platform);
        return anchor;
      }

      renderReactionBar(message) {
        const bar = document.createElement("div");
        bar.className = "reaction-bar";
        const summary = this.reactionRepository.summary(message);
        summary.slice(0, 5).forEach(item => {
          const button = document.createElement("button");
          button.type = "button";
          button.className = `reaction-pill${item.reacted ? " is-selected" : ""}`;
          button.setAttribute("aria-pressed", String(item.reacted));
          button.setAttribute("aria-label", `${item.emoji}, ${item.count} reaction${item.count === 1 ? "" : "s"}`);
          const emoji = document.createElement("span");
          emoji.className = "reaction-pill__emoji";
          emoji.textContent = item.emoji;
          const count = document.createElement("span");
          count.className = "reaction-pill__count";
          count.textContent = String(item.count);
          button.append(emoji, count);
          button.addEventListener("click", async () => {
            button.disabled = true;
            await this.handleReaction(message, item.emoji);
            this.renderRecent();
          });
          bar.append(button);
        });

        const add = document.createElement("button");
        add.type = "button";
        add.className = "reaction-add";
        add.setAttribute("aria-expanded", "false");
        add.setAttribute("aria-haspopup", "dialog");
        add.setAttribute("aria-controls", "reactionPopover");
        add.setAttribute("aria-label", `React to note ${message.id}`);
        add.innerHTML = '<span aria-hidden="true">+</span><span>React</span>';
        add.addEventListener("click", () => this.reactionPopover.open(message, add));
        bar.append(add);
        return bar;
      }

      renderRecent() {
        this.reactionPopover.close(true, false);
        this.reportPopover.close();
        Dom.recentList.replaceChildren();
        const allPublicMessages = this.publicMessages();
        const filteredMessages = this.filteredMessages();
        const searchActive = Boolean(this.searchQuery.trim());
        const visibleMessages = searchActive ? filteredMessages : filteredMessages.slice(0, this.visibleCount);

        Dom.recentCount.textContent = searchActive
          ? `${filteredMessages.length} of ${allPublicMessages.length} notes`
          : `${allPublicMessages.length} note${allPublicMessages.length === 1 ? "" : "s"}`;
        Dom.recentSearchState.textContent = searchActive
          ? `${filteredMessages.length} result${filteredMessages.length === 1 ? "" : "s"} for “${this.searchQuery.trim()}”`
          : "";

        if (!filteredMessages.length) {
          const empty = document.createElement("div");
          empty.className = "recent-empty";
          empty.textContent = searchActive ? "No inks match this search." : "No public inks yet.";
          Dom.recentList.append(empty);
          Dom.recentTools.hidden = true;
          return;
        }

        visibleMessages.forEach((message, index) => {
          const article = document.createElement("article");
          article.className = `recent-entry${this.activeId === message.id ? " is-active" : ""}${message.prepared ? " is-prepared" : ""}`;
          article.dataset.noteId = message.id;

          const number = document.createElement("span");
          number.className = "recent-index";
          const sourceIndex = allPublicMessages.findIndex(item => item.id === message.id);
          number.textContent = String(sourceIndex + 1).padStart(2, "0");

          const main = document.createElement("div");
          main.className = "recent-main";
          const copy = document.createElement("p");
          copy.className = "recent-message";
          LinkRenderer.render(copy, message.message, { bindings: message.bindings, showFavicons: message.showFavicons });
          const meta = document.createElement("span");
          meta.className = "recent-meta";
          const author = document.createElement("strong");
          author.textContent = message.name;
          const time = document.createElement("time");
          time.dateTime = message.createdAt;
          time.textContent = this.display.formatDate(new Date(message.createdAt));
          meta.append(author, time);
          if (message.prepared) {
            const prepared = document.createElement("span");
            prepared.className = "prepared-badge";
            prepared.textContent = "From angusu.de";
            meta.append(prepared);
          }
          main.append(copy, meta);

          const destinations = LinkRenderer.destinationList(message.message, message.bindings, message.showFavicons);
          if (destinations.length) {
            const links = document.createElement("div");
            links.className = "recent-links";
            destinations.forEach(item => links.append(this.createRecentLink(item)));
            main.append(links);
          }
          main.append(this.renderReactionBar(message));

          const actions = document.createElement("div");
          actions.className = "recent-actions";
          const view = document.createElement("button");
          view.type = "button";
          view.className = "recent-view";
          view.textContent = "View ink";
          view.addEventListener("click", async () => {
            this.activeId = message.id;
            this.previewMode = "archive";
            this.appliedSignature = null;
            await this.display.refresh({ mode: "archive", ...message, date: new Date(message.createdAt) }, { revealOnMobile: true });
            this.renderRecent();
            this.updateState();
          });
          actions.append(view);

          if (message.reportable !== false && !message.prepared) {
            const report = document.createElement("button");
            report.type = "button";
            report.className = "recent-report";
            report.dataset.noteId = message.id;
            report.setAttribute("aria-expanded", "false");
            report.setAttribute("aria-haspopup", "dialog");
            report.setAttribute("aria-controls", "reportPopover");
            report.setAttribute("aria-label", `Report note ${message.id}`);
            report.append(IconRegistry.create("flag", "recent-report__icon"));
            const reportLabel = document.createElement("span");
            reportLabel.textContent = "Report";
            report.append(reportLabel);
            report.addEventListener("click", () => this.reportPopover.open(message, report));
            actions.append(report);
          } else {
            const managed = document.createElement("span");
            managed.className = "owner-managed";
            managed.textContent = "Owner managed";
            actions.append(managed);
          }

          article.append(number, main, actions);
          Dom.recentList.append(article);
        });
        Dom.recentTools.hidden = searchActive || this.visibleCount >= filteredMessages.length;
      }

      showLatest(animate = false) {
        const latest = this.publicMessages()[0];
        this.previewMode = "latest";
        this.activeId = latest?.id || null;
        this.appliedSignature = null;
        const payload = latest
          ? { mode: "latest", ...latest, date: new Date(latest.createdAt) }
          : { mode: "latest", name: "Anonymous", message: "No public ink yet.", image: null, bindings: {}, showFavicons: true, date: new Date() };
        if (animate) this.display.refresh(payload);
        else this.display.set(payload);
        this.renderRecent();
      }

      async toggleTheme() {
        if (this.display.refreshing) return;
        Dom.themeToggle.disabled = true;
        const next = Dom.html.dataset.theme === "dark" ? "light" : "dark";
        await this.display.refreshTheme(next);
        Dom.themeToggle.disabled = false;
      }

      showToast(message) {
        clearTimeout(this.toastTimer);
        Dom.toast.textContent = message;
        Dom.toast.classList.add("is-visible");
        this.toastTimer = window.setTimeout(() => Dom.toast.classList.remove("is-visible"), 3400);
      }

      async init() {
        let theme = "light";
        try { theme = localStorage.getItem(AppConfig.themeKey) || (matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light"); }
        catch { /* Use light theme when storage is unavailable. */ }
        Dom.html.dataset.theme = theme === "dark" ? "dark" : "light";
        Dom.themeLabel.textContent = theme === "dark" ? "Light mode" : "Dark mode";
        Dom.themeToggle.setAttribute("aria-label", theme === "dark" ? "Switch to light mode" : "Switch to dark mode");

        try { this.messages = (await this.repository.list()).sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt)); }
        catch {
          this.messages = this.repository.loadLocal();
          this.showToast("The live endpoint is unavailable. The local archive is active.");
        }

        Dom.faviconToggle.setAttribute("aria-pressed", "true");
        Dom.heroDestinationLink.href = AppConfig.destinationUrl;
        Dom.liveProfileLink.href = AppConfig.destinationUrl;
        Dom.repositoryLink.href = AppConfig.repositoryUrl;
        Dom.footerRepositoryLink.href = AppConfig.repositoryUrl;
        this.showLatest(false);
        this.renderEntities();
        this.updateState();
      }
    }

    new AppController().init();
  </script>
</body>
</html>

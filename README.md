<div align="center">

# InkWall

### Put a note on my GitHub.

A public E‑Ink message surface with a live SVG, image processing, reactions, reporting, and privacy-conscious usage statistics.

</div>

<p align="center">
  <a href="https://angusu.de/inkwall/">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://angusu.de/inkwall/action.svg.php?action=live&amp;theme=dark&amp;v=2">
      <source media="(prefers-color-scheme: light)" srcset="https://angusu.de/inkwall/action.svg.php?action=live&amp;theme=light&amp;v=2">
      <img width="340" src="https://angusu.de/inkwall/action.svg.php?action=live&amp;theme=light&amp;v=2" alt="Open the live InkWall surface">
    </picture>
  </a>&nbsp;
  <a href="https://github.com/IamAngusU">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://angusu.de/inkwall/action.svg.php?action=profile&amp;theme=dark&amp;v=2">
      <source media="(prefers-color-scheme: light)" srcset="https://angusu.de/inkwall/action.svg.php?action=profile&amp;theme=light&amp;v=2">
      <img width="340" src="https://angusu.de/inkwall/action.svg.php?action=profile&amp;theme=light&amp;v=2" alt="View the live GitHub profile">
    </picture>
  </a>
</p>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://angusu.de/inkwall/latest.svg?theme=dark&amp;v=5">
  <source media="(prefers-color-scheme: light)" srcset="https://angusu.de/inkwall/latest.svg?theme=light&amp;v=5">
  <img width="100%" src="https://angusu.de/inkwall/latest.svg?theme=light&amp;v=5" alt="Latest public InkWall message">
</picture>

## What it is

InkWall lets visitors compose a short note and publish it to a paper-like public surface. The newest accepted note becomes a server-rendered SVG on my GitHub profile; the site keeps a searchable archive and supports images, reactions, and community reports.

The browser converts uploaded images locally into a compact four-tone WebP before anything is sent to the server. The backend validates the result again, stores the public note, and updates the live card without a build or GitHub Action.

## Underneath the paper

```text
Browser editor
  ├─ local crop, zoom, invert, four-tone WebP
  ├─ note preview and moderation hints
  └─ random browser pseudonym
              │
              ▼
PHP API ── SQLite ── revalidated live SVG
   │           │
   │           ├─ public notes and reactions
   │           ├─ reports and moderation state
   │           └─ pseudonymous usage events
   │
   └─ private cockpit integration
```

## Privacy boundary

InkWall intentionally supports correlation without identifying a person:

- a random browser value is transformed into a server-side HMAC hash;
- raw IP addresses and user agents are not stored by InkWall;
- referrers are reduced to the hostname — paths, queries, and fragments are discarded;
- country is accepted only as a coarse local edge/proxy hint and otherwise remains `unknown` (the live deployment uses the [DB-IP Country Lite](https://db-ip.com/db/lite.php) database locally);
- the public interface explains this behavior where notes are submitted.

This is a technical data-minimization design, not a substitute for reviewing the privacy and consent requirements that apply to your deployment.

## Features

- responsive E‑Ink editor and live preview
- preserved manual line breaks, text alignment, and image placement
- local image conversion with crop, zoom, inversion, and size limits
- public archive, search, reactions, and external-destination rendering
- report thresholds with immediate holds for priority safety categories
- generated light/dark SVG for profile READMEs
- private moderation and usage cockpit
- no external analytics SDK and no GitHub Actions dependency

## Run it

The production deployment targets PHP 8.1+ with PDO SQLite and the `/inkwall` path.

```bash
git clone https://github.com/IamAngusU/InkWall.git
cd InkWall
php -S 127.0.0.1:8080 -t public public/router.php
```

For clean API routes and `latest.svg`, use the example Nginx locations in [`deploy/nginx.location.conf`](deploy/nginx.location.conf). The runtime needs write access to `data/`; that directory and its generated HMAC secret are ignored by Git.

## Repository map

```text
public/                  public editor, API, media and SVG renderer
integrations/cockpit/    private admin-dashboard adapter
deploy/                  Nginx location example
tools/                   template import helper
data/                    generated runtime state, never committed
```

## Moderation note

Client-side checks improve the writing experience, but the server is authoritative. It enforces lengths, image type and size, URL rules, request limits, report uniqueness, and review states. Production moderation is intentionally designed so that it can be strengthened independently of the visual editor.

## License

[MIT](LICENSE) — built by [Angus Uelsmann](https://angusu.de).

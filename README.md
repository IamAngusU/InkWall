<div align="center">

# InkWall

### Put a note on my GitHub.

A public E-Ink message surface with a live SVG, image processing, reactions, reporting, and privacy-conscious usage statistics.

</div>

<p align="center">
  <a href="https://angusu.de/inkwall/">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://angusu.de/inkwall/action.svg.php?action=live&amp;theme=dark&amp;v=2">
      <source media="(prefers-color-scheme: light)" srcset="https://angusu.de/inkwall/action.svg.php?action=live&amp;theme=light&amp;v=2">
      <img width="340" src="https://angusu.de/inkwall/action.svg.php?action=live&amp;theme=light&amp;v=2" alt="Open the live InkWall surface">
    </picture>
  </a>
  <a href="https://github.com/IamAngusU">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://angusu.de/inkwall/action.svg.php?action=profile&amp;theme=dark&amp;v=2">
      <source media="(prefers-color-scheme: light)" srcset="https://angusu.de/inkwall/action.svg.php?action=profile&amp;theme=light&amp;v=2">
      <img width="340" src="https://angusu.de/inkwall/action.svg.php?action=profile&amp;theme=light&amp;v=2" alt="View the live GitHub profile">
    </picture>
  </a>
</p>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://angusu.de/inkwall/latest.svg?theme=dark&amp;v=6">
  <source media="(prefers-color-scheme: light)" srcset="https://angusu.de/inkwall/latest.svg?theme=light&amp;v=6">
  <img width="100%" src="https://angusu.de/inkwall/latest.svg?theme=light&amp;v=6" alt="Latest public InkWall message">
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
- raw IP addresses and browser fingerprints are not stored by InkWall;
- referrers are reduced to the hostname; paths, queries, and fragments are discarded;
- country is accepted only as a coarse local edge/proxy hint and otherwise remains `unknown` (the live deployment uses the [DB-IP Country Lite](https://db-ip.com/db/lite.php) database locally);
- the public interface explains this behavior where notes are submitted.

This is a technical data-minimization design, not a substitute for reviewing the privacy and consent requirements that apply to your deployment.

## Features

- responsive E-Ink editor and live preview
- preserved manual line breaks, text alignment, and image placement
- local image conversion with crop, zoom, inversion, and size limits
- public archive, search, reactions, and external-destination rendering
- report thresholds with immediate holds for priority safety categories
- server-rendered light/dark SVG for profile READMEs
- private moderation and usage cockpit
- no external analytics SDK and no GitHub Actions dependency

## Run it

The production deployment targets PHP 8.1+ with PDO SQLite and the `/inkwall` path.

```bash
git clone https://github.com/IamAngusU/InkWall.git
cd InkWall
php -S 127.0.0.1:8080 -t public public/router.php
```

Copy the example environment file when you want review mail or AI moderation:

```bash
cp .env.example .env
```

For clean API routes and `latest.svg`, use the example Nginx locations in [`deploy/nginx.location.conf`](deploy/nginx.location.conf). The runtime needs write access to `data/`; that directory and its generated HMAC secret are ignored by Git.

## Repository map

```text
public/                  public editor, API, media and SVG renderer
integrations/cockpit/    private admin-dashboard adapter
deploy/                  Nginx location example
tools/                   template import helper
data/                    runtime state, never committed
```

## Moderation note

Client-side checks improve the writing experience, but the server is authoritative. It enforces lengths, image type and size, URL rules, request limits, report uniqueness, and review states.

InkWall can run with no AI key at all. In that mode it uses local heuristics and community reports. For public deployments, you can add a review gate without letting AI reject people:

- `allow` becomes public immediately.
- `review` is held for a human.
- AI never returns a final block/delete decision.
- `latest.svg.php` only reads `published` notes, so a held note automatically falls back to the last approved ink.
- AI calls have separate cost guards. When a quota is reached, or DeepSeek balance is unavailable/too low, the note is held for manual review without another model call.

Supported providers:

```env
# OpenAI moderation, text + image
INKWALL_AI_PROVIDER=openai_moderation
OPENAI_API_KEY=sk-...

# DeepSeek, OpenAI-compatible review
INKWALL_AI_PROVIDER=deepseek
DEEPSEEK_API_KEY=...
INKWALL_DEEPSEEK_MODEL=deepseek-v4-flash
INKWALL_DEEPSEEK_BALANCE_GUARD=1
INKWALL_DEEPSEEK_MIN_BALANCE_USD=0.25
INKWALL_DEEPSEEK_DAILY_SPEND_LIMIT_USD=1.00
INKWALL_DEEPSEEK_ESTIMATED_CALL_USD=0.01
# Enable only if your DeepSeek model/API accepts OpenAI-style image content.
INKWALL_DEEPSEEK_SEND_IMAGES=0

# Ollama, local text review
INKWALL_AI_PROVIDER=ollama
INKWALL_OLLAMA_URL=http://127.0.0.1:11434
INKWALL_OLLAMA_MODEL=qwen3:latest
```

You can also split text and image review by provider. Empty channel values keep using `INKWALL_AI_PROVIDER`.

```env
INKWALL_AI_PROVIDER=deepseek
INKWALL_AI_TEXT_PROVIDER=deepseek
INKWALL_AI_TEXT_MODEL=deepseek-v4-flash
INKWALL_AI_IMAGE_PROVIDER=openai_moderation
INKWALL_AI_IMAGE_MODEL=omni-moderation-latest
```

For a more future-proof config, use the JSON channel form. `manual` means the channel is not model-reviewed and can be held by policy.

```env
INKWALL_AI_CHANNELS_JSON={"text":{"provider":"ollama","model":"gemma3:4b"},"image":{"provider":"manual"}}
```

Each note stores a structured review chain in `ai_review_json`:

```json
{
  "text": {
    "provider": "deepseek",
    "model": "deepseek-v4-flash",
    "decision": "allow",
    "flags": ["advertising"],
    "confidence": 0.98,
    "latency_ms": 842
  },
  "image": {
    "provider": "manual",
    "model": "manual",
    "decision": "review",
    "flags": ["image_unchecked"],
    "confidence": 0.75,
    "latency_ms": 0
  }
}
```

DeepSeek image sending is opt-in because the public DeepSeek API docs may differ by model and account. Ollama is text-only in the default InkWall integration. If a user submits an image with a text-only provider, InkWall adds `image_unchecked` by default. Set `INKWALL_AI_REVIEW_UNCHECKED_IMAGES=1` if those images should always wait for human review, or `INKWALL_AI_ALLOW_UNCHECKED_IMAGES=1` if you do not want the audit flag.

InkWall shares anonymous AI metadata with the maintainer by default so future releases can compare provider reliability and speed. It sends version, status, `has_image`, channel, provider, model, decision, flags, confidence, and latency. It does not send names, messages, images, IPs, full referrers, visitor hashes, report details, or secrets.

```env
INKWALL_SHARE_AI_METADATA=1
INKWALL_AI_METADATA_ENDPOINT=https://angusu.de/inkwall/telemetry.php
```

Branding can be changed with one JSON value or individual overrides:

```env
INKWALL_BRANDING_JSON={"accent":"#2ec4b6","paper_texture":"dots","theme":"light","ad_badge":true,"ad_badge_text":"AD","review_badge":true,"review_badge_mode":"model","review_badge_text":"Reviewed automatically","review_badge_model_prefix":"Approved by","owner_name":"Angus Uelsmann","profile_url":"https://github.com/IamAngusU","image_rendering":"ink"}
# INKWALL_BRAND_ACCENT=#2ec4b6
```

The public SVG can render emoji as InkWall marks and show footer link hints:

```env
INKWALL_SVG_EMOJI_STYLE=native
INKWALL_SVG_FOOTER_LINKS=1
INKWALL_SVG_CLICKABLE_LINKS=1
INKWALL_SVG_AD_BADGE=1
INKWALL_SVG_REVIEW_BADGE=1
```

AI categories are policy-driven. Flags stay stored for audit, but each deployment decides what they do:

```env
INKWALL_AI_ALLOW_REJECT=0
INKWALL_AI_FLAG_POLICY_JSON={"advertising":"allow","harassment":"hold","copyright":"hold","violence":"hold","nudity":"hold"}
# INKWALL_AI_FLAG_ADVERTISING=allow
```

The default AI quotas are intentionally small for public profiles. For DeepSeek, InkWall records balance snapshots and estimates rolling 24-hour spend from balance drops, so the budget is based on provider balance movement rather than a calendar-day counter.

```env
INKWALL_AI_VISITOR_HOURLY_LIMIT=5
INKWALL_AI_GLOBAL_HOURLY_LIMIT=40
INKWALL_AI_GLOBAL_DAILY_LIMIT=0
INKWALL_AI_IMAGE_HOURLY_LIMIT=12
INKWALL_DEEPSEEK_BALANCE_GUARD=1
INKWALL_DEEPSEEK_MIN_BALANCE_USD=0.25
INKWALL_DEEPSEEK_DAILY_SPEND_LIMIT_USD=1.00
INKWALL_DEEPSEEK_ESTIMATED_CALL_USD=0.01
```

For review notifications set `INKWALL_REVIEW_EMAIL`, `INKWALL_PUBLIC_URL`, and SMTP values such as `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, and `MAIL_PASSWORD`. Review mails include a signed SVG preview URL for the exact held ink.

## License

[MIT](LICENSE). Built by [Angus Uelsmann](https://angusu.de).

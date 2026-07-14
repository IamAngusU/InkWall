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

Fast setup:

```bash
git clone https://github.com/IamAngusU/InkWall.git
cd InkWall
./install.sh
```

Private review receiver on Linux, macOS, or WSL:

```bash
./start-private-review-linux.sh
```

Linux desktop/server autostart can be managed later:

```bash
./manage-private-review-linux.sh
./manage-private-review-linux.sh install service
./manage-private-review-linux.sh install terminal
./manage-private-review-linux.sh status
./manage-private-review-linux.sh stop
./manage-private-review-linux.sh remove
```

`service` runs hidden through the user service manager and restarts after crashes. `terminal` is a visible desktop autostart for debugging on graphical Linux sessions.

Windows users can choose WSL or native Windows. Native Windows does not need `sudo`, `apt`, or Homebrew:

```powershell
git clone https://github.com/IamAngusU/InkWall.git
cd InkWall
php -v
.\setup-windows.cmd
```

The Windows setup asks for branding, public URL, moderation mode, optional cloud keys, private review fallback, autostart, and whether the receiver should start immediately.

If you only want to start the private review receiver later:

```powershell
.\start-private-review-windows.cmd
```

The Windows start helper creates missing private-review secrets, writes them to `.env`, chooses a free port, creates the inbox folder, and prints the matching tunnel command. You can also run the setup wizard directly with PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\setup-windows.ps1
```

If `php -v` is not found, install PHP for Windows from php.net or your package manager, then open a new terminal. If Windows refuses a socket on port `8787`, the Windows start helper automatically tries the next free port and prints the matching tunnel and server endpoint. You can also choose one:

```powershell
powershell -ExecutionPolicy Bypass -File .\start-private-review-windows.ps1 -Port 8797
```

Autostart can be managed later without editing files:

```powershell
.\manage-private-review-windows.cmd
.\manage-private-review-windows.cmd install hidden
.\manage-private-review-windows.cmd start
.\manage-private-review-windows.cmd status
.\manage-private-review-windows.cmd stop
.\manage-private-review-windows.cmd remove
```

`hidden` starts the receiver as a scheduled task after sign-in. Use `window` when you want a visible receiver window for debugging:

```powershell
.\manage-private-review-windows.cmd install window
```

The installer creates `.env`, generates private-review secrets, prepares `data/`, and lets you choose one of three review modes:

- cloud AI with private fallback
- private computer only
- local checks only

You can switch later without deleting keys:

```env
INKWALL_AI_CLOUD_ENABLED=1
INKWALL_AI_TEXT_CLOUD_ENABLED=1
INKWALL_AI_IMAGE_CLOUD_ENABLED=1
INKWALL_REMOTE_REVIEW=fallback
```

For private-computer-only review:

```env
INKWALL_AI_CLOUD_ENABLED=0
INKWALL_AI_TEXT_CLOUD_ENABLED=0
INKWALL_AI_IMAGE_CLOUD_ENABLED=0
INKWALL_REMOTE_REVIEW=fallback
```

Manual setup is also possible. Copy the example environment file when you want review mail or AI moderation:

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
- AI calls have separate cost guards. When a quota is reached, or provider budget is unavailable/too low, the note is held for manual review by default. Set fail-open per provider if your deployment should keep publishing when an AI provider is out of budget.

Supported providers:

```env
# OpenAI moderation, text + image
INKWALL_AI_PROVIDER=openai_moderation
OPENAI_API_KEY=sk-...

# OpenAI vision via Responses API, image review only
INKWALL_AI_IMAGE_PROVIDER=openai_vision
INKWALL_AI_IMAGE_MODEL=gpt-4o-mini
INKWALL_OPENAI_VISION_DETAIL=low
INKWALL_OPENAI_DAILY_SPEND_LIMIT_USD=1.00
INKWALL_OPENAI_ESTIMATED_IMAGE_CALL_USD=0.01
INKWALL_OPENAI_VISION_FAIL_OPEN=1

# DeepSeek, OpenAI-compatible review
INKWALL_AI_PROVIDER=deepseek
DEEPSEEK_API_KEY=...
INKWALL_DEEPSEEK_MODEL=deepseek-v4-flash
INKWALL_DEEPSEEK_BALANCE_GUARD=1
INKWALL_DEEPSEEK_MIN_BALANCE_USD=0.25
INKWALL_DEEPSEEK_DAILY_SPEND_LIMIT_USD=1.00
INKWALL_DEEPSEEK_ESTIMATED_CALL_USD=0.01
INKWALL_DEEPSEEK_FAIL_OPEN=0
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
INKWALL_AI_IMAGE_PROVIDER=openai_vision
INKWALL_AI_IMAGE_MODEL=gpt-4o-mini
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

For OpenAI image review, the API key only needs `Responses (/v1/responses)` write access. `List models` read access is optional for your own diagnostics. InkWall does not need OpenAI image generation, files, assistants, vector stores, chat completions, or the moderation endpoint when you use `openai_vision`.

Private fallback review can send text, images, or both to your own machine. Use it when cloud AI is out of budget, unavailable, or when you want all review handled locally. The server signs every job with HMAC and accepts only `allow` or `review` plus flags from the private receiver. The normal InkWall flag policy still decides whether the note is public or held.

```env
# off, fallback, or always
INKWALL_REMOTE_REVIEW=fallback
INKWALL_REMOTE_REVIEW_ENDPOINT=https://your-private-endpoint.example/review
INKWALL_REMOTE_REVIEW_SECRET=change-this-long-random-secret
INKWALL_REMOTE_REVIEW_ENCRYPT=1
INKWALL_REMOTE_REVIEW_ENCRYPTION_KEY=change-this-second-long-random-secret
INKWALL_REMOTE_REVIEW_TIMEOUT_SECONDS=8
INKWALL_REMOTE_REVIEW_FAIL_OPEN=1
INKWALL_REMOTE_REVIEW_SEND_TEXT=1
INKWALL_REMOTE_REVIEW_SEND_IMAGE=1
```

On your private machine you can run the included receiver. Put it behind HTTPS directly, through a reverse proxy, or through an SSH tunnel that forwards only this local port. Requests are authenticated with HMAC. With `INKWALL_REMOTE_REVIEW_ENCRYPT=1`, the payload is encrypted with XChaCha20-Poly1305 before transport and decrypted only by the private receiver.

```bash
export INKWALL_PRIVATE_REVIEW_SECRET="change-this-long-random-secret"
export INKWALL_PRIVATE_REVIEW_ENCRYPTION_KEY="change-this-second-long-random-secret"
export INKWALL_PRIVATE_REVIEW_DIR="$HOME/InkWallReviewInbox"
export INKWALL_PRIVATE_REVIEW_DEFAULT=review
# Optional: command receives the created job folder and prints JSON.
export INKWALL_PRIVATE_REVIEW_COMMAND="$HOME/bin/inkwall-local-review"
php -S 127.0.0.1:8787 tools/private-review-receiver.php
```

On native Windows, use:

```powershell
.\start-private-review-windows.cmd
```

The command can send the saved job to Ollama, a local script, a local app, or any other private review system. It receives the job folder path as its first argument and must print compact JSON:

Example local review command output:

```json
{"verdict":"allow","flags":[],"confidence":0.91,"model":"local-qwen3-vision"}
```

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

The default AI quotas are intentionally small for public profiles. For DeepSeek, InkWall records balance snapshots and estimates rolling 24-hour spend from balance drops, so the budget is based on provider balance movement rather than a calendar-day counter. For OpenAI Vision, InkWall records a configurable estimated call cost because the public API key flow does not expose a simple project balance endpoint.

```env
INKWALL_AI_VISITOR_HOURLY_LIMIT=5
INKWALL_AI_GLOBAL_HOURLY_LIMIT=40
INKWALL_AI_GLOBAL_DAILY_LIMIT=0
INKWALL_AI_IMAGE_HOURLY_LIMIT=12
INKWALL_DEEPSEEK_BALANCE_GUARD=1
INKWALL_DEEPSEEK_MIN_BALANCE_USD=0.25
INKWALL_DEEPSEEK_DAILY_SPEND_LIMIT_USD=1.00
INKWALL_DEEPSEEK_ESTIMATED_CALL_USD=0.01
INKWALL_OPENAI_DAILY_SPEND_LIMIT_USD=1.00
INKWALL_OPENAI_ESTIMATED_IMAGE_CALL_USD=0.01
INKWALL_OPENAI_VISION_FAIL_OPEN=0
# Optional global fallback. Provider-specific values win.
INKWALL_AI_FAIL_OPEN=0
```

For review notifications set `INKWALL_REVIEW_EMAIL`, `INKWALL_PUBLIC_URL`, and SMTP values such as `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, and `MAIL_PASSWORD`. Review mails include a signed SVG preview URL for the exact held ink.

## License

[MIT](LICENSE). Built by [Angus Uelsmann](https://angusu.de).

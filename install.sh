#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$ROOT_DIR/.env"

say() { printf '%s\n' "$*"; }
ask() {
  local prompt="$1"
  local default="${2:-}"
  local value
  if [ -n "$default" ]; then
    read -r -p "$prompt [$default]: " value || true
    printf '%s' "${value:-$default}"
  else
    read -r -p "$prompt: " value || true
    printf '%s' "$value"
  fi
}
yes_no() {
  local prompt="$1"
  local default="${2:-y}"
  local value
  read -r -p "$prompt [$default]: " value || true
  value="${value:-$default}"
  case "$(printf '%s' "$value" | tr '[:upper:]' '[:lower:]')" in
    y|yes|j|ja|1|true) return 0 ;;
    *) return 1 ;;
  esac
}
secret() {
  if command -v openssl >/dev/null 2>&1; then
    printf 'base64:%s' "$(openssl rand -base64 32)"
  else
    php -r 'echo "base64:" . base64_encode(random_bytes(32));'
  fi
}
json_branding() {
  php -r '
    $brand = [
      "accent" => $argv[1],
      "paper_texture" => "dots",
      "theme" => "light",
      "ad_badge" => false,
      "review_badge" => false,
      "svg_ink_number" => true,
      "svg_latest_label" => "latest_only",
      "owner_name" => $argv[2],
      "profile_url" => $argv[3],
      "repository_url" => $argv[4],
      "site_url" => $argv[5],
      "site_label" => $argv[6],
      "image_rendering" => "ink",
    ];
    echo json_encode($brand, JSON_UNESCAPED_SLASHES);
  ' "$@"
}

if ! command -v php >/dev/null 2>&1; then
  say "PHP CLI is required. Install php-cli first."
  exit 1
fi

say "InkWall setup"
say "This creates a local .env file. Existing files are not overwritten without a backup."
if [ -f "$ENV_FILE" ]; then
  backup="$ENV_FILE.backup.$(date +%Y%m%d-%H%M%S)"
  cp "$ENV_FILE" "$backup"
  say "Existing .env backed up to $backup"
fi

owner_name="$(ask "Owner name" "Your Name")"
github_profile="$(ask "GitHub profile URL" "https://github.com/yourname")"
repo_url="$(ask "InkWall repository URL" "https://github.com/yourname/InkWall")"
public_url="$(ask "Public InkWall URL" "https://example.com/inkwall")"
site_label="$(ask "Short site label" "example.com/inkwall")"
accent="$(ask "Accent color" "#d7422f")"
review_email="$(ask "Review email" "admin@example.com")"

say ""
say "Moderation mode:"
say "  1) Cloud AI with private fallback"
say "  2) Private computer only"
say "  3) Local checks only"
mode="$(ask "Choose 1, 2, or 3" "1")"

cloud_enabled=1
remote_mode=fallback
remote_endpoint=""
remote_secret="$(secret)"
remote_encrypt_key="$(secret)"
text_cloud=1
image_cloud=1

case "$mode" in
  2)
    cloud_enabled=0
    text_cloud=0
    image_cloud=0
    remote_mode=always
    ;;
  3)
    cloud_enabled=0
    text_cloud=0
    image_cloud=0
    remote_mode=off
    ;;
  *)
    cloud_enabled=1
    if yes_no "Use cloud AI for text review?" "y"; then text_cloud=1; else text_cloud=0; fi
    if yes_no "Use cloud AI for image review?" "y"; then image_cloud=1; else image_cloud=0; fi
    if yes_no "Use private computer as fallback?" "y"; then remote_mode=fallback; else remote_mode=off; fi
    ;;
esac

if [ "$remote_mode" != "off" ]; then
  remote_endpoint="$(ask "Private review endpoint" "http://127.0.0.1:8787")"
fi

deepseek_key=""
openai_key=""
if [ "$cloud_enabled" = "1" ] && [ "$text_cloud" = "1" ]; then
  deepseek_key="$(ask "DeepSeek API key, optional" "")"
fi
if [ "$cloud_enabled" = "1" ] && [ "$image_cloud" = "1" ]; then
  openai_key="$(ask "OpenAI API key, optional" "")"
fi

brand_json="$(json_branding "$accent" "$owner_name" "$github_profile" "$repo_url" "$public_url" "$site_label")"

cat > "$ENV_FILE" <<EOF
INKWALL_PUBLIC_URL=$public_url
INKWALL_ADMIN_URL=$public_url/admin
INKWALL_REVIEW_EMAIL=$review_email
INKWALL_BRANDING_JSON=$brand_json

INKWALL_AI_MODERATION=auto
INKWALL_AI_CLOUD_ENABLED=$cloud_enabled
INKWALL_AI_TEXT_CLOUD_ENABLED=$text_cloud
INKWALL_AI_IMAGE_CLOUD_ENABLED=$image_cloud

INKWALL_AI_PROVIDER=deepseek
INKWALL_AI_TEXT_PROVIDER=deepseek
INKWALL_AI_TEXT_MODEL=deepseek-v4-flash
DEEPSEEK_API_KEY=$deepseek_key
DEEPSEEK_BASE_URL=https://api.deepseek.com
INKWALL_DEEPSEEK_MODEL=deepseek-v4-flash
INKWALL_DEEPSEEK_BALANCE_GUARD=1
INKWALL_DEEPSEEK_BALANCE_FAIL_CLOSED=0
INKWALL_DEEPSEEK_FAIL_OPEN=1
INKWALL_DEEPSEEK_DAILY_SPEND_LIMIT_USD=1.00
INKWALL_DEEPSEEK_ESTIMATED_CALL_USD=0.01
INKWALL_DEEPSEEK_SEND_IMAGES=0

INKWALL_AI_IMAGE_PROVIDER=openai_vision
INKWALL_AI_IMAGE_MODEL=gpt-4o-mini
OPENAI_API_KEY=$openai_key
INKWALL_OPENAI_VISION_MODEL=gpt-4o-mini
INKWALL_OPENAI_VISION_DETAIL=low
INKWALL_OPENAI_VISION_FAIL_OPEN=1
INKWALL_OPENAI_DAILY_SPEND_LIMIT_USD=1.00
INKWALL_OPENAI_ESTIMATED_IMAGE_CALL_USD=0.01

INKWALL_REMOTE_REVIEW=$remote_mode
INKWALL_REMOTE_REVIEW_ENDPOINT=$remote_endpoint
INKWALL_REMOTE_REVIEW_SECRET=$remote_secret
INKWALL_REMOTE_REVIEW_ENCRYPT=1
INKWALL_REMOTE_REVIEW_ENCRYPTION_KEY=$remote_encrypt_key
INKWALL_REMOTE_REVIEW_FAIL_OPEN=1
INKWALL_REMOTE_REVIEW_SEND_TEXT=1
INKWALL_REMOTE_REVIEW_SEND_IMAGE=1
INKWALL_REMOTE_REVIEW_TIMEOUT_SECONDS=8

INKWALL_AI_ALLOW_REJECT=0
INKWALL_AI_FLAG_POLICY_JSON={"advertising":"allow","harassment":"hold","copyright":"hold","violence":"hold","nudity":"hold"}
INKWALL_AI_REVIEW_UNCHECKED_IMAGES=0
INKWALL_AI_ALLOW_UNCHECKED_IMAGES=0
INKWALL_SHARE_AI_METADATA=1
INKWALL_AI_METADATA_ENDPOINT=https://angusu.de/inkwall/telemetry.php
EOF

chmod 600 "$ENV_FILE" || true
mkdir -p "$ROOT_DIR/data/inkwall"
chmod 750 "$ROOT_DIR/data" "$ROOT_DIR/data/inkwall" || true

say ""
say "Created $ENV_FILE"
say "Hard toggles:"
say "  INKWALL_AI_CLOUD_ENABLED=$cloud_enabled"
say "  INKWALL_AI_TEXT_CLOUD_ENABLED=$text_cloud"
say "  INKWALL_AI_IMAGE_CLOUD_ENABLED=$image_cloud"
say "  INKWALL_REMOTE_REVIEW=$remote_mode"

if [ "$remote_mode" != "off" ]; then
  say ""
  say "Private receiver values for your own computer:"
  say "  export INKWALL_PRIVATE_REVIEW_SECRET='$remote_secret'"
  say "  export INKWALL_PRIVATE_REVIEW_ENCRYPTION_KEY='$remote_encrypt_key'"
  say "  export INKWALL_PRIVATE_REVIEW_DIR=\"\$HOME/InkWallReviewInbox\""
  say "  php -S 127.0.0.1:8787 tools/private-review-receiver.php"
  say ""
  say "SSH reverse tunnel from your computer to the server:"
  say "  ssh -N -R 127.0.0.1:8787:127.0.0.1:8787 user@your-server"

  if [ "$(uname -s 2>/dev/null || true)" = "Linux" ] && yes_no "Install private review autostart on this Linux user?" "n"; then
    if yes_no "Run hidden as a user service?" "y"; then
      "$ROOT_DIR/manage-private-review-linux.sh" install service || true
    else
      "$ROOT_DIR/manage-private-review-linux.sh" install terminal || true
    fi
  else
    say "Autostart can be managed later with ./manage-private-review-linux.sh"
  fi
fi

say ""
say "Next:"
say "  1. Point your web server to public/ or copy public/ into your site path."
say "  2. Add deploy/nginx.location.conf to Nginx if you use /inkwall routes."
say "  3. Open $public_url"

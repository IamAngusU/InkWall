#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$ROOT_DIR/.env"
HOST_NAME="${INKWALL_PRIVATE_REVIEW_HOST:-127.0.0.1}"
PORT="${INKWALL_PRIVATE_REVIEW_PORT:-8787}"

env_get() {
  local key="$1"
  [ -f "$ENV_FILE" ] || return 1
  grep -E "^${key}=" "$ENV_FILE" | tail -n 1 | cut -d= -f2- | sed "s/^['\"]//;s/['\"]$//"
}

env_set() {
  local key="$1"
  local value="$2"
  touch "$ENV_FILE"
  if grep -qE "^${key}=" "$ENV_FILE"; then
    tmp="$(mktemp)"
    sed "s|^${key}=.*|${key}=${value}|" "$ENV_FILE" > "$tmp"
    mv "$tmp" "$ENV_FILE"
  else
    printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
  fi
}

secret() {
  if command -v openssl >/dev/null 2>&1; then
    printf 'base64:%s' "$(openssl rand -base64 32)"
  else
    php -r 'echo "base64:" . base64_encode(random_bytes(32));'
  fi
}

port_free() {
  php -r '$host=$argv[1];$port=(int)$argv[2];$s=@stream_socket_server("tcp://".$host.":".$port,$e,$m);if($s){fclose($s);exit(0);}exit(1);' "$HOST_NAME" "$1"
}

if ! command -v php >/dev/null 2>&1; then
  printf '%s\n' "PHP CLI was not found in PATH."
  exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
  printf '%s\n' "No .env found. Creating a minimal private-review config."
  cp "$ROOT_DIR/.env.example" "$ENV_FILE"
  env_set INKWALL_AI_CLOUD_ENABLED 0
  env_set INKWALL_AI_TEXT_CLOUD_ENABLED 0
  env_set INKWALL_AI_IMAGE_CLOUD_ENABLED 0
  env_set INKWALL_REMOTE_REVIEW fallback
  env_set INKWALL_REMOTE_REVIEW_ENCRYPT 1
  env_set INKWALL_REMOTE_REVIEW_FAIL_OPEN 1
fi

SECRET_VALUE="${INKWALL_PRIVATE_REVIEW_SECRET:-$(env_get INKWALL_REMOTE_REVIEW_SECRET || true)}"
ENCRYPTION_VALUE="${INKWALL_PRIVATE_REVIEW_ENCRYPTION_KEY:-$(env_get INKWALL_REMOTE_REVIEW_ENCRYPTION_KEY || true)}"
if [ -z "$SECRET_VALUE" ]; then
  SECRET_VALUE="$(secret)"
  env_set INKWALL_REMOTE_REVIEW_SECRET "$SECRET_VALUE"
  printf '%s\n' "Generated INKWALL_REMOTE_REVIEW_SECRET and saved it to .env."
fi
if [ -z "$ENCRYPTION_VALUE" ]; then
  ENCRYPTION_VALUE="$(secret)"
  env_set INKWALL_REMOTE_REVIEW_ENCRYPTION_KEY "$ENCRYPTION_VALUE"
  printf '%s\n' "Generated INKWALL_REMOTE_REVIEW_ENCRYPTION_KEY and saved it to .env."
fi

selected="$PORT"
limit=$((PORT + 99))
while [ "$selected" -le "$limit" ]; do
  if port_free "$selected"; then break; fi
  selected=$((selected + 1))
done
if [ "$selected" -gt "$limit" ]; then
  printf 'No free local port found from %s to %s.\n' "$PORT" "$limit"
  exit 1
fi

export INKWALL_PRIVATE_REVIEW_SECRET="$SECRET_VALUE"
export INKWALL_PRIVATE_REVIEW_ENCRYPTION_KEY="$ENCRYPTION_VALUE"
export INKWALL_PRIVATE_REVIEW_DIR="${INKWALL_PRIVATE_REVIEW_DIR:-$HOME/InkWallReviewInbox}"
export INKWALL_PRIVATE_REVIEW_DEFAULT="${INKWALL_PRIVATE_REVIEW_DEFAULT:-review}"
mkdir -p "$INKWALL_PRIVATE_REVIEW_DIR"

endpoint="http://127.0.0.1:$selected"
env_set INKWALL_REMOTE_REVIEW_ENDPOINT "$endpoint"
if [ -z "$(env_get INKWALL_REMOTE_REVIEW || true)" ]; then
  env_set INKWALL_REMOTE_REVIEW fallback
fi
env_set INKWALL_REMOTE_REVIEW_ENCRYPT 1

printf '%s\n' "InkWall private review receiver"
printf 'Inbox: %s\n' "$INKWALL_PRIVATE_REVIEW_DIR"
printf 'Local URL: http://%s:%s\n\n' "$HOST_NAME" "$selected"
printf '%s\n' "SSH reverse tunnel example:"
printf 'ssh -N -R 127.0.0.1:%s:%s:%s user@your-server\n\n' "$selected" "$HOST_NAME" "$selected"
printf '%s\n' "Set the server endpoint to:"
printf 'INKWALL_REMOTE_REVIEW_ENDPOINT=%s\n\n' "$endpoint"

cd "$ROOT_DIR"
exec php -S "$HOST_NAME:$selected" tools/private-review-receiver.php

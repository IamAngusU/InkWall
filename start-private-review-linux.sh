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
for key in INKWALL_PRIVATE_REVIEW_COMMAND INKWALL_CONTEXTBRIDGE_EXE INKWALL_CONTEXTBRIDGE_CONFIG; do
  value="$(env_get "$key" || true)"
  if [ -n "$value" ]; then export "$key=$value"; fi
done
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
ssh_target="$(env_get INKWALL_PRIVATE_REVIEW_SSH_TARGET || true)"
ssh_key="$(env_get INKWALL_PRIVATE_REVIEW_SSH_KEY || true)"
if [ -z "$ssh_target" ]; then
  printf '%s\n' "Server tunnel: not configured"
  printf 'Pair a server first or set INKWALL_PRIVATE_REVIEW_SSH_TARGET in %s.\n\n' "$ENV_FILE"
  cd "$ROOT_DIR"
  exec php -S "$HOST_NAME:$selected" tools/private-review-receiver.php
fi

if ! command -v ssh >/dev/null 2>&1; then
  printf '%s\n' "OpenSSH client was not found in PATH."
  exit 1
fi

contextbridge_connection() {
  local config="${INKWALL_CONTEXTBRIDGE_CONFIG:-}"
  [ -f "$config" ] || return 1
  local listen token
  listen="$(sed -nE 's/^[[:space:]]*listen:[[:space:]]*([^#[:space:]]+).*/\1/p' "$config" | head -n 1 | tr -d "'\"")"
  token="$(sed -nE 's/^[[:space:]]*token:[[:space:]]*([^#[:space:]]+).*/\1/p' "$config" | head -n 1 | tr -d "'\"")"
  [ -n "$token" ] || return 1
  printf '%s\t%s' "${listen:-127.0.0.1:32145}" "$token"
}

tunnel_heartbeat() {
  local state="$1"
  command -v curl >/dev/null 2>&1 || return 0
  local connection listen token body
  connection="$(contextbridge_connection || true)"
  [ -n "$connection" ] || return 0
  listen="${connection%%$'\t'*}"
  token="${connection#*$'\t'}"
  body="$(php -r 'echo json_encode(["state"=>$argv[1],"target"=>$argv[2],"transport"=>"SSH with encrypted InkWall payloads","local_port"=>(int)$argv[3],"remote_port"=>(int)$argv[3]], JSON_UNESCAPED_SLASHES);' "$state" "$ssh_target" "$selected")"
  curl -fsS --max-time 3 -X POST -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data "$body" "http://$listen/v1/tunnel/heartbeat" >/dev/null 2>&1 || true
}

server_probe() {
  local args=(-o BatchMode=yes -o ConnectTimeout=8)
  [ -n "$ssh_key" ] && args+=(-i "$ssh_key")
  args+=("$ssh_target" "curl -s -X POST -H 'X-InkWall-Probe: 1' -o /dev/null -w '%{http_code}' --max-time 5 http://127.0.0.1:$selected/")
  [ "$(ssh "${args[@]}" 2>/dev/null || true)" = "200" ]
}

cd "$ROOT_DIR"
mkdir -p "$ROOT_DIR/data/logs"
php -S "$HOST_NAME:$selected" tools/private-review-receiver.php >"$ROOT_DIR/data/logs/private-review-php-output.log" 2> >(tee -a "$ROOT_DIR/data/logs/private-review-php-server.log" >&2) &
php_pid=$!
bridge_pid=""
ssh_pid=""

cleanup() {
  tunnel_heartbeat disconnected
  [ -n "$ssh_pid" ] && kill "$ssh_pid" 2>/dev/null || true
  [ -n "$bridge_pid" ] && kill "$bridge_pid" 2>/dev/null || true
  kill "$php_pid" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

if [ -n "${INKWALL_CONTEXTBRIDGE_EXE:-}" ] && [ -x "$INKWALL_CONTEXTBRIDGE_EXE" ] && [ -f "${INKWALL_CONTEXTBRIDGE_CONFIG:-}" ]; then
  if ! "$INKWALL_CONTEXTBRIDGE_EXE" health --config "$INKWALL_CONTEXTBRIDGE_CONFIG" >/dev/null 2>&1; then
    "$INKWALL_CONTEXTBRIDGE_EXE" serve --config "$INKWALL_CONTEXTBRIDGE_CONFIG" >>"$ROOT_DIR/data/logs/contextbridge.log" 2>&1 &
    bridge_pid=$!
    sleep 1
  fi
  printf 'ContextBridge: http://127.0.0.1:32145\n'
fi

printf 'Server tunnel: %s\n' "$ssh_target"
printf '%s\n' "Transport: SSH with encrypted InkWall payloads"
printf '%s\n' "Waiting for review jobs. Receiver events stay visible in this terminal."

while kill -0 "$php_pid" 2>/dev/null; do
  ssh_args=(-N -o BatchMode=yes -o ExitOnForwardFailure=yes -o ServerAliveInterval=30 -o ServerAliveCountMax=3 -R "127.0.0.1:$selected:$HOST_NAME:$selected")
  [ -n "$ssh_key" ] && ssh_args+=(-i "$ssh_key")
  ssh_args+=("$ssh_target")
  printf '%s\n' "Connecting secure tunnel..."
  ssh "${ssh_args[@]}" &
  ssh_pid=$!
  sleep 1
  if ! kill -0 "$ssh_pid" 2>/dev/null; then
    printf '%s\n' "Tunnel failed. Retrying in 5 seconds."
    wait "$ssh_pid" 2>/dev/null || true
    sleep 5
    continue
  fi
  if server_probe; then
    printf 'Secure tunnel connected. Server probe passed on 127.0.0.1:%s.\n' "$selected"
    state=connected
  else
    printf '%s\n' "Tunnel is open, but the server probe is still pending."
    state=degraded
  fi
  tunnel_heartbeat "$state"
  ticks=0
  while kill -0 "$ssh_pid" 2>/dev/null && kill -0 "$php_pid" 2>/dev/null; do
    sleep 2
    ticks=$((ticks + 1))
    if [ $((ticks % 5)) -eq 0 ]; then tunnel_heartbeat "$state"; fi
    if [ $((ticks % 15)) -eq 0 ]; then if server_probe; then state=connected; else state=degraded; fi; fi
  done
  wait "$ssh_pid" 2>/dev/null || true
  ssh_pid=""
  tunnel_heartbeat disconnected
  printf '%s\n' "Tunnel disconnected. Reconnecting in 5 seconds."
  sleep 5
done

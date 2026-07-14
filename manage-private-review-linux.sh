#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ACTION="${1:-ask}"
MODE="${2:-service}"
SERVICE_NAME="inkwall-private-review.service"
SERVICE_DIR="$HOME/.config/systemd/user"
SERVICE_FILE="$SERVICE_DIR/$SERVICE_NAME"
AUTOSTART_DIR="$HOME/.config/autostart"
DESKTOP_FILE="$AUTOSTART_DIR/inkwall-private-review.desktop"

ask() {
  local prompt="$1"
  local default="$2"
  local value
  read -r -p "$prompt [$default]: " value || true
  printf '%s' "${value:-$default}"
}

install_service() {
  mkdir -p "$SERVICE_DIR"
  cat > "$SERVICE_FILE" <<EOF
[Unit]
Description=InkWall private review receiver
After=network-online.target

[Service]
Type=simple
WorkingDirectory=$ROOT_DIR
ExecStart=$ROOT_DIR/start-private-review-linux.sh
Restart=always
RestartSec=5

[Install]
WantedBy=default.target
EOF
  systemctl --user daemon-reload
  systemctl --user enable "$SERVICE_NAME"
  printf '%s\n' "Installed hidden user service: $SERVICE_NAME"
  printf '%s\n' "Start now with: ./manage-private-review-linux.sh start"
  if command -v loginctl >/dev/null 2>&1; then
    printf '%s\n' "For boot-before-login on your own machine, run once:"
    printf 'loginctl enable-linger %s\n' "$USER"
  fi
}

install_terminal() {
  mkdir -p "$AUTOSTART_DIR"
  terminal="x-terminal-emulator"
  if ! command -v "$terminal" >/dev/null 2>&1; then
    terminal="sh"
  fi
  if [ "$terminal" = "sh" ]; then
    exec_line="sh -lc 'cd \"$ROOT_DIR\" && ./start-private-review-linux.sh'"
  else
    exec_line="$terminal -e sh -lc 'cd \"$ROOT_DIR\" && ./start-private-review-linux.sh; read -r -p \"Press Enter to close\" _'"
  fi
  cat > "$DESKTOP_FILE" <<EOF
[Desktop Entry]
Type=Application
Name=InkWall Private Review
Exec=$exec_line
Terminal=false
X-GNOME-Autostart-enabled=true
EOF
  printf '%s\n' "Installed visible desktop autostart."
}

remove_all() {
  if command -v systemctl >/dev/null 2>&1; then
    systemctl --user disable --now "$SERVICE_NAME" >/dev/null 2>&1 || true
    systemctl --user daemon-reload >/dev/null 2>&1 || true
  fi
  rm -f "$SERVICE_FILE" "$DESKTOP_FILE"
  printf '%s\n' "Removed InkWall private review autostart."
}

status_all() {
  if [ -f "$SERVICE_FILE" ]; then
    systemctl --user status "$SERVICE_NAME" --no-pager || true
  else
    printf '%s\n' "Hidden user service is not installed."
  fi
  if [ -f "$DESKTOP_FILE" ]; then
    printf '%s\n' "Visible desktop autostart is installed."
  fi
}

if [ "$ACTION" = "ask" ]; then
  ACTION="$(ask "Action: install, remove, status, start, stop" "status")"
  if [ "$ACTION" = "install" ]; then
    MODE="$(ask "Mode: service hidden, terminal visible" "service")"
  fi
fi

case "$ACTION" in
  install)
    if [ "$MODE" = "terminal" ]; then install_terminal; else install_service; fi
    ;;
  remove) remove_all ;;
  status) status_all ;;
  start)
    if [ ! -f "$SERVICE_FILE" ]; then install_service; fi
    systemctl --user start "$SERVICE_NAME"
    printf '%s\n' "Started $SERVICE_NAME"
    ;;
  stop)
    systemctl --user stop "$SERVICE_NAME" >/dev/null 2>&1 || true
    printf '%s\n' "Stopped $SERVICE_NAME"
    ;;
  *)
    printf 'Unknown action: %s\n' "$ACTION"
    exit 1
    ;;
esac

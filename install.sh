#!/bin/bash
set -e

export LANG=en_US.UTF-8
export LC_ALL=C

# ============================================
# mpay Install Script
# Supports amd64 / arm64
# ============================================

REPO="abai569/mpay-flvx"
INSTALL_DIR="/opt/mpay"
IMAGE="ghcr.io/abai569/mpay-flvx:latest"
DEFAULT_MPAY_PORT=8088

# ============================================
# Utilities
# ============================================

install_download_tools() {
  local need_install=0

  if ! command -v curl &> /dev/null; then
    echo "[WARN] curl not found"
    need_install=1
  fi

  if ! command -v wget &> /dev/null; then
    echo "[WARN] wget not found"
    need_install=1
  fi

  if [ $need_install -eq 0 ]; then
    return 0
  fi

  echo "[INFO] Installing missing download tools..."

  OS_TYPE=$(uname -s)

  if [[ "$OS_TYPE" == "Darwin" ]]; then
    if command -v brew &> /dev/null; then
      brew install curl wget git
    else
      echo "[ERROR] Homebrew not found, please install curl and wget manually"
      exit 1
    fi
    return 0
  fi

  if [ -f /etc/os-release ]; then
    . /etc/os-release
    DISTRO=$ID
  elif [ -f /etc/redhat-release ]; then
    DISTRO="rhel"
  elif [ -f /etc/debian_version ]; then
    DISTRO="debian"
  else
    DISTRO="unknown"
  fi

  case $DISTRO in
    ubuntu|debian|kali)
      apt update
      apt install -y curl wget git
      ;;
    centos|rhel|fedora|almalinux|rocky)
      if command -v dnf &> /dev/null; then
        dnf install -y curl wget git
      elif command -v yum &> /dev/null; then
        yum install -y curl wget git
      fi
      ;;
    alpine)
      apk add --no-cache curl wget git
      ;;
    arch|manjaro|endeavouros)
      pacman -S --noconfirm curl wget git
      ;;
    opensuse*|sles)
      zypper install -y curl wget git
      ;;
    *)
      echo "[WARN] Unknown distro, please install curl and wget manually"
      exit 1
      ;;
  esac

  echo "[OK] Download tools installed"
}

check_docker() {
  if command -v docker-compose &> /dev/null; then
    DOCKER_CMD="docker-compose"
    echo "[OK] Docker command: $DOCKER_CMD"
    return 0
  elif command -v docker &> /dev/null; then
    if docker compose version &> /dev/null; then
      DOCKER_CMD="docker compose"
      echo "[OK] Docker command: $DOCKER_CMD"
      return 0
    else
      echo "[WARN] docker found but 'docker compose' not supported, installing plugin..."
      install_docker_compose_plugin
      DOCKER_CMD="docker compose"
      echo "[OK] Docker command: $DOCKER_CMD"
      return 0
    fi
  fi

  echo "[INFO] Docker not found, installing..."
  install_docker
  if command -v docker &> /dev/null && docker compose version &> /dev/null; then
    DOCKER_CMD="docker compose"
    echo "[OK] Docker installed"
    echo "[OK] Docker command: $DOCKER_CMD"
    return 0
  fi

  echo "[ERROR] Docker installation failed, please install manually and retry"
  exit 1
}

install_docker() {
  curl -fsSL https://get.docker.com | bash -s docker --mirror Aliyun
  ln -sf /usr/libexec/docker/cli-plugins/docker-compose /usr/local/bin/docker-compose
  systemctl enable --now docker
}

install_docker_compose_plugin() {
  ln -sf /usr/libexec/docker/cli-plugins/docker-compose /usr/local/bin/docker-compose
}

generate_random() {
  LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c16
}

upsert_env_var() {
  local file="$1"
  local key="$2"
  local value="$3"
  local tmp_file

  tmp_file=$(mktemp)
  if [ -f "$file" ]; then
    awk -v k="$key" -v v="$value" '
      BEGIN { found=0 }
      $0 ~ ("^" k "=") { print k "=" v; found=1; next }
      { print }
      END { if (!found) print k "=" v }
    ' "$file" > "$tmp_file"
  else
    printf '%s=%s\n' "$key" "$value" > "$tmp_file"
  fi

  mv "$tmp_file" "$file"
}

get_env_var() {
  local key="$1"
  local file="${2:-.env}"

  if [[ ! -f "$file" ]]; then
    return 0
  fi

  grep -m1 "^${key}=" "$file" | cut -d= -f2-
}

get_public_ipv4() {
  local ip
  for url in "https://ifconfig.me" "https://ip.sb" "https://icanhazip.com" "https://api.ipify.org" "https://checkip.amazonaws.com"; do
    ip=$(curl -fsSL --max-time 3 "$url" 2>/dev/null | head -1)
    if [[ -n "$ip" ]] && [[ "$ip" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
      echo "$ip"
      return 0
    fi
  done
  echo ""
  return 1
}

# ============================================
# Menu
# ============================================

show_menu() {
  echo ""
  echo "==============================================="
  echo "          mpay Install Script"
  echo "==============================================="
  echo "Select action:"
  echo "1. Install mpay"
  echo "2. Update mpay"
  echo "3. Uninstall mpay"
  echo "4. Backup data"
  echo "5. Restore data"
  echo "6. Setup domain reverse proxy (Caddy)"
  echo "7. Exit"
  echo "==============================================="
}

# ============================================
# Install
# ============================================

install_mpay() {
  echo "[INSTALL] Starting mpay installation..."

  echo "[INFO] Creating install directory: $INSTALL_DIR"
  $SUDO_CMD mkdir -p "$INSTALL_DIR"
  cd "$INSTALL_DIR"

  check_docker

  echo ""
  echo "[CONFIG] Configure parameters:"
  read -p "Port (default $DEFAULT_MPAY_PORT): " MPAY_PORT
  MPAY_PORT=${MPAY_PORT:-$DEFAULT_MPAY_PORT}

  read -p "Use domain reverse proxy? (y/N): " use_proxy
  if [[ "$use_proxy" == "y" || "$use_proxy" == "Y" ]]; then
    read -p "Domain (e.g. pay.example.com): " SERVER_DOMAIN
    while [[ -z "$SERVER_DOMAIN" ]]; do
      echo "[ERROR] Domain cannot be empty"
      read -p "Domain: " SERVER_DOMAIN
    done
    SERVER_DOMAIN=$(echo "$SERVER_DOMAIN" | sed -e 's|^https\?://||' -e 's|/.*||')
  fi

  echo ""
  echo "[INFO] Pulling Docker image..."
  $DOCKER_CMD pull "$IMAGE"

  cat > docker-compose.yml <<EOF
version: "3.8"

services:
  mpay:
    image: $IMAGE
    container_name: mpay-app
    restart: unless-stopped
    environment:
      APP_DEBUG: false
      DB_TYPE: sqlite
      DB_NAME: database/mpay.db
      DB_PREFIX: mpay_
      DEFAULT_LANG: zh-cn
    ports:
      - "\${MPAY_PORT:-8088}:80"
    volumes:
      - mpay_data:/var/www/html/database
      - mpay_runtime:/var/www/html/runtime

volumes:
  mpay_data:
  mpay_runtime:
EOF

  echo "[INFO] Starting mpay service..."
  $DOCKER_CMD up -d

  echo ""
  echo "[COMPLETE] mpay deployed!"
  echo ""

  local public_ip=$(get_public_ipv4)
  public_ip=${public_ip:-"Server IP"}

  if [[ -n "$SERVER_DOMAIN" ]]; then
    echo "   URL: https://${SERVER_DOMAIN}"
    echo ""
    echo "[INFO] Configuring domain reverse proxy..."
    setup_caddy "$SERVER_DOMAIN" "$MPAY_PORT"
  else
    echo "   URL: http://${public_ip}:${MPAY_PORT}"
  fi

  echo ""
  echo "[INFO] First visit required:"
  echo "   1. Visit URL above to open install page"
  echo "   2. Database path default: database/mpay.db"
  echo "   3. Set admin account and password"
  echo ""
  echo "   Install dir: $INSTALL_DIR"
  echo "   Database: SQLite (auto-persisted)"
  echo "   Server restart: container auto-starts"
  echo ""
}

# ============================================
# Update
# ============================================

update_mpay() {
  echo "[UPDATE] Updating mpay..."

  if [[ ! -d "$INSTALL_DIR" ]]; then
    echo "[ERROR] mpay not found ($INSTALL_DIR does not exist), please install first"
    return 1
  fi

  cd "$INSTALL_DIR"
  check_docker

  # Backup data
  backup_data

  # Pull latest image
  echo "[INFO] Pulling latest Docker image..."
  $DOCKER_CMD pull "$IMAGE"

  # Restart
  echo "[INFO] Restarting service..."
  $DOCKER_CMD up -d --force-recreate

  echo "[OK] Update complete"
}

# ============================================
# Uninstall
# ============================================

uninstall_mpay() {
  local non_interactive="${1:-false}"

  if [[ ! -d "$INSTALL_DIR" ]]; then
    echo "[ERROR] mpay not found ($INSTALL_DIR does not exist)"
    return 1
  fi

  cd "$INSTALL_DIR"
  check_docker

  if [[ "$non_interactive" != "true" ]]; then
    read -p "Confirm uninstall? This will delete all containers, images and data (y/N): " confirm
    if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
      echo "[ERROR] Cancelled"
      return 0
    fi
  fi

  echo "[INFO] Stopping and removing containers..."
  $DOCKER_CMD down --rmi all --volumes --remove-orphans

  echo "[INFO] Removing install directory..."
  rm -rf "$INSTALL_DIR"

  echo "[OK] Uninstall complete"
}

# ============================================
# Backup
# ============================================

backup_data() {
  local backup_dir timestamp

  echo "[BACKUP] Starting mpay backup..."

  if [[ ! -d "$INSTALL_DIR" ]]; then
    echo "[ERROR] mpay not found ($INSTALL_DIR does not exist)"
    return 1
  fi

  cd "$INSTALL_DIR"
  check_docker

  timestamp=$(date +"%Y%m%d_%H%M%S")
  backup_dir="${INSTALL_DIR}/backup_${timestamp}"
  mkdir -p "$backup_dir"

  # Backup .env
  if [[ -f ".env" ]]; then
    cp .env "$backup_dir/.env"
    echo "  .env backed up"
  fi

  # Backup SQLite database
  echo "[BACKUP] Backing up SQLite database..."
  if docker ps --format "{{.Names}}" | grep -q "^mpay-app$"; then
    docker cp mpay-app:/var/www/html/database/mpay.db "$backup_dir/mpay.db" 2>/dev/null && echo "  mpay.db backed up" || echo "  Database backup failed"
  else
    # Backup from volume
    docker run --rm -v mpay_data:/data -v "$backup_dir":/backup alpine sh -c "cp /data/mpay.db /backup/mpay.db 2>/dev/null" && echo "  mpay.db backed up from volume"
  fi

  local backup_size
  backup_size=$(du -sh "$backup_dir" | cut -f1)

  echo ""
  echo "==============================================="
  echo "              Backup Complete"
  echo "==============================================="
  echo "  Dir: $backup_dir"
  echo "  Size: $backup_size"
  echo "==============================================="
}

# ============================================
# Restore
# ============================================

restore_data() {
  echo "[RESTORE] Starting mpay restore..."

  if [[ ! -d "$INSTALL_DIR" ]]; then
    echo "[ERROR] mpay not found ($INSTALL_DIR does not exist)"
    return 1
  fi

  # List backups
  local backups=()
  while IFS= read -r dir; do
    backups+=("$dir")
  done < <(ls -1d "${INSTALL_DIR}"/backup_* 2>/dev/null | sort -r)

  if [[ ${#backups[@]} -eq 0 ]]; then
    echo "[ERROR] No backup files found, please backup first"
    return 1
  fi

  echo ""
  echo "  Available backups:"
  echo "==============================================="
  local idx=1
  for dir in "${backups[@]}"; do
    local bsize
    bsize=$(du -sh "$dir" 2>/dev/null | cut -f1)
    echo "  $idx. $(basename "$dir") ($bsize)"
    idx=$((idx + 1))
  done
  echo "==============================================="

  read -p "Select backup number (1-$((idx-1))), enter defaults to 1: " backup_choice
  backup_choice=${backup_choice:-1}

  if ! [[ "$backup_choice" =~ ^[0-9]+$ ]] || [[ "$backup_choice" -lt 1 ]] || [[ "$backup_choice" -gt $((idx-1)) ]]; then
    echo "[ERROR] Invalid selection"
    return 1
  fi

  local backup_dir="${backups[$((backup_choice-1))]}"
  echo "  Selected: $(basename "$backup_dir")"

  read -p "Confirm restore? This will overwrite current data (y/N): " confirm
  if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
    echo "[ERROR] Cancelled"
    return 0
  fi

  cd "$INSTALL_DIR"
  check_docker

  # Stop service
  echo "[INFO] Stopping service..."
  docker stop mpay-app 2>/dev/null || true
  sleep 3

  # Restore database
  if [[ -f "$backup_dir/mpay.db" ]]; then
    echo "[RESTORE] Restoring SQLite database..."
    docker run --rm -v mpay_data:/data -v "$backup_dir":/restore alpine sh -c "cp /restore/mpay.db /data/mpay.db" && echo "  mpay.db restored"
  fi

  # Restore config
  if [[ -f "$backup_dir/.env" ]]; then
    read -p "Restore .env config file? (Y/n): " restore_env
    if [[ "$restore_env" != "n" && "$restore_env" != "N" ]]; then
      cp "$backup_dir/.env" "$INSTALL_DIR/.env"
      echo "  .env restored"
    fi
  fi

  # Restart
  echo "[INFO] Restarting service..."
  cd "$INSTALL_DIR"
  $DOCKER_CMD up -d

  echo "[OK] Data restore complete"
}

# ============================================
# Reverse Proxy (Caddy)
# ============================================

setup_caddy() {
  local domain="$1"
  local port="$2"

  echo "[INFO] Installing and configuring Caddy (domain: $domain)..."

  # Check system
  if ! command -v apt-get &> /dev/null; then
    echo "[ERROR] Current system does not support auto Caddy install (Debian/Ubuntu only)"
    echo "[WARN] Please install Caddy manually and proxy to http://127.0.0.1:$port"
    return 1
  fi

  # Install Caddy
  if ! command -v caddy &> /dev/null; then
    echo "[INFO] Caddy not found, installing..."
    sudo apt-get update
    sudo apt-get install -y debian-keyring debian-archive-keyring apt-transport-https curl gnupg
    curl -1sLf 'https://dl.cloudflare.com/cloudflare-main.gpg' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg 2>/dev/null || \
    curl -1sLf 'https://dl.cloudflare.com/content/v1/e2qwFJ2fRP2b2q/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudflare.com/content/v1/e2qwFJ2fRP2b2q/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
    sudo apt-get update
    sudo apt-get install -y caddy
  else
    echo "[OK] Caddy already installed"
  fi

  # Config Caddyfile
  echo "[INFO] Writing Caddyfile config..."
  sudo tee /etc/caddy/Caddyfile > /dev/null <<CADDY_EOF
${domain} {
    reverse_proxy http://127.0.0.1:${port} {
        header_up Host {host}
        header_up X-Forwarded-For {remote}
        header_up X-Forwarded-Proto {scheme}
    }
}
CADDY_EOF

  # Restart
  echo "[INFO] Restarting Caddy service..."
  sudo systemctl restart caddy

  echo "[OK] Caddy configured!"
  echo "   URL: https://$domain"
  sleep 2
}

configure_caddy_interactive() {
  local domain port

  if [[ ! -d "$INSTALL_DIR" ]]; then
    echo "[ERROR] mpay not found ($INSTALL_DIR does not exist)"
    return 1
  fi

  cd "$INSTALL_DIR"

  if [[ ! -f ".env" ]]; then
    echo "[ERROR] .env not found"
    return 1
  fi

  port=$(grep -m1 "^MPAY_PORT=" .env | cut -d= -f2)
  port=${port:-8088}

  echo "  Current mpay port: $port"

  read -p "Please enter domain (e.g. pay.example.com): " domain
  while [[ -z "$domain" ]]; do
    echo "[ERROR] Domain cannot be empty"
    read -p "Domain: " domain
  done

  setup_caddy "$domain" "$port"
}

# ============================================
# Main
# ============================================

main() {
  # Root check (non-strict with prompt)
  if [[ $EUID -ne 0 ]]; then
    SUDO_CMD="sudo"
    echo "[WARN] Not running as root, some operations may require sudo"
  else
    SUDO_CMD=""
  fi

  install_download_tools

  # Uninstall without interaction
  if [[ "$1" == "uninstall" ]]; then
    uninstall_mpay "true"
    exit $?
  fi

  while true; do
    show_menu
    read -p "Select option (1-7): " choice

    case $choice in
      1)
        install_mpay
        exit 0
        ;;
      2)
        update_mpay
        echo ""
        ;;
      3)
        uninstall_mpay
        echo ""
        ;;
      4)
        backup_data
        echo ""
        ;;
      5)
        restore_data
        echo ""
        ;;
      6)
        configure_caddy_interactive
        echo ""
        ;;
      7)
        echo "[EXIT] Quitting"
        exit 0
        ;;
      *)
        echo "[ERROR] Invalid option, please enter 1-7"
        ;;
    esac
  done
}

main "$@"

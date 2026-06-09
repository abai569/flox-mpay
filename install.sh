#!/bin/bash
set -e

export LANG=en_US.UTF-8
export LC_ALL=C

REPO="abai569/mpay-flvx"
INSTALL_DIR="/opt/mpay"
IMAGE="ghcr.io/abai569/mpay-flvx:latest"
DEFAULT_MPAY_PORT=8088

install_download_tools() {
  local need_install=0

  if ! command -v curl &> /dev/null; then
    echo "[WARN] 未检测到 curl"
    need_install=1
  fi

  if ! command -v wget &> /dev/null; then
    echo "[WARN] 未检测到 wget"
    need_install=1
  fi

  if [ $need_install -eq 0 ]; then
    return 0
  fi

  echo "[INFO] 正在安装缺失的下载工具..."

  OS_TYPE=$(uname -s)
  if [[ "$OS_TYPE" == "Darwin" ]]; then
    if command -v brew &> /dev/null; then
      brew install curl wget git
    else
      echo "[ERROR] 未检测到 Homebrew，请手动安装 curl 和 wget"
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
    alpine) apk add --no-cache curl wget git
      ;;
    arch|manjaro|endeavouros)
      pacman -S --noconfirm curl wget git
      ;;
    opensuse*|sles)
      zypper install -y curl wget git
      ;;
    *)
      echo "[WARN] 未知发行版，请手动安装 curl 和 wget"
      exit 1
      ;;
  esac

  echo "[OK] 下载工具安装完成"
}

check_docker() {
  if command -v docker-compose &> /dev/null; then
    DOCKER_CMD="docker-compose"
    echo "[OK] 检测到 Docker 命令：$DOCKER_CMD"
    return 0
  elif command -v docker &> /dev/null; then
    if docker compose version &> /dev/null; then
      DOCKER_CMD="docker compose"
      echo "[OK] 检测到 Docker 命令：$DOCKER_CMD"
      return 0
    else
      echo "[WARN] 检测到 docker，但不支持 'docker compose' 命令，尝试安装插件..."
      install_docker_compose_plugin
      DOCKER_CMD="docker compose"
      echo "[OK] 检测到 Docker 命令：$DOCKER_CMD"
      return 0
    fi
  fi

  echo "[INFO] 未检测到 Docker，开始自动安装..."
  install_docker
  if command -v docker &> /dev/null && docker compose version &> /dev/null; then
    DOCKER_CMD="docker compose"
    echo "[OK] Docker 安装成功"
    echo "[OK] 检测到 Docker 命令：$DOCKER_CMD"
    return 0
  fi

  echo "[ERROR] Docker 自动安装失败，请手动安装后重试"
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

show_menu() {
  echo ""
  echo "==============================================="
  echo "          mpay 安装管理脚本"
  echo "==============================================="
  echo "请选择操作："
  echo "1. 安装 mpay"
  echo "2. 更新 mpay"
  echo "3. 卸载 mpay"
  echo "4. 备份数据"
  echo "5. 恢复数据"
  echo "6. 域名反代 (Caddy)"
  echo "7. 退出"
  echo "==============================================="
}

install_mpay() {
  echo "[INFO] 开始安装 mpay..."
  echo "[INFO] 创建安装目录：$INSTALL_DIR"
  $SUDO_CMD mkdir -p "$INSTALL_DIR"
  cd "$INSTALL_DIR"

  check_docker

  echo ""
  echo "[CONFIG] 请输入配置参数："
  read -p " 访问端口（默认 $DEFAULT_MPAY_PORT）： " MPAY_PORT
  MPAY_PORT=${MPAY_PORT:-$DEFAULT_MPAY_PORT}

  read -p "是否使用域名反代？(y/N): " use_proxy
  if [[ "$use_proxy" == "y" || "$use_proxy" == "Y" ]]; then
    read -p "请输入域名（例如 pay.example.com）： " SERVER_DOMAIN
    while [[ -z "$SERVER_DOMAIN" ]]; do
      echo "[ERROR] 域名不能为空，请重新输入"
      read -p "请输入域名： " SERVER_DOMAIN
    done
    SERVER_DOMAIN=$(echo "$SERVER_DOMAIN" | sed -e 's|^https\?://||' -e 's|/.*||')
  fi

  echo ""
  echo "[INFO] 拉取 Docker 镜像..."
  docker pull "$IMAGE"

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

  echo "[INFO] 启动 mpay 服务..."
  $DOCKER_CMD up -d

  echo ""
  echo "[OK] mpay 部署完成！"
  echo ""

  local public_ip=$(get_public_ipv4)
  public_ip=${public_ip:-"服务器IP"}

  if [[ -n "$SERVER_DOMAIN" ]]; then
    echo "   访问地址：https://${SERVER_DOMAIN}"
    echo ""
    echo "[INFO] 正在配置域名反代..."
    setup_caddy "$SERVER_DOMAIN" "$MPAY_PORT"
  else
    echo "   访问地址：http://${public_ip}:${MPAY_PORT}"
  fi

  echo ""
  echo "[INFO] 首次访问说明："
  echo "   1. 打开上述地址进入 Web 安装向导"
  echo "   2. 数据库路径默认：database/mpay.db"
  echo "   3. 按提示设置管理员账号和密码"
  echo ""
  echo "   安装目录：$INSTALL_DIR"
  echo "   数据库类型：SQLite（文件自动持久化）"
  echo "   服务器重启后容器自动启动"
  echo ""
}

update_mpay() {
  echo "[INFO] 开始更新 mpay..."

  if [[ ! -d "$INSTALL_DIR" ]]; then
    echo "[ERROR] 未检测到 mpay 安装（$INSTALL_DIR 不存在），请先安装"
    return 1
  fi

  cd "$INSTALL_DIR"
  check_docker

  backup_data

  echo "[INFO] 拉取最新 Docker 镜像..."
  docker pull "$IMAGE"

  echo "[INFO] 重启服务..."
  $DOCKER_CMD up -d --force-recreate

  echo "[OK] 更新完成"
}

uninstall_mpay() {
  local non_interactive="${1:-false}"

  if [[ ! -d "$INSTALL_DIR" ]]; then
    echo "[ERROR] 未检测到 mpay 安装（$INSTALL_DIR 不存在）"
    return 1
  fi

  cd "$INSTALL_DIR"
  check_docker

  if [[ "$non_interactive" != "true" ]]; then
    read -p "确认卸载 mpay 吗？将删除所有容器、镜像和数据 (y/N): " confirm
    if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
      echo "[ERROR] 取消卸载"
      return 0
    fi
  fi

  echo "[INFO] 停止并删除容器..."
  $DOCKER_CMD down --rmi all --volumes --remove-orphans

  echo "[INFO] 删除安装目录..."
  rm -rf "$INSTALL_DIR"

  echo "[OK] 卸载完成"
}

backup_data() {
  local backup_dir timestamp

  echo "[INFO] 开始备份 mpay 数据..."

  if [[ ! -d "$INSTALL_DIR" ]]; then
    echo "[ERROR] 未检测到 mpay 安装（$INSTALL_DIR 不存在）"
    return 1
  fi

  cd "$INSTALL_DIR"
  check_docker

  timestamp=$(date +"%Y%m%d_%H%M%S")
  backup_dir="${INSTALL_DIR}/backup_${timestamp}"
  mkdir -p "$backup_dir"

  if [[ -f ".env" ]]; then
    cp .env "$backup_dir/.env"
    echo "  .env 已备份"
  fi

  echo "[BACKUP] 备份 SQLite 数据库..."
  if docker ps --format "{{.Names}}" | grep -q "^mpay-app$"; then
    docker cp mpay-app:/var/www/html/database/mpay.db "$backup_dir/mpay.db" 2>/dev/null && echo "  mpay.db 已备份" || echo "  数据库文件备份失败"
  else
    docker run --rm -v mpay_data:/data -v "$backup_dir":/backup alpine sh -c "cp /data/mpay.db /backup/mpay.db 2>/dev/null" && echo "  mpay.db 已从卷备份"
  fi

  local backup_size
  backup_size=$(du -sh "$backup_dir" | cut -f1)

  echo ""
  echo "==============================================="
  echo "              备份完成"
  echo "==============================================="
  echo "  备份目录：$backup_dir"
  echo "  备份大小：$backup_size"
  echo "==============================================="
}

restore_data() {
  echo "[INFO] 开始恢复 mpay 数据..."

  if [[ ! -d "$INSTALL_DIR" ]]; then
    echo "[ERROR] 未检测到 mpay 安装（$INSTALL_DIR 不存在）"
    return 1
  fi

  local backups=()
  while IFS= read -r dir; do
    backups+=("$dir")
  done < <(ls -1d "${INSTALL_DIR}"/backup_* 2>/dev/null | sort -r)

  if [[ ${#backups[@]} -eq 0 ]]; then
    echo "[ERROR] 未找到备份文件，请先执行备份操作"
    return 1
  fi

  echo ""
  echo "  可用备份列表："
  echo "==============================================="
  local idx=1
  for dir in "${backups[@]}"; do
    local bsize
    bsize=$(du -sh "$dir" 2>/dev/null | cut -f1)
    echo "  $idx. $(basename "$dir") ($bsize)"
    idx=$((idx + 1))
  done
  echo "==============================================="

  read -p "请选择要恢复的备份编号 (1-$((idx-1)))，回车默认 1: " backup_choice
  backup_choice=${backup_choice:-1}

  if ! [[ "$backup_choice" =~ ^[0-9]+$ ]] || [[ "$backup_choice" -lt 1 ]] || [[ "$backup_choice" -gt $((idx-1)) ]]; then
    echo "[ERROR] 无效的选择"
    return 1
  fi

  local backup_dir="${backups[$((backup_choice-1))]}"
  echo "  选择备份：$(basename "$backup_dir")"

  read -p "确认恢复？将覆盖当前数据 (y/N): " confirm
  if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
    echo "[ERROR] 取消恢复"
    return 0
  fi

  cd "$INSTALL_DIR"
  check_docker

  echo "[INFO] 停止服务..."
  docker stop mpay-app 2>/dev/null || true
  sleep 3

  if [[ -f "$backup_dir/mpay.db" ]]; then
    echo "[INFO] 恢复 SQLite 数据库..."
    docker run --rm -v mpay_data:/data -v "$backup_dir":/restore alpine sh -c "cp /restore/mpay.db /data/mpay.db" && echo "  mpay.db 已恢复"
  fi

  if [[ -f "$backup_dir/.env" ]]; then
    read -p "是否同时恢复 .env 配置文件？(Y/n): " restore_env
    if [[ "$restore_env" != "n" && "$restore_env" != "N" ]]; then
      cp "$backup_dir/.env" "$INSTALL_DIR/.env"
      echo "  .env 已恢复"
    fi
  fi

  echo "[INFO] 重启服务..."
  cd "$INSTALL_DIR"
  $DOCKER_CMD up -d

  echo "[OK] 数据恢复完成"
}

setup_caddy() {
  local domain="$1"
  local port="$2"

  echo "[INFO] 正在安装并配置 Caddy 服务 (域名: $domain)..."

  if ! command -v apt-get &> /dev/null; then
    echo "[ERROR] 当前系统不支持自动安装 Caddy（仅支持 Debian/Ubuntu）"
    echo "[WARN] 请手动安装 Caddy 并反向代理至 http://127.0.0.1:$port"
    return 1
  fi

  if ! command -v caddy &> /dev/null; then
    echo "[INFO] 未检测到 Caddy，正在安装..."
    sudo apt-get update
    sudo apt-get install -y debian-keyring debian-archive-keyring apt-transport-https curl gnupg
    curl -1sLf 'https://dl.cloudflare.com/cloudflare-main.gpg' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg 2>/dev/null || \
    curl -1sLf 'https://dl.cloudflare.com/content/v1/e2qwFJ2fRP2b2q/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudflare.com/content/v1/e2qwFJ2fRP2b2q/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
    sudo apt-get update
    sudo apt-get install -y caddy
  else
    echo "[OK] 检测到 Caddy 已安装"
  fi

  echo "[INFO] 正在写入 Caddyfile 配置..."
  sudo tee /etc/caddy/Caddyfile > /dev/null <<CADDY_EOF
${domain} {
    reverse_proxy http://127.0.0.1:${port} {
        header_up Host {host}
        header_up X-Forwarded-For {remote}
        header_up X-Forwarded-Proto {scheme}
    }
}
CADDY_EOF

  echo "[INFO] 重启 Caddy 服务..."
  sudo systemctl restart caddy

  echo "[OK] Caddy 配置成功！"
  echo "   访问地址：https://$domain"
  sleep 2
}

configure_caddy_interactive() {
  local domain port

  if [[ ! -d "$INSTALL_DIR" ]]; then
    echo "[ERROR] 未检测到 mpay 安装（$INSTALL_DIR 不存在）"
    return 1
  fi

  cd "$INSTALL_DIR"

  if [[ ! -f ".env" ]]; then
    echo "[ERROR] 未找到 .env 文件"
    return 1
  fi

  port=$(grep -m1 "^MPAY_PORT=" .env | cut -d= -f2)
  port=${port:-8088}

  echo "  当前 mpay 端口：$port"

  read -p "请输入绑定域名（例如 pay.example.com）： " domain
  while [[ -z "$domain" ]]; do
    echo "[ERROR] 域名不能为空"
    read -p "请输入域名： " domain
  done

  setup_caddy "$domain" "$port"
}

main() {
  if [[ $EUID -ne 0 ]]; then
    SUDO_CMD="sudo"
    echo "[WARN] 当前非 root 用户，部分操作可能需 sudo 权限"
  else
    SUDO_CMD=""
  fi

  install_download_tools

  if [[ "$1" == "uninstall" ]]; then
    uninstall_mpay "true"
    exit $?
  fi

  while true; do
    show_menu
    read -p "请选择操作 (1-7): " choice

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
        echo "[INFO] 退出脚本"
        exit 0
        ;;
      *)
        echo "[ERROR] 无效选项，请输入 1-7"
        ;;
    esac
  done
}

main "$@"
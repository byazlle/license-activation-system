#!/usr/bin/env bash
# ============================================================
# 软件激活与授权管理系统 · 宝塔面板一键部署脚本
# 适用：宝塔 Linux 面板 + Nginx + PHP 7.2~8.3 + MySQL/MariaDB
# 用法见：docs/INSTALL-安装部署教程.md
#
# 前提：请先在宝塔面板创建好数据库和用户（脚本不猜数据库密码）。
# ============================================================
set -euo pipefail

SITE="/www/wwwroot/license"
DOMAIN="_"
PORT="80"
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME=""
DB_USER=""
DB_PASS=""
PREFIX="lc_"
ADMIN="admin"
ADMIN_PASS=""
SRC="$(cd "$(dirname "$0")/.." && pwd)"
ZIP=""
PHP_BIN="php"
FPM_PASS="unix:/run/php-fpm/www.sock"
NGINX_CONF=""
WEB_USER=""

usage() {
    cat <<'EOF'
用法：bash bt-onekey.sh [参数]
必填：
  --db=许可证数据库名         例如 --db=license
  --db-user=数据库用户        例如 --db-user=license
  --db-pass=数据库密码        例如 --db-pass='StrongPass123'
  --admin-pass=后台密码       例如 --admin-pass='AdminPass123'
选填：
  --site=/www/wwwroot/license 程序安装目录（Web 根目录为其下的 public）
  --domain=example.com        站点域名，默认 _
  --port=80                   监听端口
  --db-host=127.0.0.1
  --db-port=3306
  --prefix=lc_                数据表前缀
  --admin=admin               后台用户名
  --src=/path/to/source       源码目录（默认脚本上一级目录）
  --zip=/path/to/package.zip  从 zip 包部署（优先于 --src）
  --php=php                   PHP 可执行文件路径
  --fpm=unix:/run/php-fpm/www.sock
  --web-user=apache           文件属主；默认从 FPM socket 自动探测
  --nginx-conf=路径           自定义 nginx 配置输出路径
EOF
    exit 1
}

for arg in "$@"; do
    case "$arg" in
        --site=*) SITE="${arg#*=}" ;;
        --domain=*) DOMAIN="${arg#*=}" ;;
        --port=*) PORT="${arg#*=}" ;;
        --db=*) DB_NAME="${arg#*=}" ;;
        --db-user=*) DB_USER="${arg#*=}" ;;
        --db-pass=*) DB_PASS="${arg#*=}" ;;
        --db-host=*) DB_HOST="${arg#*=}" ;;
        --db-port=*) DB_PORT="${arg#*=}" ;;
        --prefix=*) PREFIX="${arg#*=}" ;;
        --admin=*) ADMIN="${arg#*=}" ;;
        --admin-pass=*) ADMIN_PASS="${arg#*=}" ;;
        --src=*) SRC="${arg#*=}" ;;
        --zip=*) ZIP="${arg#*=}" ;;
        --php=*) PHP_BIN="${arg#*=}" ;;
        --fpm=*) FPM_PASS="${arg#*=}" ;;
        --web-user=*) WEB_USER="${arg#*=}" ;;
        --nginx-conf=*) NGINX_CONF="${arg#*=}" ;;
        -h|--help) usage ;;
        *) echo "未知参数：$arg"; usage ;;
    esac
done

[ -n "$DB_NAME" ] && [ -n "$DB_USER" ] && [ -n "$ADMIN_PASS" ] || usage
[ "$(id -u)" -eq 0 ] || { echo "请以 root 运行"; exit 1; }
[ -d /www/server/panel ] || { echo "未检测到宝塔面板目录 /www/server/panel"; exit 1; }
command -v nginx >/dev/null 2>&1 || { echo "未检测到 nginx"; exit 1; }
command -v "$PHP_BIN" >/dev/null 2>&1 || { echo "未检测到 PHP：$PHP_BIN"; exit 1; }

# 仅从真正的站点配置（含 root 指令）复用 fastcgi_pass；状态页等模板配置不参与。
# 若自动探测不到，保留默认值 unix:/run/php-fpm/www.sock，可用 --fpm 显式指定。
if [ "$FPM_PASS" = "unix:/run/php-fpm/www.sock" ]; then
    for conf in /etc/nginx/conf.d/license.conf /www/server/panel/vhost/nginx/*.conf /etc/nginx/conf.d/license*.conf; do
        [ -f "$conf" ] || continue
        if grep -q 'root[[:space:]]' "$conf" && grep -q 'fastcgi_pass' "$conf"; then
            CAND=$(grep 'fastcgi_pass' "$conf" | head -n 1 | sed 's/^[[:space:]]*//;s/;.*$//' | awk '{print $2}')
            if [ -n "$CAND" ] && [ -S "${CAND#unix:}" ]; then
                FPM_PASS="$CAND"
                break
            fi
        fi
    done
fi

# 取源码
TMP_SRC=""
if [ -n "$ZIP" ]; then
    [ -f "$ZIP" ] || { echo "zip 不存在：$ZIP"; exit 1; }
    TMP_SRC=$(mktemp -d)
    unzip -q "$ZIP" -d "$TMP_SRC" || true
    ROOT_SRC=$(find "$TMP_SRC" -mindepth 1 -maxdepth 3 -type d -name app 2>/dev/null | head -n 1 || true)
    if [ -z "$ROOT_SRC" ]; then
        echo "zip 中没有找到 app/ 目录，请确认是完整发布包"
        exit 1
    fi
    SRC=$(dirname "$ROOT_SRC")
fi
[ -f "$SRC/app/bootstrap.php" ] || { echo "源码目录不正确：$SRC"; exit 1; }

echo "[1/5] 部署文件到 $SITE（Web 根为 $SITE/public）"
mkdir -p "$SITE"
tar -C "$SRC" \
    --exclude='./config/config.php' \
    --exclude='./install/install.lock' \
    --exclude='./keys/*.pem' \
    --exclude='./.git' \
    --exclude='./.workbuddy' \
    --exclude='./storage/cache/*' \
    -cf - . | tar -C "$SITE" -xf -

# 属主：优先从 php-fpm socket 推断实际运行用户（宝塔常见 www 或 apache）
if [ -z "$WEB_USER" ]; then
    # 最可靠：正在运行的 php-fpm worker 属主
    WEB_USER=$(ps -eo user,args 2>/dev/null | grep 'php-fpm: pool' | grep -v grep | head -n 1 | awk '{print $1}' || true)
    # 其次：从 php-fpm 池配置读取
    if [ -z "$WEB_USER" ]; then
        for pc in /etc/php-fpm.d/*.conf /www/server/php/*/etc/php-fpm.conf; do
            [ -f "$pc" ] || continue
            WEB_USER=$(grep -E '^[[:space:]]*user[[:space:]]*=' "$pc" 2>/dev/null | head -n 1 | sed 's/.*=[[:space:]]*//;s/[[:space:]]*$//' || true)
            [ -n "$WEB_USER" ] && break
        done
    fi
    if [ -z "$WEB_USER" ]; then
        if id www >/dev/null 2>&1; then WEB_USER="www"; else WEB_USER="apache"; fi
    fi
fi
# socket 文件属主通常是 root，不能作为 web 用户；只允许已知账户
if [ "$WEB_USER" = "root" ]; then WEB_USER=""; fi
if [ -z "$WEB_USER" ]; then
    if id apache >/dev/null 2>&1; then WEB_USER="apache"; elif id www >/dev/null 2>&1; then WEB_USER="www"; else WEB_USER="root"; fi
fi
id "$WEB_USER" >/dev/null 2>&1 || { echo "Web 用户不存在：$WEB_USER，请用 --web-user 指定"; exit 1; }
chown -R "$WEB_USER":"$WEB_USER" "$SITE"
chmod -R u=rwX,go=rX "$SITE"
chmod 0700 "$SITE/keys"
chmod 0770 "$SITE/storage" "$SITE/storage/cache" 2>/dev/null || true
mkdir -p "$SITE/storage/cache" "$SITE/storage/sessions"
chown -R "$WEB_USER":"$WEB_USER" "$SITE/keys" "$SITE/storage"
echo "      文件属主：$WEB_USER"

echo "[2/5] 静默安装（建表、生成密钥 A 与初始 API 密钥）"
OUT=$("$PHP_BIN" "$SITE/install/cli_install.php" \
    --host="$DB_HOST" --port="$DB_PORT" --db="$DB_NAME" \
    --user="$DB_USER" --pass="$DB_PASS" --prefix="$PREFIX" \
    --admin="$ADMIN" --admin-pass="$ADMIN_PASS")
echo "      $OUT"
echo "$OUT" | grep -q '"ok":true' || { echo "安装失败，请检查数据库参数"; exit 1; }
chown "$WEB_USER":"$WEB_USER" "$SITE/config/config.php" "$SITE/keys"/*.pem "$SITE/install/initial_api_key.txt"
chmod 0600 "$SITE/keys/private_a.pem" "$SITE/install/initial_api_key.txt"

echo "[3/5] 写入 Nginx 站点配置"
if [ -z "$NGINX_CONF" ]; then
    SAFE=$(echo "$DOMAIN" | tr -cd 'a-zA-Z0-9._-' | head -c 60)
    [ -z "$SAFE" ] && SAFE="license"
    NGINX_CONF="/etc/nginx/conf.d/license-${SAFE}.conf"
fi
cat > "$NGINX_CONF" <<NGINX
server {
    listen ${PORT};
    server_name ${DOMAIN};
    root ${SITE}/public;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass ${FPM_PASS};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME ${SITE}/public\$fastcgi_script_name;
        include fastcgi_params;
        # 开放 API 的 Bearer 鉴权依赖此行
        fastcgi_param HTTP_AUTHORIZATION \$http_authorization;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
NGINX
echo "      配置：$NGINX_CONF"

echo "[4/5] 检查并重载 Nginx"
nginx -t && nginx -s reload

echo "[5/5] 完成"
echo "---------------------------------------------"
echo "访问地址   : http://${DOMAIN}:${PORT}/"
echo "后台账号   : ${ADMIN}"
echo "后台密码   : ${ADMIN_PASS}"
echo "API 密钥   : ${SITE}/install/initial_api_key.txt"
echo "开放 API   : http://${DOMAIN}:${PORT}/openapi/ping"
echo "---------------------------------------------"
echo "提示：install/ 与 keys/ 都在 Web 根之外，请勿把私钥放进 public/。"

[ -n "$TMP_SRC" ] && rm -rf "$TMP_SRC"
exit 0

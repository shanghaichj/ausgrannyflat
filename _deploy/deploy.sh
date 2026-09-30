#!/bin/bash
# ============================================================
# Aus Granny Flat 一键部署脚本 (适用于 Ubuntu / Debian)
# 用法:  sudo bash deploy.sh [你的域名]
#   例:  sudo bash deploy.sh www.example.com
#   不填域名则用服务器 IP 访问
# ============================================================
set -e
DOMAIN="${1:-}"

echo "==> [1/7] 检查并安装 Nginx + PHP (如未安装)"
if ! command -v nginx >/dev/null 2>&1; then
  apt-get update -y
  apt-get install -y nginx php-fpm php-cli php-curl unzip curl
fi

echo "==> [2/7] 部署站点文件"
ZIP=/tmp/ausgrannyflat_site_migration.zip
if [ -f "$ZIP" ]; then
  mkdir -p /var/www/ausgrannyflat
  unzip -o "$ZIP" -d /var/www/ausgrannyflat/
elif [ ! -f /var/www/ausgrannyflat/index.html ]; then
  echo "错误: 未找到 $ZIP , 且 /var/www/ausgrannyflat 中无站点文件"
  echo "请先将迁移包上传到 /tmp 再重试"; exit 1
else
  echo "检测到站点文件已就位, 跳过解压"
fi
chown -R www-data:www-data /var/www/ausgrannyflat 2>/dev/null || true

echo "==> [3/7] 生成 Nginx 配置"
PHP_SOCK=$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1)
[ -z "$PHP_SOCK" ] && PHP_SOCK="/run/php/php-fpm.sock"

cat > /etc/nginx/sites-available/ausgrannyflat <<EOF
server {
    listen 80;
    server_name ${DOMAIN:-_};
    root /var/www/ausgrannyflat;
    index index.html;

    location ~* \.(css|js|woff2|jpg|jpeg|png|webp|gif|ico|svg)$ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        try_files \$uri =404;
    }

    location = / { try_files /index.html =404; }
    location = /products/granny-flat { try_files /products_granny-flat.html =404; }
    location = /contact { try_files /contact.html =404; }

    location = /api/admin/products {
        alias /var/www/ausgrannyflat/_data/products.json;
        default_type application/json;
        add_header Cache-Control "no-cache";
    }
    location = /api/rolling-images {
        alias /var/www/ausgrannyflat/_data/rolling-images.json;
        default_type application/json;
        add_header Cache-Control "no-cache";
    }
    location = /api/intro-images {
        alias /var/www/ausgrannyflat/_data/intro-images.json;
        default_type application/json;
        add_header Cache-Control "no-cache";
    }
    location = /api/contact {
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$document_root/_deploy/contact-handler.php;
        include fastcgi_params;
        fastcgi_read_timeout 30s;
    }

    location / { try_files \$uri \$uri/ =404; }

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
EOF

ln -sf /etc/nginx/sites-available/ausgrannyflat /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

echo "==> [4/7] 测试并重载 Nginx"
nginx -t
systemctl reload nginx

echo "==> [5/7] 表单日志目录"
mkdir -p /var/www/ausgrannyflat/_deploy/logs
chown -R www-data:www-data /var/www/ausgrannyflat/_deploy 2>/dev/null || true

echo "==> [6/7] 开机自启"
systemctl enable nginx 2>/dev/null || true

echo "==> [7/7] 本机验证"
echo "--- 首页 HTTP 状态 ---"
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1/
echo "--- 产品页 HTTP 状态 ---"
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1/products/granny-flat
echo "--- 联系页 HTTP 状态 ---"
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1/contact
echo "--- 产品 API 前 120 字符 ---"
curl -s http://127.0.0.1/api/admin/products | head -c 120; echo ""
echo "--- 表单 API (POST 测试) ---"
curl -s -X POST http://127.0.0.1/api/contact -H "Content-Type: application/json" -d '{"name":"test","email":"test@test.com","message":"deploy test"}'; echo ""

IP=$(hostname -I 2>/dev/null | awk '{print $1}')
echo ""
echo "=========================================="
echo " 部署完成!"
echo " 访问地址: http://${DOMAIN:-$IP}"
echo " 表单提交记录: /var/www/ausgrannyflat/_deploy/logs/contact-messages.log"
echo " 提示: 邮件通知需编辑 contact-handler.php 填入 SMTP 账号"
echo "=========================================="

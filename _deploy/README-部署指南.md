# Aus Granny Flat 网站搬迁部署指南

> 源站: https://ausgrannyflat.coze.site (Coze 托管, Next.js)
> 目标: 腾讯云服务器 (CVM + Nginx, 或宝塔面板)

## 一、包内结构

```
ausgrannyflat_site/
├── index.html                  # 首页
├── products_granny-flat.html   # 产品页 (Granny Flat)
├── contact.html                # 联系页
├── _next/                      # Next.js 静态资源 (CSS/JS/字体)
├── _data/                      # 固化后的 API 数据
│   ├── products.json           #   13 个产品(中英双语)
│   ├── rolling-images.json     #   8 张轮播图
│   └── intro-images.json       #   5 张介绍图
├── GrannyFlat/                 # 产品户型图 (70sqm/60sqm)
├── Box Series/                 # Box 系列户型图
├── Rolling Image1/             # 首页轮播图
├── Rolling Image2/             # 公司介绍图
├── logo.png / hero-1.jpeg / service-*.jpeg / 客服.png / favicon.ico
└── _deploy/
    ├── nginx-ausgrannyflat.conf  # Nginx 站点配置
    ├── contact-handler.php       # 联系表单后端 (SMTP 发邮件)
    └── 本文件
```

## 二、部署步骤 (Linux CVM)

### 1. 上传文件
```bash
# 将 ausgrannyflat_site 包上传到服务器后:
sudo mkdir -p /var/www/ausgrannyflat
sudo cp -r ausgrannyflat_site/* /var/www/ausgrannyflat/
sudo chown -R www-data:www-data /var/www/ausgrannyflat   # 宝塔则用 www:www
```

### 2. 配置 Nginx
```bash
sudo cp /var/www/ausgrannyflat/_deploy/nginx-ausgrannyflat.conf /etc/nginx/conf.d/
sudo nginx -t && sudo systemctl reload nginx
```
> 宝塔用户: 在"网站"中添加站点指向 `/var/www/ausgrannyflat`，把 conf 里的
> `location` 配置粘贴到站点的"配置文件"中，并删除宝塔默认的 index 处理。

### 3. 联系表单 (可选但推荐)
```bash
cd /var/www/ausgrannyflat/_deploy
# 安装 PHPMailer (需要 composer; 也可手动上传 PHPMailer 源码)
composer require phpmailer/phpmailer
```
然后编辑 `contact-handler.php`, 填入你的发件邮箱 + SMTP 授权码:
- `YOUR_SENDER_EMAIL` → 发件邮箱 (如 QQ 邮箱)
- `YOUR_SMTP_AUTH_CODE` → SMTP 授权码
- 确认 `fastcgi_pass` 的 PHP-FPM socket 版本与服务器一致

> 即使不配邮件, 表单也会先落盘到 `_deploy/logs/contact-messages.log`, 数据不丢。

### 4. 域名 + HTTPS
- 解析域名 A 记录到服务器 IP
- 修改 conf 中 `YOUR_DOMAIN` → 你的域名
- 申请 SSL 证书: 腾讯云控制台免费证书 / certbot, 配置 443 端口
- **注意**: 域名若解析到中国大陆服务器需完成 ICP 备案, 否则 80/443 会被拦截

## 三、技术说明

| 项目 | 原站 (Coze) | 迁移后 (腾讯云) |
|---|---|---|
| 页面 | Next.js SSR | 静态 HTML (预渲染, 已抓全) |
| 产品数据 | /api/admin/products | _data/products.json (Nginx 映射) |
| 轮播/介绍图 | /api/rolling-images 等 | _data/*.json (Nginx 映射) |
| 联系表单 | /api/contact (Coze FaaS) | contact-handler.php (SMTP) |
| 分析脚本 | Coze/火山埋点 | 已移除 |

页面 JS 无需任何改动 —— 它请求的 `/api/*` 路径由 Nginx 直接指向静态 JSON,
前端逻辑原样工作。

## 四、验证清单

- [ ] `http://域名/` 首页完整, 轮播图可滚动
- [ ] `http://域名/products/granny-flat` 13 个产品卡片 + 图片正常
- [ ] `http://域名/contact` 提交表单 → 收到邮件 (或 logs 有记录)
- [ ] HTTPS 证书生效, 无混合内容警告
- [ ] 移动端菜单、语言切换正常

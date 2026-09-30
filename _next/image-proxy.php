<?php
/**
 * Next.js 图片优化器静态替代端点
 * 处理 /_next/image?url=<encoded>&w=<n>&q=<n>
 * 解码 url 参数后直接输出站点内的本地原图（不做实际缩放，仅做转发）。
 */
header('Access-Control-Allow-Origin: *');

$url = $_GET['url'] ?? '';
$path = urldecode($url);

// 安全限制：仅允许站点内的绝对路径，禁止目录穿越与远程地址
if (
    strpos($path, '/') !== 0 ||
    strpos($path, '..') !== false ||
    strpos($path, '://') !== false
) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'invalid url';
    exit;
}

$root = '/var/www/ausgrannyflat';
$file = $root . $path;

if (!is_file($file)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'not found';
    exit;
}

$mime = mime_content_type($file);
if (!$mime) {
    $mime = 'application/octet-stream';
}
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=2592000, immutable');
readfile($file);
exit;

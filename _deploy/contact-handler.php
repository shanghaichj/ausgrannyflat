<?php
/**
 * Aus Granny Flat - 联系表单处理脚本
 * 接收 POST /api/contact 的 JSON 数据, 做两件事:
 *   1) 追加保存到服务器本地 logs/contact-messages.log (兜底, 永不丢失)
 *   2) 通过 SMTP 发送邮件通知到业务邮箱 (chj@pukai.com)
 *
 * 依赖: PHPMailer (composer require phpmailer/phpmailer)
 * 部署: 将本文件放在站点 _deploy/ 目录, 并在此目录执行 composer install
 * 配置: 修改下方 SMTP 账号为你的发件邮箱(推荐 QQ邮箱/腾讯企业邮, 需开启 SMTP 授权码)
 */

header('Content-Type: application/json');

// ---------- 读取请求 ----------
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST; // 兼容 form-urlencoded
}

$name    = trim($data['name'] ?? '');
$company = trim($data['company'] ?? '');
$email   = trim($data['email'] ?? '');
$phone   = trim($data['phone'] ?? '');
$message = trim($data['message'] ?? '');

if ($name === '' || $email === '' || $message === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'name, email, message are required']);
    exit;
}

$line = sprintf(
    "[%s] Name=%s | Company=%s | Email=%s | Phone=%s | Message=%s\n",
    date('Y-m-d H:i:s'), $name, $company, $email, $phone, str_replace(["\r", "\n"], ' ', $message)
);

// ---------- 1) 落盘兜底 ----------
$logDir  = __DIR__ . '/logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
@file_put_contents($logDir . '/contact-messages.log', $line, FILE_APPEND);

// ---------- 2) SMTP 发邮件 ----------
$mailSent = false;
$smtpError = '';
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.qq.com';            // SMTP 服务器(QQ邮箱示例)
        $mail->SMTPAuth   = true;
        $mail->Username   = 'YOUR_SENDER_EMAIL';      // 发件邮箱
        $mail->Password   = 'YOUR_SMTP_AUTH_CODE';    // SMTP 授权码(不是邮箱密码!)
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom('YOUR_SENDER_EMAIL', 'Aus Granny Flat Website');
        $mail->addAddress('chj@pukai.com');           // 收件人: 业务邮箱
        $mail->isHTML(true);
        $mail->Subject = "【官网询盘】$name" . ($company ? " ($company)" : '');
        $mail->Body    = "<h3>Aus Granny Flat 官网新询盘</h3>
            <p><b>姓名:</b> " . htmlspecialchars($name) . "</p>
            <p><b>公司:</b> " . htmlspecialchars($company) . "</p>
            <p><b>邮箱:</b> " . htmlspecialchars($email) . "</p>
            <p><b>电话:</b> " . htmlspecialchars($phone) . "</p>
            <p><b>留言:</b><br>" . nl2br(htmlspecialchars($message)) . "</p>
            <p><small>时间: " . date('Y-m-d H:i:s') . "</small></p>";
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $mail->Body));
        $mail->send();
        $mailSent = true;
    } catch (Exception $e) {
        $smtpError = $e->getMessage();
    }
}

echo json_encode(['ok' => true, 'saved' => true, 'email_sent' => $mailSent, 'error' => $smtpError ?: null]);

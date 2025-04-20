<?php
include __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$logs_dir = __DIR__ . '/../logs';
if (!file_exists($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}

if (!ini_get('error_log')) {
    ini_set('error_log', $logs_dir . '/notifications_logs.php');
}

/**
 * @param string $path
 * @return void
 */
function loadEnv($path = __DIR__ . '../.env')
{
    if (!file_exists($path)) {
        error_log("ENV file not found at: {$path}");
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!getenv($name)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

/**
 * @return PHPMailer
 */
function getConfiguredMailer()
{
    loadEnv();

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_USERNAME') ?: 'luckynestbookings@gmail.com';
    $mail->Password = getenv('SMTP_PASSWORD');
    $mail->SMTPSecure = 'tls';
    $mail->Port = getenv('SMTP_PORT') ?: 587;

    $mail->setFrom(getenv('SMTP_USERNAME') ?: 'luckynestbookings@gmail.com', 'LuckyNest Notifications');

    $mail->isHTML(true);

    return $mail;
}
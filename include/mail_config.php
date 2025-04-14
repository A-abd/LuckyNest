<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * @param string $path
 * @return void
 */
function loadEnv($path = '../.env')
{
    if (!file_exists($path)) {
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

    $mail->action_function = function () {
        if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false) {
            echo "<script>
                const sound = new Audio('/LuckyNest/assets/sounds/notification.mp3');
                sound.play().catch(error => {
                    console.error('Failed to play notification sound:', error);
                });
            </script>";
            flush();
        }
    };

    return $mail;
}
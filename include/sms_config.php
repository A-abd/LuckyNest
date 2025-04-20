<?php
use Twilio\Rest\Client;

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
function loadEnvSMS($path = __DIR__ . '../.env')
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
 * @param string $to Recipient phone number (E.164 format)
 * @param string $message SMS content
 * @return bool True if SMS was sent successfully
 * @throws Exception If SMS sending fails
 */
function sendSMS($to, $message)
{
    loadEnvSMS();

    $account_sid = getenv('TWILIO_ACCOUNT_SID');
    $auth_token = getenv('TWILIO_AUTH_TOKEN');
    $twilio_number = getenv('TWILIO_NUMBER');

    if (empty($account_sid) || empty($auth_token) || empty($twilio_number)) {
        $error_msg = "Missing Twilio configuration. Please check your .env file.";
        error_log($error_msg);
        throw new Exception($error_msg);
    }

    try {
        $client = new Client($account_sid, $auth_token);

        $message = $client->messages->create(
            $to,
            [
                'from' => $twilio_number,
                'body' => $message,
            ]
        );

        error_log("SMS sent successfully to {$to}. Message SID: {$message->sid}");

        return true;
    } catch (Exception $e) {
        error_log("Failed to send SMS to {$to}: " . $e->getMessage());
        throw $e;
    }
}
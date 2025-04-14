<?php
use Twilio\Rest\Client;

/**
 * @param string $path
 * @return void
 */
function loadEnvSMS($path = '../.env') {
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
 * Send SMS using Twilio API
 * 
 * @param string $to Recipient phone number (E.164 format)
 * @param string $message SMS content
 * @return bool True if SMS was sent successfully
 * @throws Exception If SMS sending fails
 */
function sendSMS($to, $message) {
    // Load environment variables if not already loaded
    loadEnvSMS();
    
    $account_sid = getenv('TWILIO_ACCOUNT_SID');
    $auth_token = getenv('TWILIO_AUTH_TOKEN');
    $twilio_number = getenv('TWILIO_NUMBER');
    
    // Check if required configuration exists
    if (empty($account_sid) || empty($auth_token) || empty($twilio_number)) {
        throw new Exception("Missing Twilio configuration. Please check your .env file.");
    }
    
    try {
        // Initialize the Twilio client
        $client = new Client($account_sid, $auth_token);
        
        // Send the message
        $message = $client->messages->create(
            $to,
            [
                'from' => $twilio_number,
                'body' => $message,
            ]
        );
        
        // Log the message SID for tracking
        error_log("SMS sent successfully to {$to}. Message SID: {$message->sid}");
        
        if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false) {
            echo "<script>
                const sound = new Audio('/LuckyNest/assets/sounds/notification.mp3');
                sound.play().catch(error => {
                    console.error('Failed to play notification sound:', error);
                });
            </script>";
            flush();
        }
        
        return true;
    } catch (Exception $e) {
        // Log the error and re-throw it
        error_log("Failed to send SMS to {$to}: " . $e->getMessage());
        throw $e;
    }
}
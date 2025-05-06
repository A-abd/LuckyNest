<?php
include_once __DIR__ . '/db.php';
include_once __DIR__ . '/notification_utils.php';

function generateResetToken()
{
    return bin2hex(random_bytes(32));
}

function validatePassword($password)
{
    $errors = [];

    if (strlen($password) < 5) {
        $errors[] = "Password must be at least 5 characters long";
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }

    if (!preg_match('/[^a-zA-Z\d]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }

    return $errors;
}

function storeResetToken($email, $token, $conn)
{
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return false;
    }

    $user_id = $user['user_id'];

    $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expiry) VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    return true;
}

function verifyResetToken($token, $email, $conn)
{
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return false;
    }

    $user_id = $user['user_id'];

    $stmt = $conn->prepare("SELECT * FROM password_reset_tokens WHERE user_id = :user_id AND token = :token AND expiry > NOW()");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC) ? $user_id : false;
}

function sendPasswordResetEmail($email, $token, $conn, $method = 'email')
{
    try {
        $stmt = $conn->prepare("SELECT user_id, forename, surname, phone FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return false;
        }

        if (!storeResetToken($email, $token, $conn)) {
            return false;
        }

        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/LuckyNest/authentication/reset_page?token=" . $token . "&email=" . urlencode($email);

        $message = '<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your LuckyNest Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #2c3e50;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
        }
        .content {
            padding: 20px;
            background-color: #ffffff;
        }
        .footer {
            background-color: #f5f5f5;
            padding: 15px;
            text-align: center;
            font-size: 0.9em;
            color: #777;
        }
        .button {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            margin: 20px 0;
        }
        .details {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .warning {
            color: #e74c3c;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>LuckyNest Password Reset</h1>
        </div>
        <div class="content">
            <p>Hello ' . htmlspecialchars($user['forename']) . ',</p>
            <p>We received a request to reset your password for your LuckyNest account. To complete the password reset process, please click the button below:</p>
            
            <div style="text-align: center;">
                <a href="' . $resetLink . '" class="button">Reset Your Password</a>
            </div>
            
            <div class="details">
                <p>If the button above doesn\'t work, copy and paste the following link into your browser:</p>
                <p style="word-break: break-all;">' . $resetLink . '</p>
                <p class="warning">This link will expire in 1 hour for security reasons.</p>
            </div>
            
            <p>If you didn\'t request a password reset, you can safely ignore this email. Your password will remain unchanged.</p>
        </div>
        <div class="footer">
            <p>Thank you for choosing LuckyNest!</p>
            <p>&copy; ' . date('Y') . ' LuckyNest. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';

        $subject = "Reset Your LuckyNest Password";
        $sms_message = "LuckyNest: Password reset requested. Click to reset: " . $resetLink . " (Link expires in 1 hour)";
        $notification_message = "A password reset was requested for your account. Check your " . ($method == 'email' ? 'email' : 'phone') . " for instructions.";

        // Override normal notification settings based on chosen method
        if ($method == 'email') {
            // Send only email, not SMS
            $results = [
                'email' => false,
                'sms' => false,
                'notification' => false
            ];

            try {
                $mail = getConfiguredMailer();
                $mail->addAddress($email, $user['forename'] . ' ' . $user['surname']);
                $mail->Subject = $subject;
                $mail->Body = $message;
                $mail->send();
                $results['email'] = true;

                // Still add to notifications table
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
                $stmt->bindValue(':user_id', $user['user_id'], PDO::PARAM_INT);
                $stmt->bindValue(':message', $notification_message, PDO::PARAM_STR);
                $stmt->execute();
                $results['notification'] = true;

                error_log("Password reset email sent to {$email} for user {$user['user_id']}");
            } catch (Exception $e) {
                error_log("Failed to send password reset email to {$email} for user {$user['user_id']}: " . $e->getMessage());
                return false;
            }
        } else {
            // Send only SMS, not email
            $results = [
                'email' => false,
                'sms' => false,
                'notification' => false
            ];

            try {
                $formatted_phone = $user['phone'];
                // If the phone number doesn't start with +, add +44
                if (substr($formatted_phone, 0, 1) !== '+') {
                    $formatted_phone = preg_replace('/^0/', '', $formatted_phone);
                    $formatted_phone = '+44' . $formatted_phone;
                }

                sendSMS($formatted_phone, $sms_message);
                $results['sms'] = true;

                // Still add to notifications table
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
                $stmt->bindValue(':user_id', $user['user_id'], PDO::PARAM_INT);
                $stmt->bindValue(':message', $notification_message, PDO::PARAM_STR);
                $stmt->execute();
                $results['notification'] = true;

                error_log("Password reset SMS sent to {$formatted_phone} for user {$user['user_id']}");
            } catch (Exception $e) {
                error_log("Failed to send password reset SMS to {$user['phone']} for user {$user['user_id']}: " . $e->getMessage());
                return false;
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Error sending password reset notification: " . $e->getMessage());
        return false;
    }
}

function updatePassword($user_id, $new_password, $conn)
{
    try {
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = :password WHERE user_id = :user_id");
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':user_id', $user_id);
        $result = $stmt->execute();

        if ($result) {
            $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            $stmt = $conn->prepare("SELECT email, forename, surname FROM users WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $subject = "Your LuckyNest Password Has Been Changed";
            $html_message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .header { color: #2c3e50; }
                        .details { background-color: #f9f9f9; padding: 15px; border-radius: 5px; }
                        .footer { margin-top: 20px; font-size: 0.9em; color: #7f8c8d; }
                        .button { background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
                        .warning { color: #e74c3c; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <h1 class='header'>Password Successfully Changed</h1>
                    <p>Hello " . htmlspecialchars($user['forename'] . ' ' . $user['surname']) . ",</p>
                    <p>This is a confirmation that the password for your LuckyNest account has been successfully changed.</p>
                    
                    <div class='details'>
                        <p>If you did not make this change, please contact us immediately by replying to this email or calling our support team.</p>
                    </div>
                    
                    <p><a href='http://" . $_SERVER['HTTP_HOST'] . "' class='button'>Go to LuckyNest</a></p>
                    
                    <div class='footer'>
                        <p>Thank you for choosing LuckyNest!</p>
                    </div>
                </body>
                </html>
            ";

            $sms_message = "LuckyNest: Your password has been successfully changed. If you did not make this change, please contact support immediately.";

            $notification_message = "Your password has been successfully changed.";

            sendUserNotifications($conn, $user_id, $subject, $html_message, $sms_message, $notification_message, 'password_changed');
        }

        return $result;
    } catch (Exception $e) {
        error_log("Error updating password: " . $e->getMessage());
        return false;
    }
}

function handlePasswordResetRequest($email, $conn, $method = 'email')
{
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $token = generateResetToken();
        return sendPasswordResetEmail($email, $token, $conn, $method);
    }

    return true;
}

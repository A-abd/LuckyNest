How to setup payments_page.php:
     1. Make a .env file in the webroot 
     e.g. "C:\xampp\htdocs\LuckyNest\.env" here .env is my .env file
     2. Insert stripe secret keys or stripe restricted keys, each with a reference assigned with one key assigned as a fallback key.
          e.g. stripe_first_key = rk_test_...
               stripe_second_key = rk_test_...

               stripe_fallback_key = rk_test_...



How to setup phpmailer:
     1. Create an app password in your Google Account https://myaccount.google.com/apppasswords
     2. Configue PHPmailer with these settings:
               $mail->Host       = 'smtp.gmail.com';
               $mail->SMTPAuth   = true;
               $mail->Username   = 'your@gmail.com';
               $mail->Password   = 'your-app-password';
               $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
               $mail->Port       = 587;
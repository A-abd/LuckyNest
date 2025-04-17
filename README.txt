FIRSTLY CREATE YOUR OWN ".env" FILE IN THE WEBROOT
e.g. "C:\xampp\htdocs\LuckyNest\.env" here .env is my .env file

How to setup payments_page.php:
     1. Copy paste the following and replace the 'rk_test_...' part with your own Stripe API keys:
          stripe_first_key = rk_test_...
          stripe_second_key = rk_test_...
          stripe_third_key = rk_test_...
          stripe_fourth_key = rk_test_...
          stripe_fallback_key = rk_test_...



How to setup phpmailer:
     1. Create an app password in your Google Account https://myaccount.google.com/apppasswords
     2. Configue SMTP settings in your .env file:
          SMTP_HOST=smtp.gmail.com
          SMTP_PORT=587
          SMTP_USERNAME=your@gmail.com
          SMTP_PASSWORD=your-app-password


How to setup the automated notifications:
     1. Open up Windows Task Scheduler by searching for "Task Scheduler" in the start menu
     2. Click "Create a Basic Task" on the right
     3. Choose when you want to run it and click next
     4. Then choose what frequency you want to run it and click Next
     5. Select start a program and click next
     6. In the program/script section add: C:\xampp\php\php.exe (you may have to adjust the path to the file)
     7. In add arguments enter: C:\xampp\htdocs\LuckyNest\notification_scheduler.php (you may have to adjust the path to the file)
     8. Click Next, then finish
     9. Create a notification_logs.php file in the logs folder (although not necessary as the scripts should automatically create it)

     Note: you can manually run it via "C:\xampp\php\php.exe C:\xampp\htdocs\LuckyNest\notification_scheduler.php" (you may have to adjust the path to the file)
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
In case autoload fails:
1. Install composer
2. Install all dependencies by using the following command:



How to setup payments_page.php:
1. Make a .env file in the webroot 
e.g. "C:\xampp\htdocs\LuckyNest\.env" here .env is my .env file
2. Insert stripe secret keys or stripe restricted keys, each with a reference assigned with one key assigned as a fallback key.
e.g. stripe_first_key = rk_test_...
     stripe_second_key = rk_test_...

     stripe_fallback_key = rk_test_...
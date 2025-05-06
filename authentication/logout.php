<?php
session_start();
session_destroy();
header('Location: ../authentication/login');
exit();
?>
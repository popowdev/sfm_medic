<?php
require_once 'auth.php';
logout_user();
header("Location: /");
exit;

<?php
declare(strict_types=1);

 
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../php_errors.log');
ini_set('display_errors', '0');
error_reporting(E_ALL);

echo "DEBUG OK (PHP s'exécute)<br>";

 
echo "1) require db.php...<br>";
require_once __DIR__ . '/../db.php';
echo "OK db.php<br>";

echo "2) require auth.php...<br>";
require_once __DIR__ . '/../auth.php';
echo "OK auth.php<br>";

echo "3) require csrf.php...<br>";
require_once __DIR__ . '/../csrf.php';
echo "OK csrf.php<br>";

echo "4) require_admin...<br>";
require_admin();
echo "OK require_admin<br>";

echo "5) DB ping...<br>";
$v = db()->query("SELECT VERSION() AS v")->fetch();
echo "DB OK: " . htmlspecialchars($v['v'] ?? 'unknown', ENT_QUOTES, 'UTF-8') . "<br>";

echo "FIN OK";
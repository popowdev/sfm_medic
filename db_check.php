<?php
require_once __DIR__ . '/db.php';

$pdo = db();

echo "<h2>Database used by site:</h2>";
echo $pdo->query("SELECT DATABASE()")->fetchColumn();

echo "<h2>Tables:</h2>";

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $t) {
    echo $t . "<br>";
}
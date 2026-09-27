<?php
declare(strict_types=1);

$path = __DIR__ . '/../php_errors.log';
$ok = @file_put_contents($path, "LOG TEST " . date('c') . "\n", FILE_APPEND);

header('Content-Type: text/plain; charset=utf-8');

if ($ok === false) {
  echo "FAILED writing to: $path\n";
  echo "Try writing to /tmp...\n";
  $ok2 = @file_put_contents('/tmp/samr_log_test.log', "TMP LOG TEST " . date('c') . "\n", FILE_APPEND);
  echo $ok2 === false ? "FAILED writing to /tmp\n" : "OK writing to /tmp/samr_log_test.log\n";
} else {
  echo "OK writing to: $path\n";
}
<?php
require_once('../../../wp-load.php');

$log_file = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-content/debug.log';
$message = date('[Y-m-d H:i:s] ') . "=== TEST DEBUG ===\n";
$message .= date('[Y-m-d H:i:s] ') . "Si ves este mensaje, el logging está funcionando\n";
$message .= date('[Y-m-d H:i:s] ') . "=== END TEST ===\n";

file_put_contents($log_file, $message, FILE_APPEND);

echo "Test completado. Revisa wp-content/debug.log"; 
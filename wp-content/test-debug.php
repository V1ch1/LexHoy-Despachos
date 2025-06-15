<?php
require_once('../../../wp-load.php');

error_log('=== TEST DEBUG ===');
error_log('Si ves este mensaje, el logging está funcionando');
error_log('=== END TEST ===');

echo "Test completado. Revisa wp-content/debug.log"; 
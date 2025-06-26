<?php
/**
 * Script temporal para habilitar WordPress debug
 * Subir a la raíz del sitio y ejecutar una vez
 */

$wp_config_path = __DIR__ . '/wp-config.php';

if (!file_exists($wp_config_path)) {
    die("❌ wp-config.php no encontrado en: " . __DIR__);
}

$config_content = file_get_contents($wp_config_path);

// Buscar si ya tiene debug habilitado
if (strpos($config_content, "define('WP_DEBUG', true)") !== false) {
    echo "✅ WP_DEBUG ya está habilitado\n";
} else {
    // Agregar configuración de debug antes de la línea "/* That's all"
    $debug_config = "
// Debug habilitado temporalmente
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);

";
    
    $config_content = str_replace(
        "/* That's all, stop editing!",
        $debug_config . "/* That's all, stop editing!",
        $config_content
    );
    
    if (file_put_contents($wp_config_path, $config_content)) {
        echo "✅ WordPress debug habilitado\n";
        echo "📝 Logs aparecerán en: /wp-content/debug.log\n";
        echo "⚠️  Visita tu sitio para generar el error\n";
        echo "🔧 Después ejecuta disable-wp-debug.php\n";
    } else {
        echo "❌ Error escribiendo wp-config.php\n";
    }
}
?> 
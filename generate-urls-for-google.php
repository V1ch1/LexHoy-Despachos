<?php
/**
 * Script para generar URLs de despachos para indexación en Google
 * Ejecutar desde: wp-admin > Despachos > Generar URLs para Google
 */

// Cargar WordPress
$wp_config_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-config.php';
if (file_exists($wp_config_path)) {
    require_once($wp_config_path);
}

// Verificar que WordPress esté cargado
if (!function_exists('get_posts')) {
    die('Error: WordPress no está cargado correctamente.');
}

echo "🔍 GENERADOR DE URLs PARA INDEXACIÓN EN GOOGLE\n";
echo "===========================================\n\n";

// Obtener todos los despachos publicados
$despachos = get_posts(array(
    'post_type' => 'despacho',
    'post_status' => 'publish',
    'numberposts' => -1,
    'orderby' => 'title',
    'order' => 'ASC'
));

if (empty($despachos)) {
    echo "❌ No se encontraron despachos publicados.\n";
    exit;
}

echo "✅ Encontrados " . count($despachos) . " despachos publicados\n\n";

// Generar lista de URLs
$urls = array();
$sitemap_urls = array();

echo "📋 URLS PARA GOOGLE SEARCH CONSOLE:\n";
echo "===================================\n";

foreach ($despachos as $despacho) {
    $url = home_url('/' . $despacho->post_name . '/');
    $urls[] = $url;
    
    // Datos para sitemap
    $modified = get_the_modified_date('Y-m-d\TH:i:s+00:00', $despacho->ID);
    $sitemap_urls[] = array(
        'url' => $url,
        'modified' => $modified,
        'title' => get_the_title($despacho->ID)
    );
    
    echo $url . "\n";
}

echo "\n";
echo "📊 ESTADÍSTICAS:\n";
echo "================\n";
echo "Total de URLs: " . count($urls) . "\n";
echo "Sitemap XML disponible en: " . home_url('/despachos-sitemap.xml') . "\n";
echo "Fecha de generación: " . date('Y-m-d H:i:s') . "\n\n";

// Generar archivo de texto con las URLs
$filename = 'despachos-urls-' . date('Y-m-d-H-i-s') . '.txt';
$filepath = __DIR__ . '/' . $filename;

$content = "# URLs de Despachos para Google Search Console\n";
$content .= "# Generado el: " . date('Y-m-d H:i:s') . "\n";
$content .= "# Total: " . count($urls) . " URLs\n\n";

foreach ($urls as $url) {
    $content .= $url . "\n";
}

file_put_contents($filepath, $content);

echo "📄 Archivo generado: $filename\n";
echo "📍 Ubicación: $filepath\n\n";

// Instrucciones para usar con Google Search Console
echo "🚀 INSTRUCCIONES PARA GOOGLE SEARCH CONSOLE:\n";
echo "===========================================\n";
echo "1. Ve a https://search.google.com/search-console/\n";
echo "2. Selecciona tu propiedad (lexhoy.com)\n";
echo "3. Ve a 'Inspección de URLs' en el menú lateral\n";
echo "4. Para indexar TODAS las URLs de una vez:\n";
echo "   - Ve a 'Sitemaps' en el menú lateral\n";
echo "   - Agrega este sitemap: despachos-sitemap.xml\n";
echo "   - Google indexará automáticamente todas las URLs\n\n";

echo "5. Para indexar URLs MANUALMENTE (una por una):\n";
echo "   - Copia cada URL de arriba\n";
echo "   - Pégala en 'Inspección de URLs'\n";
echo "   - Haz clic en 'Solicitar indexación'\n\n";

echo "📈 CONFIGURACIÓN RANK MATH RECOMENDADA:\n";
echo "=====================================\n";
echo "1. Ve a Rank Math > Sitemap Settings\n";
echo "2. Asegúrate de que 'despacho' esté habilitado en el sitemap\n";
echo "3. Configura la frecuencia de actualización a 'Monthly'\n";
echo "4. Prioridad: 0.8 (ya configurada automáticamente)\n\n";

echo "✅ ¡Listo! Ahora puedes indexar tus despachos en Google.\n";
?> 
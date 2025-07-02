<?php
/**
 * Test específico para metadatos de fotos - Ambos entornos
 */

// Cargar WordPress
require_once('../../../wp-config.php');

// Detectar entorno
$is_local = (strpos($_SERVER['HTTP_HOST'], 'lexhoy.local') !== false);
$env_name = $is_local ? 'DESARROLLO' : 'PRODUCCIÓN';
$base_url = $is_local ? 'http://lexhoy.local' : 'https://lexhoy.com';

echo "<h1>🔍 Test de Fotos de Perfil - {$env_name}</h1>";
echo "<p><strong>Entorno:</strong> {$env_name}</p>";
echo "<p><strong>URL base:</strong> {$base_url}</p>";

// Contar despachos total
$total_despachos = wp_count_posts('despacho')->publish;
echo "<p><strong>Total despachos:</strong> {$total_despachos}</p>";

// Verificar metadatos de fotos
global $wpdb;
$fotos_query = "SELECT COUNT(*) as total FROM {$wpdb->postmeta} WHERE meta_key = '_despacho_foto_perfil' AND meta_value != ''";
$fotos_count = $wpdb->get_var($fotos_query);

echo "<p><strong>Despachos con foto asignada:</strong> {$fotos_count}</p>";
echo "<p><strong>Despachos sin foto:</strong> " . ($total_despachos - $fotos_count) . "</p>";

// Mostrar algunos ejemplos
echo "<h2>📊 Ejemplos de Metadatos</h2>";

$sample_limit = $is_local ? 5 : 3; // Menos ejemplos en producción
$despachos_sample = get_posts(array(
    'post_type' => 'despacho',
    'numberposts' => $sample_limit,
    'post_status' => 'publish',
    'orderby' => 'ID',
    'order' => 'DESC'
));

echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>Post ID</th>";
echo "<th>Título</th>";
echo "<th>Tiene Foto</th>";
echo "<th>URL (primeros 50 chars)</th>";
echo "</tr>";

foreach ($despachos_sample as $despacho) {
    $post_id = $despacho->ID;
    $titulo = $despacho->post_title;
    $foto = get_post_meta($post_id, '_despacho_foto_perfil', true);
    
    $tiene_foto = !empty($foto) ? '✅ SÍ' : '❌ NO';
    $foto_preview = !empty($foto) ? substr($foto, 0, 50) . '...' : 'N/A';
    
    echo "<tr>";
    echo "<td>{$post_id}</td>";
    echo "<td>" . esc_html(substr($titulo, 0, 30)) . "...</td>";
    echo "<td>{$tiene_foto}</td>";
    echo "<td>" . esc_html($foto_preview) . "</td>";
    echo "</tr>";
}

echo "</table>";

// Verificar configuración de Algolia
echo "<h2>🔧 Configuración de Algolia</h2>";
$algolia_config = array(
    'App ID' => get_option('lexhoy_despachos_algolia_app_id'),
    'Admin API Key' => get_option('lexhoy_despachos_algolia_admin_api_key') ? 'Configurado' : 'No configurado',
    'Search API Key' => get_option('lexhoy_despachos_algolia_search_api_key') ? 'Configurado' : 'No configurado',
    'Index Name' => get_option('lexhoy_despachos_algolia_index_name')
);

echo "<ul>";
foreach ($algolia_config as $key => $value) {
    $status = empty($value) ? '❌' : '✅';
    $display_value = ($key === 'Admin API Key' || $key === 'Search API Key') ? $value : $value;
    echo "<li><strong>{$key}:</strong> {$status} {$display_value}</li>";
}
echo "</ul>";

// Recomendaciones basadas en el entorno
echo "<h2>💡 Recomendaciones para {$env_name}</h2>";

if ($is_local) {
    echo "<div style='background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3;'>";
    echo "<h4>🛠️ Entorno de Desarrollo:</h4>";
    echo "<ol>";
    echo "<li><strong>Sincronizar fotos desde Algolia:</strong> <a href='sync-fotos-algolia-to-wp.php' target='_blank'>Ejecutar sincronización</a></li>";
    echo "<li><strong>Probar manualmente:</strong> Edita un despacho y añade una foto</li>";
    echo "<li><strong>Verificar template:</strong> Ve a una página de despacho y revisa el código fuente</li>";
    echo "<li><strong>Una vez funcionando:</strong> Aplicar a producción</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #fff3e0; padding: 15px; border-left: 4px solid #ff9800;'>";
    echo "<h4>🚀 Entorno de Producción:</h4>";
    echo "<ol>";
    echo "<li><strong>⚠️ Precaución:</strong> Con 10,856 registros, la sincronización puede tardar</li>";
    echo "<li><strong>Probar primero:</strong> Sincronizar solo algunos registros de prueba</li>";
    echo "<li><strong>Horario recomendado:</strong> Ejecutar durante horas de menos tráfico</li>";
    echo "<li><strong>Monitorear:</strong> Verificar rendimiento del servidor durante el proceso</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<hr>";
echo "<p><em>Test completado en " . date('Y-m-d H:i:s') . "</em></p>"; 
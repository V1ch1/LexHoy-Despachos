<?php
/**
 * Script para limpiar el caché del buscador de despachos
 * Ejecutar desde el navegador: http://tu-sitio.com/wp-content/plugins/LexHoy-Despachos/clear-search-cache.php
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos de administrador
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado. Necesitas permisos de administrador.');
}

echo "<h1>Limpiando caché del buscador de despachos</h1>";

// Limpiar caché principal
$cache_keys = array(
    'lexhoy_despachos_provincias',
    'lexhoy_despachos_localidades', 
    'lexhoy_despachos_areas',
    'lexhoy_despachos_counts'
);

foreach ($cache_keys as $key) {
    $deleted = wp_cache_delete($key);
    echo "<p>Cache '$key': " . ($deleted ? "✅ Limpiado" : "❌ No encontrado") . "</p>";
}

// Limpiar caché de áreas por post
global $wpdb;
$post_ids = $wpdb->get_col(
    "SELECT DISTINCT post_id 
    FROM {$wpdb->postmeta} 
    WHERE meta_key LIKE '_despacho_%'"
);

$areas_cleared = 0;
foreach ($post_ids as $post_id) {
    if (wp_cache_delete('lexhoy_despachos_areas_' . $post_id)) {
        $areas_cleared++;
    }
}
echo "<p>Cache de áreas por post: ✅ $areas_cleared limpiados</p>";

// Limpiar caché de conteos por área
$areas = get_terms(array(
    'taxonomy' => 'area_practica',
    'hide_empty' => false,
    'fields' => 'names'
));

$area_counts_cleared = 0;
foreach ($areas as $area) {
    if (wp_cache_delete('lexhoy_despachos_area_count_' . sanitize_title($area))) {
        $area_counts_cleared++;
    }
}
echo "<p>Cache de conteos por área: ✅ $area_counts_cleared limpiados</p>";

echo "<h2>✅ Caché limpiado completamente</h2>";
echo "<p><a href='javascript:history.back()'>← Volver atrás</a></p>";
?> 
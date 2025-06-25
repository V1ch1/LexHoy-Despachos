<?php
/**
 * Script de diagnóstico para verificar la paginación
 * Colocar en la raíz del plugin y ejecutar desde el navegador
 */

// Cargar WordPress
require_once('../../../wp-load.php');

echo "<h1>Diagnóstico de Paginación - LexHoy Despachos</h1>";

// Verificar cuántos despachos hay en total
$total_despachos = wp_count_posts('despacho');
echo "<h2>Estadísticas de Despachos</h2>";
echo "<p><strong>Total de despachos:</strong> " . $total_despachos->publish . "</p>";
echo "<p><strong>Borradores:</strong> " . $total_despachos->draft . "</p>";
echo "<p><strong>Pendientes:</strong> " . $total_despachos->pending . "</p>";

// Simular la consulta de búsqueda
$args = array(
    'post_type' => 'despacho',
    'post_status' => 'publish',
    'posts_per_page' => 10,
    'paged' => 1,
    'no_found_rows' => false,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => false,
    'fields' => 'ids'
);

$query = new WP_Query($args);

echo "<h2>Consulta de Prueba</h2>";
echo "<p><strong>Despachos encontrados:</strong> " . $query->found_posts . "</p>";
echo "<p><strong>Páginas totales:</strong> " . $query->max_num_pages . "</p>";
echo "<p><strong>Despachos por página:</strong> 10</p>";

// Mostrar algunos despachos de ejemplo
echo "<h2>Primeros 5 Despachos</h2>";
$despachos = get_posts(array(
    'post_type' => 'despacho',
    'post_status' => 'publish',
    'posts_per_page' => 5
));

foreach ($despachos as $despacho) {
    echo "<p><strong>ID:</strong> " . $despacho->ID . " | <strong>Título:</strong> " . $despacho->post_title . "</p>";
}

// Verificar si hay algún filtro aplicado
echo "<h2>Verificación de Filtros</h2>";
echo "<p>¿Hay algún filtro de búsqueda aplicado? (vacío = no)</p>";

// Simular búsqueda con letra A
$args_con_filtro = array(
    'post_type' => 'despacho',
    'post_status' => 'publish',
    'posts_per_page' => 10,
    'paged' => 1,
    'no_found_rows' => false,
    's' => 'A'
);

$query_con_filtro = new WP_Query($args_con_filtro);

echo "<p><strong>Búsqueda con 'A':</strong> " . $query_con_filtro->found_posts . " resultados</p>";
echo "<p><strong>Páginas con filtro 'A':</strong> " . $query_con_filtro->max_num_pages . "</p>";

wp_reset_postdata();

echo "<h2>Información del Sistema</h2>";
echo "<p><strong>WordPress Version:</strong> " . get_bloginfo('version') . "</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Plugin Version:</strong> " . (defined('LEXHOY_DESPACHOS_VERSION') ? LEXHOY_DESPACHOS_VERSION : 'No definida') . "</p>";
?> 
<?php
/**
 * Script para verificar el estado actual de la importación
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>📊 Estado Actual de la Importación</h1>";

// 1. Contar despachos en WordPress
$wp_despachos = get_posts(array(
    'post_type' => 'despacho',
    'post_status' => 'any',
    'numberposts' => -1,
    'fields' => 'ids'
));
$total_wp = count($wp_despachos);

echo "<h2>1. Despachos en WordPress</h2>";
echo "<p><strong>Total de despachos:</strong> " . number_format($total_wp) . "</p>";

// 2. Contar áreas de práctica
$areas = get_terms(array(
    'taxonomy' => 'area_practica',
    'hide_empty' => false,
));
$total_areas = count($areas);

echo "<h2>2. Áreas de Práctica</h2>";
echo "<p><strong>Total de áreas:</strong> " . $total_areas . "</p>";
if ($total_areas > 0) {
    echo "<ul>";
    foreach ($areas as $area) {
        echo "<li>" . esc_html($area->name) . " (ID: " . $area->term_id . ")</li>";
    }
    echo "</ul>";
}

// 3. Verificar configuración de Algolia
$app_id = get_option('lexhoy_despachos_algolia_app_id');
$admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
$index_name = get_option('lexhoy_despachos_algolia_index_name');

echo "<h2>3. Configuración de Algolia</h2>";
echo "<p><strong>App ID:</strong> " . ($app_id ? '✅ Configurado' : '❌ No configurado') . "</p>";
echo "<p><strong>Admin API Key:</strong> " . ($admin_api_key ? '✅ Configurado' : '❌ No configurado') . "</p>";
echo "<p><strong>Index Name:</strong> " . ($index_name ? '✅ Configurado' : '❌ No configurado') . "</p>";

// 4. Intentar conectar con Algolia
if ($app_id && $admin_api_key && $index_name) {
    try {
        require_once('includes/class-lexhoy-algolia-client.php');
        $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, '', $index_name);
        
        $result = $client->browse_all_unfiltered();
        if ($result['success']) {
            $total_algolia = count($result['hits']);
            echo "<p><strong>Total en Algolia:</strong> " . number_format($total_algolia) . "</p>";
            
            $percentage = $total_algolia > 0 ? round(($total_wp / $total_algolia) * 100, 2) : 0;
            echo "<p><strong>Progreso de importación:</strong> " . $percentage . "%</p>";
            
            if ($percentage < 100) {
                echo "<p style='color: orange;'><strong>⚠️ Importación incompleta</strong></p>";
            } else {
                echo "<p style='color: green;'><strong>✅ Importación completada</strong></p>";
            }
        } else {
            echo "<p style='color: red;'><strong>❌ Error al conectar con Algolia:</strong> " . $result['message'] . "</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>❌ Error:</strong> " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'><strong>❌ Configuración de Algolia incompleta</strong></p>";
}

// 5. Recomendaciones
echo "<h2>4. Recomendaciones</h2>";
if ($total_wp == 0) {
    echo "<p style='color: orange;'><strong>🔄 Acción necesaria:</strong> Iniciar importación completa</p>";
} elseif ($percentage < 100) {
    echo "<p style='color: orange;'><strong>🔄 Acción necesaria:</strong> Completar importación faltante</p>";
} else {
    echo "<p style='color: green;'><strong>✅ Estado:</strong> Importación completada</p>";
}

echo "<hr>";
echo "<p><a href='javascript:location.reload()'>🔄 Actualizar estado</a></p>";
?> 
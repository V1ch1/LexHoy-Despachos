<?php
/**
 * Script para probar la importación de un solo despacho
 */

// Cargar WordPress
require_once('../../../wp-load.php');

echo "<h1>🧪 Prueba de Importación de un Solo Despacho</h1>";

// Verificar si WordPress está cargado
if (!function_exists('get_option')) {
    echo "<p style='color: red;'>❌ Error: WordPress no está cargado correctamente</p>";
    exit;
}

// Obtener las opciones de Algolia
$app_id = get_option('lexhoy_despachos_algolia_app_id');
$admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
$index_name = get_option('lexhoy_despachos_algolia_index_name');

echo "<h2>📋 Configuración</h2>";
echo "<p><strong>App ID:</strong> " . ($app_id ? esc_html($app_id) : 'No configurado') . "</p>";
echo "<p><strong>Index Name:</strong> " . ($index_name ? esc_html($index_name) : 'No configurado') . "</p>";

if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
    echo "<p style='color: red;'>❌ Configuración incompleta</p>";
    exit;
}

// Incluir la clase de Algolia
require_once('includes/class-lexhoy-algolia-client.php');

try {
    $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, '', $index_name);
    
    echo "<h2>🔍 Obteniendo un Despacho de Prueba</h2>";
    
    // Obtener solo 1 registro de Algolia
    $result = $client->browse_page(0, 1);
    
    if (!$result['success']) {
        echo "<p style='color: red;'>❌ Error al obtener registros: " . esc_html($result['message']) . "</p>";
        exit;
    }
    
    if (empty($result['hits'])) {
        echo "<p style='color: orange;'>⚠️ No se encontraron registros en Algolia</p>";
        exit;
    }
    
    $test_record = $result['hits'][0];
    
    echo "<h3>📄 Registro de Prueba Obtenido</h3>";
    echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px;'>";
    echo "<pre>" . esc_html(print_r($test_record, true)) . "</pre>";
    echo "</div>";
    
    echo "<h2>🔧 Procesando el Registro</h2>";
    
    // Incluir la clase CPT para procesar el registro
    require_once('includes/class-lexhoy-despachos-cpt.php');
    
    // Crear una instancia temporal para acceder al método process_algolia_record
    $cpt = new LexhoyDespachosCPT();
    
    // Usar reflexión para acceder al método privado
    $reflection = new ReflectionClass($cpt);
    $method = $reflection->getMethod('process_algolia_record');
    $method->setAccessible(true);
    
    echo "<p>🔄 Procesando registro con objectID: <strong>" . esc_html($test_record['objectID']) . "</strong></p>";
    
    // Procesar el registro
    $post_id = $method->invoke($cpt, $test_record);
    
    if ($post_id) {
        echo "<p style='color: green;'>✅ Despacho creado exitosamente con ID: <strong>{$post_id}</strong></p>";
        
        // Obtener los datos del post creado
        $post = get_post($post_id);
        $meta_fields = get_post_meta($post_id);
        
        echo "<h3>📊 Datos del Post Creado</h3>";
        echo "<div style='background: #e8f5e8; padding: 15px; border: 1px solid #28a745; border-radius: 5px;'>";
        echo "<h4>Información Básica del Post</h4>";
        echo "<ul>";
        echo "<li><strong>ID:</strong> " . esc_html($post->ID) . "</li>";
        echo "<li><strong>Título:</strong> " . esc_html($post->post_title) . "</li>";
        echo "<li><strong>Slug:</strong> " . esc_html($post->post_name) . "</li>";
        echo "<li><strong>Estado:</strong> " . esc_html($post->post_status) . "</li>";
        echo "<li><strong>Tipo:</strong> " . esc_html($post->post_type) . "</li>";
        echo "</ul>";
        
        echo "<h4>Campos Personalizados (Meta Fields)</h4>";
        echo "<ul>";
        foreach ($meta_fields as $key => $values) {
            if (strpos($key, '_despacho_') === 0) {
                $value = is_array($values) ? $values[0] : $values;
                echo "<li><strong>" . esc_html($key) . ":</strong> " . esc_html($value) . "</li>";
            }
        }
        echo "</ul>";
        echo "</div>";
        
        echo "<h3>🔗 Enlaces</h3>";
        echo "<p><a href='" . get_edit_post_link($post_id) . "' target='_blank'>✏️ Editar en WordPress</a></p>";
        echo "<p><a href='" . get_permalink($post_id) . "' target='_blank'>👁️ Ver en el Frontend</a></p>";
        
        // Opción para eliminar el post de prueba
        echo "<h3>🧹 Limpieza</h3>";
        echo "<p><a href='?action=delete_test&post_id={$post_id}&nonce=" . wp_create_nonce('delete_test') . "' style='color: red;' onclick='return confirm(\"¿Estás seguro de que quieres eliminar este post de prueba?\")'>🗑️ Eliminar Post de Prueba</a></p>";
        
    } else {
        echo "<p style='color: red;'>❌ Error al crear el despacho</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . esc_html($e->getMessage()) . "</p>";
}

// Manejar eliminación del post de prueba
if (isset($_GET['action']) && $_GET['action'] === 'delete_test' && isset($_GET['post_id'])) {
    if (wp_verify_nonce($_GET['nonce'], 'delete_test')) {
        $post_id = intval($_GET['post_id']);
        $deleted = wp_delete_post($post_id, true);
        if ($deleted) {
            echo "<p style='color: green;'>✅ Post de prueba eliminado correctamente</p>";
        } else {
            echo "<p style='color: red;'>❌ Error al eliminar el post de prueba</p>";
        }
    }
}

echo "<hr>";
echo "<p><small>Script de prueba generado el " . date('Y-m-d H:i:s') . "</small></p>";
?> 
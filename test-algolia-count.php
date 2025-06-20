<?php
/**
 * Script simple para probar el conteo de registros de Algolia
 */

// Cargar WordPress
require_once('../../../wp-load.php');

echo "<h1>🔍 Prueba de Conteo de Registros en Algolia</h1>";

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
echo "<p><strong>Admin API Key:</strong> " . ($admin_api_key ? substr($admin_api_key, 0, 4) . '...' . substr($admin_api_key, -4) : 'No configurado') . "</p>";

if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
    echo "<p style='color: red;'>❌ Configuración incompleta</p>";
    exit;
}

// Incluir la clase de Algolia
require_once('includes/class-lexhoy-algolia-client.php');

try {
    $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, '', $index_name);
    
    echo "<h2>🔗 Prueba de Conexión</h2>";
    
    if ($client->verify_credentials()) {
        echo "<p style='color: green;'>✅ Conexión exitosa con Algolia</p>";
        
        echo "<h2>📊 Conteo de Registros</h2>";
        
        // Intentar obtener el conteo total
        try {
            $total_count = $client->get_total_count();
            echo "<p style='color: green;'>✅ Total de registros en Algolia: <strong>{$total_count}</strong></p>";
        } catch (Exception $e) {
            echo "<p style='color: orange;'>⚠️ Error al obtener conteo: " . esc_html($e->getMessage()) . "</p>";
            
            // Intentar método alternativo
            echo "<h3>🔄 Método Alternativo - Browse</h3>";
            try {
                $hits = $client->browse_all();
                if (is_array($hits) && isset($hits['hits'])) {
                    $count = count($hits['hits']);
                    echo "<p style='color: green;'>✅ Registros encontrados (browse): <strong>{$count}</strong></p>";
                } else {
                    echo "<p style='color: red;'>❌ No se pudieron obtener registros</p>";
                }
            } catch (Exception $e2) {
                echo "<p style='color: red;'>❌ Error en browse: " . esc_html($e2->getMessage()) . "</p>";
            }
        }
        
    } else {
        echo "<p style='color: red;'>❌ Error de conexión con Algolia</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . esc_html($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><small>Script de prueba generado el " . date('Y-m-d H:i:s') . "</small></p>";
?> 
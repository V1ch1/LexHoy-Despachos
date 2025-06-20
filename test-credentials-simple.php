<?php
/**
 * Script simple para probar credenciales de Algolia
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>🔑 Prueba de Credenciales de Algolia</h1>\n";

try {
    // Obtener configuración de Algolia
    $app_id = get_option('lexhoy_despachos_algolia_app_id');
    $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
    $search_api_key = get_option('lexhoy_despachos_algolia_search_api_key');
    $index_name = get_option('lexhoy_despachos_algolia_index_name');

    echo "<h2>📋 Configuración actual:</h2>\n";
    echo "<ul>\n";
    echo "<li><strong>App ID:</strong> " . ($app_id ?: 'NO CONFIGURADO') . "</li>\n";
    echo "<li><strong>Admin API Key:</strong> " . ($admin_api_key ? substr($admin_api_key, 0, 8) . '...' : 'NO CONFIGURADO') . "</li>\n";
    echo "<li><strong>Search API Key:</strong> " . ($search_api_key ? substr($search_api_key, 0, 8) . '...' : 'NO CONFIGURADO') . "</li>\n";
    echo "<li><strong>Index Name:</strong> " . ($index_name ?: 'NO CONFIGURADO') . "</li>\n";
    echo "</ul>\n";

    if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
        throw new Exception('Configuración de Algolia incompleta');
    }

    require_once('includes/class-lexhoy-algolia-client.php');
    $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, $search_api_key, $index_name);

    echo "<h2>🔍 Probando verificación de credenciales...</h2>\n";
    
    // Probar verificación de credenciales
    $credentials_ok = $client->verify_credentials();
    
    if ($credentials_ok) {
        echo "<p style='color: green;'>✅ <strong>Credenciales válidas</strong></p>\n";
    } else {
        echo "<p style='color: red;'>❌ <strong>Credenciales inválidas</strong></p>\n";
        throw new Exception('Las credenciales de Algolia no son válidas');
    }

    echo "<h2>📊 Probando browse_all_unfiltered...</h2>\n";
    
    // Probar el método sin filtrar
    $result = $client->browse_all_unfiltered();
    
    if (!$result['success']) {
        throw new Exception('Error al obtener registros: ' . $result['message']);
    }

    $all_hits = $result['hits'];
    $total_records = count($all_hits);
    
    echo "<p style='color: green;'>✅ <strong>ÉXITO:</strong> Total de registros obtenidos: <strong>{$total_records}</strong></p>\n";
    echo "<p>📄 Páginas procesadas: <strong>{$result['pages_processed']}</strong></p>\n";

    // Mostrar algunos ejemplos
    echo "<h3>📋 Ejemplos de registros (primeros 3):</h3>\n";
    echo "<div style='max-height: 300px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>\n";
    
    for ($i = 0; $i < min(3, count($all_hits)); $i++) {
        $record = $all_hits[$i];
        $is_generated = strpos($record['objectID'] ?? '', '_dashboard_generated_id') !== false;
        $border_color = $is_generated ? 'orange' : (empty($record['nombre']) ? 'red' : 'green');
        
        echo "<div style='margin-bottom: 10px; padding: 10px; background: white; border-left: 4px solid {$border_color};'>\n";
        echo "<strong>ID:</strong> " . ($record['objectID'] ?? 'N/A') . "<br>\n";
        echo "<strong>Nombre:</strong> " . ($record['nombre'] ?? 'N/A') . "<br>\n";
        echo "<strong>Localidad:</strong> " . ($record['localidad'] ?? 'N/A') . "<br>\n";
        echo "<strong>Provincia:</strong> " . ($record['provincia'] ?? 'N/A') . "<br>\n";
        echo "<strong>Generado automáticamente:</strong> " . ($is_generated ? 'SÍ' : 'NO') . "<br>\n";
        echo "</div>\n";
    }
    echo "</div>\n";

    echo "<h3>🎯 Conclusión:</h3>\n";
    echo "<p>✅ <strong>Todo funciona correctamente:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>✅ Credenciales válidas</li>\n";
    echo "<li>✅ Conexión a Algolia exitosa</li>\n";
    echo "<li>✅ Método browse_all_unfiltered funciona</li>\n";
    echo "<li>✅ Se obtienen todos los {$total_records} registros</li>\n";
    echo "</ul>\n";
    echo "<p>Ahora puedes usar la importación masiva desde el admin de WordPress.</p>\n";

} catch (Exception $e) {
    echo "<h2>❌ Error:</h2>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='javascript:history.back()'>← Volver</a></p>\n";
?> 
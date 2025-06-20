<?php
/**
 * Script de diagnóstico para verificar la configuración de Algolia
 */

// Cargar WordPress
require_once('../../../wp-load.php');

echo "<h1>🔍 Diagnóstico de Configuración de Algolia</h1>";

// Verificar si WordPress está cargado
if (!function_exists('get_option')) {
    echo "<p style='color: red;'>❌ Error: WordPress no está cargado correctamente</p>";
    exit;
}

echo "<h2>📋 Configuración Actual</h2>";

// Obtener las opciones de Algolia
$app_id = get_option('lexhoy_despachos_algolia_app_id');
$admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
$search_api_key = get_option('lexhoy_despachos_algolia_search_api_key');
$index_name = get_option('lexhoy_despachos_algolia_index_name');

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Opción</th><th>Valor</th><th>Estado</th></tr>";

// App ID
echo "<tr>";
echo "<td>App ID</td>";
echo "<td>" . ($app_id ? esc_html($app_id) : '<em>No configurado</em>') . "</td>";
echo "<td>" . ($app_id ? "✅ Configurado" : "❌ Faltante") . "</td>";
echo "</tr>";

// Admin API Key
echo "<tr>";
echo "<td>Admin API Key</td>";
if ($admin_api_key) {
    $key_length = strlen($admin_api_key);
    $key_preview = substr($admin_api_key, 0, 4) . '...' . substr($admin_api_key, -4);
    echo "<td>" . esc_html($key_preview) . " (longitud: {$key_length})</td>";
    echo "<td>" . ($key_length >= 32 ? "✅ Configurado" : "⚠️ Posiblemente incompleto") . "</td>";
} else {
    echo "<td><em>No configurado</em></td>";
    echo "<td>❌ Faltante</td>";
}
echo "</tr>";

// Search API Key
echo "<tr>";
echo "<td>Search API Key</td>";
if ($search_api_key) {
    $key_length = strlen($search_api_key);
    $key_preview = substr($search_api_key, 0, 4) . '...' . substr($search_api_key, -4);
    echo "<td>" . esc_html($key_preview) . " (longitud: {$key_length})</td>";
    echo "<td>" . ($key_length >= 32 ? "✅ Configurado" : "⚠️ Posiblemente incompleto") . "</td>";
} else {
    echo "<td><em>No configurado</em></td>";
    echo "<td>⚠️ Opcional</td>";
}
echo "</tr>";

// Index Name
echo "<tr>";
echo "<td>Index Name</td>";
echo "<td>" . ($index_name ? esc_html($index_name) : '<em>No configurado</em>') . "</td>";
echo "<td>" . ($index_name ? "✅ Configurado" : "❌ Faltante") . "</td>";
echo "</tr>";

echo "</table>";

echo "<h2>🔗 Prueba de Conexión</h2>";

if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
    echo "<p style='color: red;'>❌ No se puede probar la conexión: faltan credenciales</p>";
} else {
    try {
        // Incluir la clase de Algolia
        require_once('includes/class-lexhoy-algolia-client.php');
        
        $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, $search_api_key, $index_name);
        
        echo "<p>🔄 Probando conexión con Algolia...</p>";
        
        if ($client->verify_credentials()) {
            echo "<p style='color: green;'>✅ Conexión exitosa con Algolia</p>";
            
            // Intentar obtener el conteo total
            echo "<p>📊 Obteniendo estadísticas...</p>";
            
            try {
                $total_count = $client->get_total_count();
                echo "<p style='color: green;'>✅ Total de registros en Algolia: <strong>{$total_count}</strong></p>";
            } catch (Exception $e) {
                echo "<p style='color: orange;'>⚠️ No se pudo obtener el conteo total: " . esc_html($e->getMessage()) . "</p>";
            }
            
        } else {
            echo "<p style='color: red;'>❌ Error de conexión con Algolia</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . esc_html($e->getMessage()) . "</p>";
    }
}

echo "<h2>📝 Recomendaciones</h2>";

if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px;'>";
    echo "<h3>⚠️ Configuración Incompleta</h3>";
    echo "<p>Para que la importación masiva funcione, necesitas configurar:</p>";
    echo "<ul>";
    if (empty($app_id)) echo "<li><strong>App ID:</strong> Ve a Despachos > Configuración de Algolia</li>";
    if (empty($admin_api_key)) echo "<li><strong>Admin API Key:</strong> Ve a Despachos > Configuración de Algolia</li>";
    if (empty($index_name)) echo "<li><strong>Index Name:</strong> Ve a Despachos > Configuración de Algolia</li>";
    echo "</ul>";
    echo "<p><a href='" . admin_url('edit.php?post_type=despacho&page=lexhoy-despachos-algolia') . "' class='button button-primary'>Ir a Configuración de Algolia</a></p>";
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>";
    echo "<h3>✅ Configuración Completa</h3>";
    echo "<p>La configuración de Algolia parece estar correcta. Si sigues viendo 0 registros, verifica:</p>";
    echo "<ul>";
    echo "<li>Que el índice de Algolia contenga datos</li>";
    echo "<li>Que las credenciales tengan permisos de lectura</li>";
    echo "<li>Que no haya restricciones de IP en Algolia</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<hr>";
echo "<p><small>Script de diagnóstico generado el " . date('Y-m-d H:i:s') . "</small></p>";
?> 
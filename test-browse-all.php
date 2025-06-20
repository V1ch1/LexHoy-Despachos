<?php
/**
 * Archivo de prueba para verificar la función browse_all_with_cursor
 * 
 * Este archivo debe ejecutarse desde el directorio raíz del plugin
 * para probar la nueva función que obtiene todos los registros de Algolia
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar que estamos en el contexto correcto
if (!defined('ABSPATH')) {
    die('Este archivo debe ejecutarse desde WordPress');
}

// Verificar permisos de administrador
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>Prueba de browse_all_with_cursor</h1>\n";

try {
    // Obtener configuración de Algolia
    $app_id = get_option('lexhoy_despachos_algolia_app_id');
    $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
    $search_api_key = get_option('lexhoy_despachos_algolia_search_api_key');
    $index_name = get_option('lexhoy_despachos_algolia_index_name');

    echo "<h2>Configuración de Algolia:</h2>\n";
    echo "<ul>\n";
    echo "<li>App ID: " . ($app_id ?: 'No configurado') . "</li>\n";
    echo "<li>Admin API Key: " . ($admin_api_key ? substr($admin_api_key, 0, 8) . '...' : 'No configurado') . "</li>\n";
    echo "<li>Search API Key: " . ($search_api_key ? substr($search_api_key, 0, 8) . '...' : 'No configurado') . "</li>\n";
    echo "<li>Index Name: " . ($index_name ?: 'No configurado') . "</li>\n";
    echo "</ul>\n";

    if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
        throw new Exception('Configuración de Algolia incompleta');
    }

    // Incluir la clase del cliente de Algolia
    require_once('includes/class-lexhoy-algolia-client.php');

    // Crear instancia del cliente
    $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, $search_api_key, $index_name);

    echo "<h2>Probando browse_all_with_cursor...</h2>\n";
    echo "<p>Iniciando obtención de todos los registros...</p>\n";

    // Ejecutar la función
    $start_time = microtime(true);
    $result = $client->browse_all_with_cursor();
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);

    echo "<h3>Resultado:</h3>\n";
    echo "<ul>\n";
    echo "<li>Éxito: " . ($result['success'] ? 'SÍ' : 'NO') . "</li>\n";
    
    if ($result['success']) {
        echo "<li>Total de registros: " . $result['total_records'] . "</li>\n";
        echo "<li>Páginas procesadas: " . $result['pages_processed'] . "</li>\n";
        echo "<li>Tiempo de ejecución: {$execution_time} segundos</li>\n";
        
        // Mostrar algunos registros de ejemplo
        if (!empty($result['hits'])) {
            echo "<li>Primeros 3 registros:</li>\n";
            echo "<ul>\n";
            for ($i = 0; $i < min(3, count($result['hits'])); $i++) {
                $hit = $result['hits'][$i];
                echo "<li>ID: " . ($hit['objectID'] ?? 'N/A') . " - Nombre: " . ($hit['nombre'] ?? 'N/A') . "</li>\n";
            }
            echo "</ul>\n";
        }
    } else {
        echo "<li>Error: " . $result['message'] . "</li>\n";
        if (isset($result['error'])) {
            echo "<li>Tipo de error: " . $result['error'] . "</li>\n";
        }
    }
    echo "</ul>\n";

    // Comparar con el método anterior
    echo "<h2>Comparación con browse_all() original:</h2>\n";
    $start_time = microtime(true);
    $old_result = $client->browse_all();
    $end_time = microtime(true);
    $old_execution_time = round($end_time - $start_time, 2);

    echo "<ul>\n";
    echo "<li>Éxito: " . ($old_result['success'] ? 'SÍ' : 'NO') . "</li>\n";
    if ($old_result['success']) {
        echo "<li>Total de registros: " . $old_result['total_records'] . "</li>\n";
        echo "<li>Tiempo de ejecución: {$old_execution_time} segundos</li>\n";
    } else {
        echo "<li>Error: " . $old_result['message'] . "</li>\n";
    }
    echo "</ul>\n";

    // Mostrar diferencia
    if ($result['success'] && $old_result['success']) {
        $difference = $result['total_records'] - $old_result['total_records'];
        echo "<h3>Diferencia:</h3>\n";
        echo "<p>El nuevo método encontró <strong>" . $difference . "</strong> registros adicionales.</p>\n";
        
        if ($difference > 0) {
            echo "<p style='color: green;'>✅ El nuevo método funciona correctamente y obtiene más registros.</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠️ Ambos métodos obtuvieron la misma cantidad de registros.</p>\n";
        }
    }

} catch (Exception $e) {
    echo "<h2>Error:</h2>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
    echo "<p>Stack trace:</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}

echo "<hr>\n";
echo "<p><a href='javascript:history.back()'>← Volver</a></p>\n";
?> 
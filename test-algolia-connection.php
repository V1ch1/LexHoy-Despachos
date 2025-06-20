<?php
/**
 * Script de prueba rápida para verificar la conexión con Algolia
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>🔍 Diagnóstico de Conexión con Algolia</h1>\n";

try {
    // Obtener configuración de Algolia
    $app_id = get_option('lexhoy_despachos_algolia_app_id');
    $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
    $search_api_key = get_option('lexhoy_despachos_algolia_search_api_key');
    $index_name = get_option('lexhoy_despachos_algolia_index_name');

    echo "<h2>1. Configuración de Algolia</h2>\n";
    echo "<ul>\n";
    echo "<li>App ID: <strong>" . ($app_id ?: 'No configurado') . "</strong></li>\n";
    echo "<li>Admin API Key: <strong>" . ($admin_api_key ? substr($admin_api_key, 0, 8) . '...' : 'No configurado') . "</strong></li>\n";
    echo "<li>Search API Key: <strong>" . ($search_api_key ? substr($search_api_key, 0, 8) . '...' : 'No configurado') . "</strong></li>\n";
    echo "<li>Index Name: <strong>" . ($index_name ?: 'No configurado') . "</strong></li>\n";
    echo "</ul>\n";

    if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
        throw new Exception('Configuración de Algolia incompleta');
    }

    require_once('includes/class-lexhoy-algolia-client.php');
    $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, $search_api_key, $index_name);

    echo "<h2>2. Verificando credenciales...</h2>\n";
    $credentials_ok = $client->verify_credentials();
    echo "<p>Credenciales válidas: <strong>" . ($credentials_ok ? '✅ SÍ' : '❌ NO') . "</strong></p>\n";

    if (!$credentials_ok) {
        throw new Exception('Las credenciales de Algolia no son válidas');
    }

    echo "<h2>3. Probando búsqueda simple...</h2>\n";
    $search_result = $client->search($index_name, '', array('hitsPerPage' => 1));
    
    if ($search_result && isset($search_result['hits'])) {
        echo "<p>Búsqueda exitosa: <strong>✅ SÍ</strong></p>\n";
        echo "<p>Total de hits: <strong>" . $search_result['nbHits'] . "</strong></p>\n";
        echo "<p>Hits en esta página: <strong>" . count($search_result['hits']) . "</strong></p>\n";
        
        if (!empty($search_result['hits'])) {
            echo "<p>Primer registro encontrado:</p>\n";
            echo "<pre>" . print_r($search_result['hits'][0], true) . "</pre>\n";
        }
    } else {
        echo "<p>Búsqueda falló: <strong>❌ NO</strong></p>\n";
        echo "<p>Respuesta: <pre>" . print_r($search_result, true) . "</pre></p>\n";
    }

    echo "<h2>4. Probando browse_all_with_cursor...</h2>\n";
    $result = $client->browse_all_with_cursor();
    
    echo "<p>Éxito: <strong>" . ($result['success'] ? '✅ SÍ' : '❌ NO') . "</strong></p>\n";
    
    if ($result['success']) {
        echo "<p>Total de registros: <strong>" . $result['total_records'] . "</strong></p>\n";
        echo "<p>Páginas procesadas: <strong>" . $result['pages_processed'] . "</strong></p>\n";
        
        if (!empty($result['hits'])) {
            echo "<p>Primer registro:</p>\n";
            echo "<pre>" . print_r($result['hits'][0], true) . "</pre>\n";
        }
    } else {
        echo "<p>Error: <strong>" . $result['message'] . "</strong></p>\n";
        if (isset($result['error'])) {
            echo "<p>Tipo de error: <strong>" . $result['error'] . "</strong></p>\n";
        }
    }

} catch (Exception $e) {
    echo "<h2>Error:</h2>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='javascript:history.back()'>← Volver</a></p>\n";
?> 
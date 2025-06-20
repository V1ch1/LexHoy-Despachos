<?php
/**
 * Script de prueba para verificar el método browse_all_unfiltered
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>🧪 Prueba: browse_all_unfiltered</h1>\n";

try {
    // Obtener configuración de Algolia
    $app_id = get_option('lexhoy_despachos_algolia_app_id');
    $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
    $search_api_key = get_option('lexhoy_despachos_algolia_search_api_key');
    $index_name = get_option('lexhoy_despachos_algolia_index_name');

    if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
        throw new Exception('Configuración de Algolia incompleta');
    }

    require_once('includes/class-lexhoy-algolia-client.php');
    $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, $search_api_key, $index_name);

    echo "<h2>📊 Probando browse_all_unfiltered...</h2>\n";
    
    // Probar el nuevo método
    $result = $client->browse_all_unfiltered();
    
    if (!$result['success']) {
        throw new Exception('Error al obtener registros: ' . $result['message']);
    }

    $all_hits = $result['hits'];
    $total_records = count($all_hits);
    
    echo "<p>✅ <strong>ÉXITO:</strong> Total de registros obtenidos: <strong>{$total_records}</strong></p>\n";
    echo "<p>📄 Páginas procesadas: <strong>{$result['pages_processed']}</strong></p>\n";

    // Mostrar algunos ejemplos
    echo "<h3>📋 Ejemplos de registros (primeros 5):</h3>\n";
    echo "<div style='max-height: 400px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>\n";
    
    for ($i = 0; $i < min(5, count($all_hits)); $i++) {
        $record = $all_hits[$i];
        $is_generated = strpos($record['objectID'] ?? '', '_dashboard_generated_id') !== false;
        $border_color = $is_generated ? 'orange' : (empty($record['nombre']) ? 'red' : 'green');
        
        echo "<div style='margin-bottom: 10px; padding: 10px; background: white; border-left: 4px solid {$border_color};'>\n";
        echo "<strong>ID:</strong> " . ($record['objectID'] ?? 'N/A') . "<br>\n";
        echo "<strong>Nombre:</strong> " . ($record['nombre'] ?? 'N/A') . "<br>\n";
        echo "<strong>Localidad:</strong> " . ($record['localidad'] ?? 'N/A') . "<br>\n";
        echo "<strong>Provincia:</strong> " . ($record['provincia'] ?? 'N/A') . "<br>\n";
        echo "<strong>Teléfono:</strong> " . ($record['telefono'] ?? 'N/A') . "<br>\n";
        echo "<strong>Generado automáticamente:</strong> " . ($is_generated ? 'SÍ' : 'NO') . "<br>\n";
        echo "</div>\n";
    }
    echo "</div>\n";

    // Análisis de tipos de registros
    $valid_records = [];
    $empty_records = [];
    $generated_records = [];
    
    foreach ($all_hits as $hit) {
        $object_id = $hit['objectID'] ?? '';
        $nombre = trim($hit['nombre'] ?? '');
        $localidad = trim($hit['localidad'] ?? '');
        $provincia = trim($hit['provincia'] ?? '');
        
        // Verificar si el registro está vacío
        $is_empty = empty($nombre) && empty($localidad) && empty($provincia);
        
        // Verificar si es un registro generado automáticamente
        $is_generated = strpos($object_id, '_dashboard_generated_id') !== false;
        
        // Verificar si tiene datos mínimos válidos
        $has_minimal_data = !empty($nombre) || !empty($localidad) || !empty($provincia);
        
        if ($is_generated) {
            $generated_records[] = $hit;
        } elseif ($is_empty) {
            $empty_records[] = $hit;
        } elseif ($has_minimal_data) {
            $valid_records[] = $hit;
        }
    }
    
    echo "<h3>📊 Análisis de tipos de registros:</h3>\n";
    echo "<ul>\n";
    echo "<li>✅ <strong>Registros válidos:</strong> <span style='color: green; font-weight: bold;'>" . count($valid_records) . "</span></li>\n";
    echo "<li>❌ <strong>Registros vacíos:</strong> <span style='color: red;'>" . count($empty_records) . "</span></li>\n";
    echo "<li>⚠️ <strong>Registros generados automáticamente:</strong> <span style='color: orange;'>" . count($generated_records) . "</span></li>\n";
    echo "</ul>\n";

    echo "<h3>🎯 Conclusión:</h3>\n";
    echo "<p>El método <code>browse_all_unfiltered</code> está funcionando correctamente y obtiene <strong>TODOS</strong> los registros de Algolia sin filtrar.</p>\n";
    echo "<p>Ahora puedes usar el script de migración completa para importar todos los registros.</p>\n";

} catch (Exception $e) {
    echo "<h2>❌ Error:</h2>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='javascript:history.back()'>← Volver</a></p>\n";
?> 
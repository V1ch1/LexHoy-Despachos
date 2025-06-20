<?php
/**
 * Script para limpiar SOLO registros completamente vacíos de Algolia
 * Conserva registros que tienen datos reales, aunque tengan _dashboard_generated_id
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>Limpieza Selectiva de Algolia</h1>\n";
echo "<p><strong>IMPORTANTE:</strong> Este script solo eliminará registros COMPLETAMENTE VACÍOS.</p>\n";
echo "<p><strong>CONSERVARÁ:</strong> Registros con datos reales (nombre, localidad, teléfono, etc.)</p>\n";

try {
    // Obtener configuración
    $app_id = get_option('lexhoy_despachos_algolia_app_id');
    $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
    $search_api_key = get_option('lexhoy_despachos_algolia_search_api_key');
    $index_name = get_option('lexhoy_despachos_algolia_index_name');

    if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
        throw new Exception('Configuración de Algolia incompleta');
    }

    require_once('includes/class-lexhoy-algolia-client.php');
    $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, $search_api_key, $index_name);

    echo "<h2>1. Analizando registros en Algolia...</h2>\n";
    
    // Obtener todos los registros de Algolia
    $result = $client->browse_all_with_cursor();
    
    if (!$result['success']) {
        throw new Exception('Error al obtener registros: ' . $result['message']);
    }

    $all_hits = $result['hits'];
    $total_algolia = count($all_hits);
    
    echo "<p>Total de registros en Algolia: <strong>{$total_algolia}</strong></p>\n";

    // Analizar registros de Algolia con criterio mejorado
    $algolia_empty_records = [];
    $algolia_valid_records = [];
    $algolia_partial_records = [];
    
    foreach ($all_hits as $hit) {
        $object_id = $hit['objectID'] ?? '';
        $nombre = trim($hit['nombre'] ?? '');
        $localidad = trim($hit['localidad'] ?? '');
        $provincia = trim($hit['provincia'] ?? '');
        $direccion = trim($hit['direccion'] ?? '');
        $telefono = trim($hit['telefono'] ?? '');
        $email = trim($hit['email'] ?? '');
        $web = trim($hit['web'] ?? '');
        $descripcion = trim($hit['descripcion'] ?? '');
        $codigo_postal = trim($hit['codigo_postal'] ?? '');
        
        // Verificar si el registro está COMPLETAMENTE vacío
        $is_completely_empty = empty($nombre) && 
                              empty($localidad) && 
                              empty($provincia) && 
                              empty($direccion) && 
                              empty($telefono) && 
                              empty($email) && 
                              empty($web) && 
                              empty($descripcion) && 
                              empty($codigo_postal);
        
        // Verificar si tiene datos significativos
        $has_significant_data = !empty($nombre) || 
                               !empty($localidad) || 
                               !empty($provincia) || 
                               !empty($direccion) || 
                               !empty($telefono) || 
                               !empty($email) || 
                               !empty($web) || 
                               !empty($descripcion) || 
                               !empty($codigo_postal);
        
        if ($is_completely_empty) {
            $algolia_empty_records[] = $hit;
        } elseif ($has_significant_data) {
            $algolia_valid_records[] = $hit;
        } else {
            $algolia_partial_records[] = $hit;
        }
    }
    
    echo "<h3>Análisis detallado de Algolia:</h3>\n";
    echo "<ul>\n";
    echo "<li>Registros válidos (con datos): <strong style='color: green;'>" . count($algolia_valid_records) . "</strong></li>\n";
    echo "<li>Registros completamente vacíos: <strong style='color: red;'>" . count($algolia_empty_records) . "</strong></li>\n";
    echo "<li>Registros parciales: <strong style='color: orange;'>" . count($algolia_partial_records) . "</strong></li>\n";
    echo "</ul>\n";

    // Mostrar ejemplos de cada tipo
    echo "<h3>Ejemplos de registros válidos (se conservarán):</h3>\n";
    echo "<ul>\n";
    for ($i = 0; $i < min(3, count($algolia_valid_records)); $i++) {
        $record = $algolia_valid_records[$i];
        $nombre = $record['nombre'] ?? 'Sin nombre';
        $localidad = $record['localidad'] ?? 'Sin localidad';
        $provincia = $record['provincia'] ?? 'Sin provincia';
        echo "<li><strong>{$nombre}</strong> - {$localidad}, {$provincia}</li>\n";
    }
    echo "</ul>\n";

    echo "<h3>Ejemplos de registros vacíos (se eliminarán):</h3>\n";
    echo "<ul>\n";
    for ($i = 0; $i < min(3, count($algolia_empty_records)); $i++) {
        $record = $algolia_empty_records[$i];
        $object_id = $record['objectID'] ?? 'N/A';
        $slug = $record['slug'] ?? 'N/A';
        echo "<li>ID: {$object_id} - Slug: {$slug} (todos los campos vacíos)</li>\n";
    }
    echo "</ul>\n";

    echo "<h2>2. Resumen de limpieza</h2>\n";
    echo "<p><strong>Se eliminarán:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>De Algolia: <strong style='color: red;'>" . count($algolia_empty_records) . "</strong> registros completamente vacíos</li>\n";
    echo "</ul>\n";
    
    echo "<p><strong>Se mantendrán:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>En Algolia: <strong style='color: green;'>" . count($algolia_valid_records) . "</strong> registros con datos</li>\n";
    echo "<li>En Algolia: <strong style='color: orange;'>" . count($algolia_partial_records) . "</strong> registros parciales</li>\n";
    echo "</ul>\n";

    $total_after_cleanup = count($algolia_valid_records) + count($algolia_partial_records);
    echo "<p><strong>Total después de la limpieza: <span style='color: blue; font-size: 18px;'>{$total_after_cleanup}</span> registros</strong></p>\n";

    if (empty($algolia_empty_records)) {
        echo "<p style='color: green;'>✅ No se encontraron registros completamente vacíos para eliminar.</p>\n";
    } else {
        // Preguntar si eliminar
        if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
            echo "<h2>Eliminando registros completamente vacíos...</h2>\n";
            
            $algolia_deleted = 0;
            $errors = 0;
            
            // Eliminar de Algolia
            echo "<h3>Eliminando de Algolia...</h3>\n";
            foreach ($algolia_empty_records as $record) {
                try {
                    $object_id = $record['objectID'];
                    $result = $client->delete_object($index_name, $object_id);
                    
                    if ($result) {
                        $algolia_deleted++;
                        echo "<p style='color: green;'>✅ Algolia: Eliminado {$object_id}</p>\n";
                    } else {
                        $errors++;
                        echo "<p style='color: red;'>❌ Algolia: Error eliminando {$object_id}</p>\n";
                    }
                    
                    usleep(50000); // 50ms
                    
                } catch (Exception $e) {
                    $errors++;
                    echo "<p style='color: red;'>❌ Algolia: Error eliminando {$object_id}: " . $e->getMessage() . "</p>\n";
                }
            }
            
            echo "<h3>Resumen de eliminación:</h3>\n";
            echo "<ul>\n";
            echo "<li>Eliminados de Algolia: <strong>{$algolia_deleted}</strong></li>\n";
            echo "<li>Errores: <strong>{$errors}</strong></li>\n";
            echo "</ul>\n";
            
            echo "<p style='color: green;'>✅ Limpieza completada. Algolia ahora tiene <strong>{$total_after_cleanup}</strong> registros válidos.</p>\n";
            
        } else {
            echo "<h2>¿Proceder con la limpieza?</h2>\n";
            echo "<p><strong>ATENCIÓN:</strong> Esta acción eliminará solo los registros completamente vacíos de Algolia.</p>\n";
            echo "<p><strong>SE CONSERVARÁN:</strong> Todos los registros con datos reales.</p>\n";
            echo "<form method='post'>\n";
            echo "<input type='hidden' name='confirm_delete' value='yes'>\n";
            echo "<button type='submit' style='background: red; color: white; padding: 10px 20px; border: none; cursor: pointer;'>";
            echo "SÍ, ELIMINAR SOLO REGISTROS VACÍOS";
            echo "</button>\n";
            echo "</form>\n";
        }
    }

} catch (Exception $e) {
    echo "<h2>Error:</h2>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='javascript:history.back()'>← Volver</a></p>\n";
?> 
<?php
/**
 * Script para limpiar registros vacíos de Algolia
 * 
 * Este script identifica y elimina registros que solo tienen slug pero no tienen datos reales
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

echo "<h1>Limpieza de Registros Vacíos en Algolia</h1>\n";

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
    echo "<li>Index Name: " . ($index_name ?: 'No configurado') . "</li>\n";
    echo "</ul>\n";

    if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
        throw new Exception('Configuración de Algolia incompleta');
    }

    // Incluir la clase del cliente de Algolia
    require_once('includes/class-lexhoy-algolia-client.php');

    // Crear instancia del cliente
    $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, $search_api_key, $index_name);

    echo "<h2>Obteniendo todos los registros de Algolia...</h2>\n";
    
    // Obtener todos los registros
    $result = $client->browse_all_with_cursor();
    
    if (!$result['success']) {
        throw new Exception('Error al obtener registros: ' . $result['message']);
    }

    $all_hits = $result['hits'];
    $total_records = count($all_hits);
    
    echo "<p>Total de registros encontrados: <strong>{$total_records}</strong></p>\n";

    // Identificar registros vacíos
    $empty_records = [];
    $valid_records = [];
    
    echo "<h2>Analizando registros...</h2>\n";
    
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
        
        // Verificar si el registro está vacío (solo tiene slug pero no datos reales)
        $is_empty = empty($nombre) && 
                   empty($localidad) && 
                   empty($provincia) && 
                   empty($direccion) && 
                   empty($telefono) && 
                   empty($email) && 
                   empty($web) && 
                   empty($descripcion);
        
        // Verificar si es un registro generado automáticamente (contiene _dashboard_generated_id)
        $is_generated = strpos($object_id, '_dashboard_generated_id') !== false;
        
        if ($is_empty || $is_generated) {
            $empty_records[] = $hit;
        } else {
            $valid_records[] = $hit;
        }
    }
    
    echo "<h3>Resultados del análisis:</h3>\n";
    echo "<ul>\n";
    echo "<li>Registros válidos: <strong>" . count($valid_records) . "</strong></li>\n";
    echo "<li>Registros vacíos/generados: <strong>" . count($empty_records) . "</strong></li>\n";
    echo "</ul>\n";
    
    if (empty($empty_records)) {
        echo "<p style='color: green;'>✅ No se encontraron registros vacíos para eliminar.</p>\n";
    } else {
        echo "<h3>Registros vacíos encontrados (primeros 10):</h3>\n";
        echo "<ul>\n";
        for ($i = 0; $i < min(10, count($empty_records)); $i++) {
            $record = $empty_records[$i];
            echo "<li>ID: " . ($record['objectID'] ?? 'N/A') . " - Slug: " . ($record['slug'] ?? 'N/A') . "</li>\n";
        }
        if (count($empty_records) > 10) {
            echo "<li>... y " . (count($empty_records) - 10) . " más</li>\n";
        }
        echo "</ul>\n";
        
        // Preguntar si eliminar
        if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
            echo "<h2>Eliminando registros vacíos...</h2>\n";
            
            $deleted_count = 0;
            $error_count = 0;
            
            foreach ($empty_records as $record) {
                try {
                    $object_id = $record['objectID'];
                    $result = $client->delete_object($index_name, $object_id);
                    
                    if ($result) {
                        $deleted_count++;
                        echo "<p style='color: green;'>✅ Eliminado: {$object_id}</p>\n";
                    } else {
                        $error_count++;
                        echo "<p style='color: red;'>❌ Error eliminando: {$object_id}</p>\n";
                    }
                    
                    // Pausa pequeña para no sobrecargar la API
                    usleep(50000); // 50ms
                    
                } catch (Exception $e) {
                    $error_count++;
                    echo "<p style='color: red;'>❌ Error eliminando {$object_id}: " . $e->getMessage() . "</p>\n";
                }
            }
            
            echo "<h3>Resumen de eliminación:</h3>\n";
            echo "<ul>\n";
            echo "<li>Registros eliminados: <strong>{$deleted_count}</strong></li>\n";
            echo "<li>Errores: <strong>{$error_count}</strong></li>\n";
            echo "</ul>\n";
            
        } else {
            echo "<h2>¿Eliminar registros vacíos?</h2>\n";
            echo "<form method='post'>\n";
            echo "<input type='hidden' name='confirm_delete' value='yes'>\n";
            echo "<button type='submit' style='background: red; color: white; padding: 10px 20px; border: none; cursor: pointer;'>";
            echo "SÍ, ELIMINAR " . count($empty_records) . " REGISTROS VACÍOS";
            echo "</button>\n";
            echo "</form>\n";
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
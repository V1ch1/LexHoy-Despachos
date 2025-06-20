<?php
/**
 * Script de diagnóstico para entender por qué la importación solo trajo 6975 registros
 * 
 * Este script analiza los registros de Algolia y WordPress para identificar el problema
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

echo "<h1>Diagnóstico de Problema de Importación</h1>\n";

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

    echo "<h2>1. Análisis de registros en Algolia</h2>\n";
    
    // Obtener todos los registros de Algolia
    $result = $client->browse_all_with_cursor();
    
    if (!$result['success']) {
        throw new Exception('Error al obtener registros: ' . $result['message']);
    }

    $all_hits = $result['hits'];
    $total_algolia = count($all_hits);
    
    echo "<p>Total de registros en Algolia: <strong>{$total_algolia}</strong></p>\n";

    // Analizar registros por tipo
    $valid_records = [];
    $empty_records = [];
    $generated_records = [];
    $error_records = [];
    
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
        
        // Verificar si el registro está vacío
        $is_empty = empty($nombre) && 
                   empty($localidad) && 
                   empty($provincia) && 
                   empty($direccion) && 
                   empty($telefono) && 
                   empty($email) && 
                   empty($web) && 
                   empty($descripcion);
        
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
        } else {
            $error_records[] = $hit;
        }
    }
    
    echo "<h3>Análisis de registros en Algolia:</h3>\n";
    echo "<ul>\n";
    echo "<li>Registros válidos (con datos mínimos): <strong>" . count($valid_records) . "</strong></li>\n";
    echo "<li>Registros vacíos (sin datos): <strong>" . count($empty_records) . "</strong></li>\n";
    echo "<li>Registros generados automáticamente: <strong>" . count($generated_records) . "</strong></li>\n";
    echo "<li>Registros con errores: <strong>" . count($error_records) . "</strong></li>\n";
    echo "</ul>\n";

    echo "<h2>2. Análisis de registros en WordPress</h2>\n";
    
    // Contar despachos en WordPress
    $wp_despachos = get_posts(array(
        'post_type' => 'despacho',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids'
    ));
    
    $total_wp = count($wp_despachos);
    echo "<p>Total de despachos en WordPress: <strong>{$total_wp}</strong></p>\n";
    
    // Contar despachos con objectID de Algolia
    $wp_with_algolia_id = get_posts(array(
        'post_type' => 'despacho',
        'post_status' => 'any',
        'numberposts' => -1,
        'meta_key' => '_algolia_object_id',
        'meta_compare' => 'EXISTS',
        'fields' => 'ids'
    ));
    
    $total_wp_with_algolia = count($wp_with_algolia_id);
    echo "<p>Despachos en WordPress con objectID de Algolia: <strong>{$total_wp_with_algolia}</strong></p>\n";
    
    // Contar despachos publicados
    $wp_published = get_posts(array(
        'post_type' => 'despacho',
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids'
    ));
    
    $total_wp_published = count($wp_published);
    echo "<p>Despachos publicados en WordPress: <strong>{$total_wp_published}</strong></p>\n";

    echo "<h2>3. Análisis de la diferencia</h2>\n";
    
    $expected_import = count($valid_records);
    $actual_import = $total_wp_with_algolia;
    $difference = $expected_import - $actual_import;
    
    echo "<ul>\n";
    echo "<li>Registros válidos en Algolia: <strong>{$expected_import}</strong></li>\n";
    echo "<li>Registros importados en WordPress: <strong>{$actual_import}</strong></li>\n";
    echo "<li>Diferencia: <strong>{$difference}</strong></li>\n";
    echo "</ul>\n";
    
    if ($difference > 0) {
        echo "<p style='color: orange;'>⚠️ Faltan {$difference} registros por importar.</p>\n";
    } elseif ($difference < 0) {
        echo "<p style='color: red;'>❌ Hay " . abs($difference) . " registros más en WordPress que en Algolia.</p>\n";
    } else {
        echo "<p style='color: green;'>✅ Los números coinciden perfectamente.</p>\n";
    }

    echo "<h2>4. Muestra de registros válidos (primeros 5)</h2>\n";
    if (!empty($valid_records)) {
        echo "<ul>\n";
        for ($i = 0; $i < min(5, count($valid_records)); $i++) {
            $record = $valid_records[$i];
            echo "<li>ID: " . ($record['objectID'] ?? 'N/A') . " - Nombre: " . ($record['nombre'] ?? 'N/A') . " - Localidad: " . ($record['localidad'] ?? 'N/A') . "</li>\n";
        }
        echo "</ul>\n";
    }

    echo "<h2>5. Muestra de registros vacíos (primeros 5)</h2>\n";
    if (!empty($empty_records)) {
        echo "<ul>\n";
        for ($i = 0; $i < min(5, count($empty_records)); $i++) {
            $record = $empty_records[$i];
            echo "<li>ID: " . ($record['objectID'] ?? 'N/A') . " - Slug: " . ($record['slug'] ?? 'N/A') . "</li>\n";
        }
        echo "</ul>\n";
    }

    echo "<h2>6. Muestra de registros generados (primeros 5)</h2>\n";
    if (!empty($generated_records)) {
        echo "<ul>\n";
        for ($i = 0; $i < min(5, count($generated_records)); $i++) {
            $record = $generated_records[$i];
            echo "<li>ID: " . ($record['objectID'] ?? 'N/A') . " - Slug: " . ($record['slug'] ?? 'N/A') . "</li>\n";
        }
        echo "</ul>\n";
    }

    echo "<h2>7. Recomendaciones</h2>\n";
    echo "<ol>\n";
    
    if (count($generated_records) > 0) {
        echo "<li><strong>Eliminar registros generados automáticamente:</strong> " . count($generated_records) . " registros con '_dashboard_generated_id' deben ser eliminados de Algolia.</li>\n";
    }
    
    if (count($empty_records) > 0) {
        echo "<li><strong>Revisar registros vacíos:</strong> " . count($empty_records) . " registros están vacíos y podrían ser eliminados.</li>\n";
    }
    
    if ($difference > 0) {
        echo "<li><strong>Reintentar importación:</strong> Faltan {$difference} registros por importar. Ejecutar la importación masiva nuevamente.</li>\n";
    }
    
    echo "</ol>\n";

} catch (Exception $e) {
    echo "<h2>Error:</h2>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
    echo "<p>Stack trace:</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}

echo "<hr>\n";
echo "<p><a href='javascript:history.back()'>← Volver</a></p>\n";
?> 
<?php
/**
 * Script de diagnóstico para entender por qué no se encuentran áreas de práctica
 * 
 * Este script analiza los registros de Algolia para identificar la estructura de datos
 * y verificar si existen áreas de práctica
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

echo "<h1>🔍 Diagnóstico de Áreas de Práctica</h1>\n";
echo "<p>Analizando la estructura de datos en Algolia para identificar áreas de práctica...</p>\n";

try {
    // Obtener configuración de Algolia
    $app_id = get_option('lexhoy_despachos_algolia_app_id');
    $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
    $search_api_key = get_option('lexhoy_despachos_algolia_search_api_key');
    $index_name = get_option('lexhoy_despachos_algolia_index_name');

    echo "<h2>📋 Configuración de Algolia:</h2>\n";
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

    echo "<h2>🔍 Analizando registros en Algolia...</h2>\n";
    
    // Obtener una muestra de registros para análisis
    $result = $client->browse_page_unfiltered(0, 10);
    
    if (!$result['success']) {
        throw new Exception('Error al obtener registros: ' . $result['message']);
    }

    $sample_records = $result['hits'];
    
    if (empty($sample_records)) {
        echo "<p>❌ No se encontraron registros en Algolia.</p>\n";
        return;
    }

    echo "<p>✅ Se encontraron " . count($sample_records) . " registros de muestra.</p>\n";

    // Analizar la estructura de cada registro
    echo "<h3>📊 Análisis de Estructura de Datos:</h3>\n";
    
    $areas_found = array();
    $possible_area_fields = array();
    
    foreach ($sample_records as $index => $record) {
        echo "<h4>Registro " . ($index + 1) . " (ID: " . ($record['objectID'] ?? 'N/A') . "):</h4>\n";
        echo "<div style='background: #f9f9f9; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa;'>\n";
        
        // Mostrar todos los campos disponibles
        echo "<strong>Campos disponibles:</strong><br>\n";
        foreach ($record as $key => $value) {
            $display_value = is_array($value) ? 'Array(' . count($value) . ' elementos)' : $value;
            echo "• <strong>{$key}:</strong> " . htmlspecialchars($display_value) . "<br>\n";
            
            // Buscar campos que podrían contener áreas de práctica
            if (stripos($key, 'area') !== false || stripos($key, 'especialidad') !== false || stripos($key, 'practica') !== false) {
                $possible_area_fields[$key] = true;
            }
        }
        
        // Verificar específicamente áreas de práctica
        if (isset($record['areas_practica'])) {
            echo "<br><strong style='color: green;'>✅ Campo 'areas_practica' encontrado:</strong><br>\n";
            if (is_array($record['areas_practica'])) {
                echo "Contenido: " . implode(', ', $record['areas_practica']) . "<br>\n";
                foreach ($record['areas_practica'] as $area) {
                    $areas_found[$area] = true;
                }
            } else {
                echo "Contenido: " . htmlspecialchars($record['areas_practica']) . "<br>\n";
            }
        } else {
            echo "<br><strong style='color: red;'>❌ Campo 'areas_practica' NO encontrado</strong><br>\n";
        }
        
        echo "</div>\n";
    }

    // Mostrar resumen de campos que podrían contener áreas
    if (!empty($possible_area_fields)) {
        echo "<h3>🔍 Campos que podrían contener áreas de práctica:</h3>\n";
        echo "<ul>\n";
        foreach (array_keys($possible_area_fields) as $field) {
            echo "<li><strong>{$field}</strong></li>\n";
        }
        echo "</ul>\n";
    }

    // Mostrar áreas encontradas
    if (!empty($areas_found)) {
        echo "<h3>✅ Áreas de práctica encontradas:</h3>\n";
        echo "<ul>\n";
        foreach (array_keys($areas_found) as $area) {
            echo "<li>" . htmlspecialchars($area) . "</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<h3>❌ No se encontraron áreas de práctica</h3>\n";
        echo "<p>Esto explica por qué la sincronización falla.</p>\n";
    }

    // Verificar si hay registros con datos
    echo "<h3>📈 Estadísticas de Registros:</h3>\n";
    $total_records = $client->get_total_count_simple();
    echo "<p>Total de registros en Algolia: <strong>" . number_format($total_records) . "</strong></p>\n";

    // Obtener más registros para análisis más completo
    if ($total_records > 10) {
        echo "<h3>🔍 Análisis de más registros...</h3>\n";
        
        $more_records = $client->browse_page_unfiltered(0, 100);
        if ($more_records['success']) {
            $areas_in_more_records = array();
            $records_with_areas = 0;
            
            foreach ($more_records['hits'] as $record) {
                if (isset($record['areas_practica']) && is_array($record['areas_practica']) && !empty($record['areas_practica'])) {
                    $records_with_areas++;
                    foreach ($record['areas_practica'] as $area) {
                        $areas_in_more_records[$area] = true;
                    }
                }
            }
            
            echo "<p>Registros con áreas de práctica: <strong>{$records_with_areas}</strong> de " . count($more_records['hits']) . "</p>\n";
            echo "<p>Áreas únicas encontradas: <strong>" . count($areas_in_more_records) . "</strong></p>\n";
            
            if (!empty($areas_in_more_records)) {
                echo "<p>Lista de áreas:</p>\n";
                echo "<ul>\n";
                foreach (array_keys($areas_in_more_records) as $area) {
                    echo "<li>" . htmlspecialchars($area) . "</li>\n";
                }
                echo "</ul>\n";
            }
        }
    }

    // Recomendaciones
    echo "<h3>💡 Recomendaciones:</h3>\n";
    echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;'>\n";
    
    if (empty($areas_found)) {
        echo "<p><strong>El problema es que no hay áreas de práctica en Algolia.</strong></p>\n";
        echo "<p>Opciones para solucionarlo:</p>\n";
        echo "<ol>\n";
        echo "<li><strong>Crear áreas manualmente:</strong> Ve a <strong>Despachos > Áreas de Práctica</strong> y crea las áreas que necesites</li>\n";
        echo "<li><strong>Importar despachos primero:</strong> Usa la <strong>Importación Masiva</strong> para traer despachos con áreas</li>\n";
        echo "<li><strong>Verificar Algolia:</strong> Asegúrate de que los despachos en Algolia tengan el campo 'areas_practica'</li>\n";
        echo "</ol>\n";
    } else {
        echo "<p><strong>Se encontraron áreas de práctica.</strong> El problema podría ser en el método de sincronización.</p>\n";
        echo "<p>Intenta:</p>\n";
        echo "<ol>\n";
        echo "<li>Usar la <strong>Importación Masiva</strong> en lugar de sincronizar solo áreas</li>\n";
        echo "<li>Verificar que la configuración de Algolia sea correcta</li>\n";
        echo "</ol>\n";
    }
    
    echo "</div>\n";

} catch (Exception $e) {
    echo "<h2>❌ Error:</h2>\n";
    echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>\n";
}
?> 
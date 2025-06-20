<?php
/**
 * Script para limpiar SOLO los registros vacíos recientes
 * 
 * Este script elimina registros que solo tienen slug pero no tienen datos reales
 * Tanto de Algolia como de WordPress, manteniendo los registros originales con datos
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

echo "<h1>Limpieza de Registros Vacíos Recientes</h1>\n";
echo "<p><strong>IMPORTANTE:</strong> Este script solo eliminará registros que NO tienen datos reales (solo slug).</p>\n";

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

    echo "<h2>1. Analizando registros en Algolia...</h2>\n";
    
    // Obtener todos los registros de Algolia
    $result = $client->browse_all_with_cursor();
    
    if (!$result['success']) {
        throw new Exception('Error al obtener registros: ' . $result['message']);
    }

    $all_hits = $result['hits'];
    $total_algolia = count($all_hits);
    
    echo "<p>Total de registros en Algolia: <strong>{$total_algolia}</strong></p>\n";

    // Analizar registros de Algolia
    $algolia_empty_records = [];
    $algolia_valid_records = [];
    
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
        
        // Verificar si es un registro generado automáticamente
        $is_generated = strpos($object_id, '_dashboard_generated_id') !== false;
        
        if ($is_empty || $is_generated) {
            $algolia_empty_records[] = $hit;
        } else {
            $algolia_valid_records[] = $hit;
        }
    }
    
    echo "<h3>Análisis de Algolia:</h3>\n";
    echo "<ul>\n";
    echo "<li>Registros válidos (con datos): <strong>" . count($algolia_valid_records) . "</strong></li>\n";
    echo "<li>Registros vacíos/generados: <strong>" . count($algolia_empty_records) . "</strong></li>\n";
    echo "</ul>\n";

    echo "<h2>2. Analizando registros en WordPress...</h2>\n";
    
    // Obtener todos los despachos de WordPress
    $wp_despachos = get_posts(array(
        'post_type' => 'despacho',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'all'
    ));
    
    $total_wp = count($wp_despachos);
    echo "<p>Total de despachos en WordPress: <strong>{$total_wp}</strong></p>\n";

    // Analizar registros de WordPress
    $wp_empty_records = [];
    $wp_valid_records = [];
    
    foreach ($wp_despachos as $post) {
        $post_id = $post->ID;
        $nombre = trim(get_post_meta($post_id, '_despacho_nombre', true));
        $localidad = trim(get_post_meta($post_id, '_despacho_localidad', true));
        $provincia = trim(get_post_meta($post_id, '_despacho_provincia', true));
        $direccion = trim(get_post_meta($post_id, '_despacho_direccion', true));
        $telefono = trim(get_post_meta($post_id, '_despacho_telefono', true));
        $email = trim(get_post_meta($post_id, '_despacho_email', true));
        $web = trim(get_post_meta($post_id, '_despacho_web', true));
        $descripcion = trim(get_post_meta($post_id, '_despacho_descripcion', true));
        
        // Verificar si el registro está vacío
        $is_empty = empty($nombre) && 
                   empty($localidad) && 
                   empty($provincia) && 
                   empty($direccion) && 
                   empty($telefono) && 
                   empty($email) && 
                   empty($web) && 
                   empty($descripcion);
        
        if ($is_empty) {
            $wp_empty_records[] = $post;
        } else {
            $wp_valid_records[] = $post;
        }
    }
    
    echo "<h3>Análisis de WordPress:</h3>\n";
    echo "<ul>\n";
    echo "<li>Despachos válidos (con datos): <strong>" . count($wp_valid_records) . "</strong></li>\n";
    echo "<li>Despachos vacíos: <strong>" . count($wp_empty_records) . "</strong></li>\n";
    echo "</ul>\n";

    echo "<h2>3. Resumen de limpieza</h2>\n";
    echo "<p><strong>Se eliminarán:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>De Algolia: <strong>" . count($algolia_empty_records) . "</strong> registros vacíos</li>\n";
    echo "<li>De WordPress: <strong>" . count($wp_empty_records) . "</strong> despachos vacíos</li>\n";
    echo "</ul>\n";
    
    echo "<p><strong>Se mantendrán:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>En Algolia: <strong>" . count($algolia_valid_records) . "</strong> registros con datos</li>\n";
    echo "<li>En WordPress: <strong>" . count($wp_valid_records) . "</strong> despachos con datos</li>\n";
    echo "</ul>\n";

    if (empty($algolia_empty_records) && empty($wp_empty_records)) {
        echo "<p style='color: green;'>✅ No se encontraron registros vacíos para eliminar.</p>\n";
    } else {
        // Mostrar algunos ejemplos de registros que se eliminarán
        echo "<h3>Ejemplos de registros que se eliminarán:</h3>\n";
        
        if (!empty($algolia_empty_records)) {
            echo "<h4>De Algolia (primeros 5):</h4>\n";
            echo "<ul>\n";
            for ($i = 0; $i < min(5, count($algolia_empty_records)); $i++) {
                $record = $algolia_empty_records[$i];
                echo "<li>ID: " . ($record['objectID'] ?? 'N/A') . " - Slug: " . ($record['slug'] ?? 'N/A') . "</li>\n";
            }
            echo "</ul>\n";
        }
        
        if (!empty($wp_empty_records)) {
            echo "<h4>De WordPress (primeros 5):</h4>\n";
            echo "<ul>\n";
            for ($i = 0; $i < min(5, count($wp_empty_records)); $i++) {
                $post = $wp_empty_records[$i];
                echo "<li>ID: " . $post->ID . " - Slug: " . $post->post_name . " - Título: " . $post->post_title . "</li>\n";
            }
            echo "</ul>\n";
        }
        
        // Preguntar si eliminar
        if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
            echo "<h2>Eliminando registros vacíos...</h2>\n";
            
            $algolia_deleted = 0;
            $wp_deleted = 0;
            $errors = 0;
            
            // Eliminar de Algolia
            if (!empty($algolia_empty_records)) {
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
            }
            
            // Eliminar de WordPress
            if (!empty($wp_empty_records)) {
                echo "<h3>Eliminando de WordPress...</h3>\n";
                foreach ($wp_empty_records as $post) {
                    try {
                        $result = wp_delete_post($post->ID, true); // true = eliminar permanentemente
                        
                        if ($result) {
                            $wp_deleted++;
                            echo "<p style='color: green;'>✅ WordPress: Eliminado post ID {$post->ID} ({$post->post_title})</p>\n";
                        } else {
                            $errors++;
                            echo "<p style='color: red;'>❌ WordPress: Error eliminando post ID {$post->ID}</p>\n";
                        }
                        
                    } catch (Exception $e) {
                        $errors++;
                        echo "<p style='color: red;'>❌ WordPress: Error eliminando post ID {$post->ID}: " . $e->getMessage() . "</p>\n";
                    }
                }
            }
            
            echo "<h3>Resumen de eliminación:</h3>\n";
            echo "<ul>\n";
            echo "<li>Eliminados de Algolia: <strong>{$algolia_deleted}</strong></li>\n";
            echo "<li>Eliminados de WordPress: <strong>{$wp_deleted}</strong></li>\n";
            echo "<li>Errores: <strong>{$errors}</strong></li>\n";
            echo "</ul>\n";
            
            echo "<p style='color: green;'>✅ Limpieza completada. Solo quedan registros con datos válidos.</p>\n";
            
        } else {
            echo "<h2>¿Proceder con la limpieza?</h2>\n";
            echo "<p><strong>ATENCIÓN:</strong> Esta acción eliminará permanentemente los registros vacíos tanto de Algolia como de WordPress.</p>\n";
            echo "<form method='post'>\n";
            echo "<input type='hidden' name='confirm_delete' value='yes'>\n";
            echo "<button type='submit' style='background: red; color: white; padding: 10px 20px; border: none; cursor: pointer;'>";
            echo "SÍ, ELIMINAR REGISTROS VACÍOS";
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
<?php
/**
 * Script de Migración Controlada desde Algolia a WordPress
 * 
 * Este script realiza una migración segura y controlada de despachos desde Algolia
 * a WordPress, procesando por bloques y con verificación en cada paso.
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>🚀 Migración Controlada: Algolia → WordPress</h1>\n";
echo "<p><strong>⚠️ IMPORTANTE:</strong> Este proceso migrará despachos válidos de Algolia a WordPress.</p>\n";
echo "<p><strong>✅ SEGURO:</strong> Solo migrará registros con datos reales, no registros vacíos.</p>\n";

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

    // Verificar si se está ejecutando la migración
    if (isset($_POST['action']) && $_POST['action'] === 'start_migration') {
        execute_migration($client);
    } else {
        show_migration_form($client);
    }

} catch (Exception $e) {
    echo "<h2>Error:</h2>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
}

function show_migration_form($client) {
    echo "<h2>📊 Análisis Preliminar</h2>\n";
    
    // Obtener estadísticas de Algolia
    echo "<h3>1. Analizando registros en Algolia...</h3>\n";
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
        }
    }
    
    echo "<h3>2. Análisis de Registros:</h3>\n";
    echo "<ul>\n";
    echo "<li>✅ <strong>Registros válidos para migrar:</strong> <span style='color: green; font-weight: bold;'>" . count($valid_records) . "</span></li>\n";
    echo "<li>❌ <strong>Registros vacíos (se ignorarán):</strong> <span style='color: red;'>" . count($empty_records) . "</span></li>\n";
    echo "<li>⚠️ <strong>Registros generados automáticamente (se ignorarán):</strong> <span style='color: orange;'>" . count($generated_records) . "</span></li>\n";
    echo "</ul>\n";

    // Obtener estadísticas de WordPress
    echo "<h3>3. Estado actual de WordPress:</h3>\n";
    $wp_despachos = get_posts(array(
        'post_type' => 'despacho',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids'
    ));
    $total_wp = count($wp_despachos);
    echo "<p>Despachos actuales en WordPress: <strong>{$total_wp}</strong></p>\n";

    // Mostrar ejemplos de registros válidos
    echo "<h3>4. Ejemplos de registros que se migrarán:</h3>\n";
    echo "<div style='max-height: 300px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>\n";
    for ($i = 0; $i < min(5, count($valid_records)); $i++) {
        $record = $valid_records[$i];
        echo "<div style='margin-bottom: 10px; padding: 10px; background: white; border-left: 4px solid green;'>\n";
        echo "<strong>ID:</strong> " . ($record['objectID'] ?? 'N/A') . "<br>\n";
        echo "<strong>Nombre:</strong> " . ($record['nombre'] ?? 'N/A') . "<br>\n";
        echo "<strong>Localidad:</strong> " . ($record['localidad'] ?? 'N/A') . "<br>\n";
        echo "<strong>Provincia:</strong> " . ($record['provincia'] ?? 'N/A') . "<br>\n";
        echo "<strong>Teléfono:</strong> " . ($record['telefono'] ?? 'N/A') . "<br>\n";
        echo "</div>\n";
    }
    echo "</div>\n";

    // Formulario de migración
    if (count($valid_records) > 0) {
        echo "<h2>🚀 Iniciar Migración</h2>\n";
        echo "<p><strong>Configuración de la migración:</strong></p>\n";
        echo "<ul>\n";
        echo "<li>📦 <strong>Tamaño de bloque:</strong> 50 registros por lote</li>\n";
        echo "<li>⏱️ <strong>Pausa entre bloques:</strong> 2 segundos</li>\n";
        echo "<li>🔄 <strong>Verificación:</strong> Cada registro se valida antes de crear</li>\n";
        echo "<li>📝 <strong>Log detallado:</strong> Se muestra el progreso en tiempo real</li>\n";
        echo "</ul>\n";
        
        echo "<form method='post' style='margin-top: 20px;'>\n";
        echo "<input type='hidden' name='action' value='start_migration'>\n";
        echo "<button type='submit' class='button button-primary' style='background: #0073aa; color: white; padding: 15px 30px; border: none; cursor: pointer; font-size: 16px; font-weight: bold;'>";
        echo "🚀 INICIAR MIGRACIÓN DE " . count($valid_records) . " DESPACHOS";
        echo "</button>\n";
        echo "</form>\n";
    } else {
        echo "<p style='color: orange; font-size: 18px;'>⚠️ No hay registros válidos para migrar.</p>\n";
    }

    echo "<hr>\n";
    echo "<p><a href='javascript:history.back()'>← Volver</a></p>\n";
}

function execute_migration($client) {
    echo "<h2>🚀 Ejecutando Migración Controlada</h2>\n";
    
    try {
        // Obtener todos los registros válidos de Algolia
        echo "<h3>1. Obteniendo registros válidos de Algolia...</h3>\n";
        $result = $client->browse_all_with_cursor();
        
        if (!$result['success']) {
            throw new Exception('Error al obtener registros: ' . $result['message']);
        }

        $all_hits = $result['hits'];
        $valid_records = [];
        
        foreach ($all_hits as $hit) {
            $object_id = $hit['objectID'] ?? '';
            $nombre = trim($hit['nombre'] ?? '');
            $localidad = trim($hit['localidad'] ?? '');
            $provincia = trim($hit['provincia'] ?? '');
            
            // Verificar si es un registro generado automáticamente
            $is_generated = strpos($object_id, '_dashboard_generated_id') !== false;
            
            // Verificar si tiene datos mínimos válidos
            $has_minimal_data = !empty($nombre) || !empty($localidad) || !empty($provincia);
            
            if (!$is_generated && $has_minimal_data) {
                $valid_records[] = $hit;
            }
        }
        
        $total_valid = count($valid_records);
        echo "<p>Registros válidos encontrados: <strong>{$total_valid}</strong></p>\n";

        if ($total_valid === 0) {
            echo "<p style='color: orange;'>⚠️ No hay registros válidos para migrar.</p>\n";
            return;
        }

        // Configuración de la migración
        $block_size = 50;
        $total_blocks = ceil($total_valid / $block_size);
        $total_created = 0;
        $total_errors = 0;
        $total_skipped = 0;

        echo "<h3>2. Iniciando migración por bloques...</h3>\n";
        echo "<p>Total de bloques a procesar: <strong>{$total_blocks}</strong></p>\n";
        echo "<p>Tamaño de cada bloque: <strong>{$block_size}</strong> registros</p>\n";

        // Procesar por bloques
        for ($block = 0; $block < $total_blocks; $block++) {
            $start_index = $block * $block_size;
            $end_index = min($start_index + $block_size, $total_valid);
            $block_records = array_slice($valid_records, $start_index, $block_size);
            
            echo "<h4>📦 Procesando Bloque " . ($block + 1) . " de {$total_blocks}</h4>\n";
            echo "<p>Registros en este bloque: <strong>" . count($block_records) . "</strong></p>\n";
            
            $block_created = 0;
            $block_errors = 0;
            $block_skipped = 0;
            
            foreach ($block_records as $index => $record) {
                $record_number = $start_index + $index + 1;
                $object_id = $record['objectID'] ?? '';
                $nombre = trim($record['nombre'] ?? '');
                
                echo "<div style='margin: 5px 0; padding: 5px; background: #f0f0f0; border-left: 3px solid #0073aa;'>\n";
                echo "<strong>Registro {$record_number}/{$total_valid}:</strong> {$nombre} (ID: {$object_id})\n";
                
                try {
                    // Verificar si ya existe en WordPress
                    $existing_posts = get_posts(array(
                        'post_type' => 'despacho',
                        'meta_query' => array(
                            array(
                                'key' => '_despacho_object_id',
                                'value' => $object_id,
                                'compare' => '='
                            )
                        ),
                        'post_status' => 'any',
                        'numberposts' => 1
                    ));
                    
                    if (!empty($existing_posts)) {
                        echo " → <span style='color: orange;'>⚠️ Ya existe en WordPress (se omite)</span>\n";
                        $block_skipped++;
                        $total_skipped++;
                    } else {
                        // Crear el despacho en WordPress
                        $post_data = array(
                            'post_title' => $nombre ?: 'Despacho sin nombre',
                            'post_content' => $record['descripcion'] ?? '',
                            'post_status' => 'publish',
                            'post_type' => 'despacho'
                        );
                        
                        $post_id = wp_insert_post($post_data);
                        
                        if ($post_id && !is_wp_error($post_id)) {
                            // Guardar metadatos
                            $meta_fields = array(
                                '_despacho_object_id' => $object_id,
                                '_despacho_nombre' => $nombre,
                                '_despacho_localidad' => trim($record['localidad'] ?? ''),
                                '_despacho_provincia' => trim($record['provincia'] ?? ''),
                                '_despacho_codigo_postal' => trim($record['codigo_postal'] ?? ''),
                                '_despacho_direccion' => trim($record['direccion'] ?? ''),
                                '_despacho_telefono' => trim($record['telefono'] ?? ''),
                                '_despacho_email' => trim($record['email'] ?? ''),
                                '_despacho_web' => trim($record['web'] ?? ''),
                                '_despacho_descripcion' => trim($record['descripcion'] ?? ''),
                                '_despacho_especialidades' => trim($record['especialidades'] ?? ''),
                                '_despacho_horario' => trim($record['horario'] ?? ''),
                                '_despacho_redes_sociales' => trim($record['redes_sociales'] ?? ''),
                                '_despacho_experiencia' => trim($record['experiencia'] ?? ''),
                                '_despacho_tamaño' => trim($record['tamano_despacho'] ?? ''),
                                '_despacho_año_fundacion' => trim($record['ano_fundacion'] ?? ''),
                                '_despacho_estado_registro' => 'activo'
                            );
                            
                            foreach ($meta_fields as $key => $value) {
                                update_post_meta($post_id, $key, $value);
                            }
                            
                            echo " → <span style='color: green;'>✅ Creado exitosamente (ID: {$post_id})</span>\n";
                            $block_created++;
                            $total_created++;
                        } else {
                            echo " → <span style='color: red;'>❌ Error al crear: " . (is_wp_error($post_id) ? $post_id->get_error_message() : 'Error desconocido') . "</span>\n";
                            $block_errors++;
                            $total_errors++;
                        }
                    }
                } catch (Exception $e) {
                    echo " → <span style='color: red;'>❌ Error: " . $e->getMessage() . "</span>\n";
                    $block_errors++;
                    $total_errors++;
                }
                
                echo "</div>\n";
                
                // Flush output para mostrar progreso en tiempo real
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
            
            echo "<p><strong>Resumen del bloque " . ($block + 1) . ":</strong></p>\n";
            echo "<ul>\n";
            echo "<li>✅ Creados: <strong>{$block_created}</strong></li>\n";
            echo "<li>⚠️ Omitidos: <strong>{$block_skipped}</strong></li>\n";
            echo "<li>❌ Errores: <strong>{$block_errors}</strong></li>\n";
            echo "</ul>\n";
            
            // Pausa entre bloques (excepto el último)
            if ($block < $total_blocks - 1) {
                echo "<p>⏱️ Pausa de 2 segundos antes del siguiente bloque...</p>\n";
                sleep(2);
            }
        }
        
        // Resumen final
        echo "<h3>3. Resumen Final de la Migración</h3>\n";
        echo "<div style='background: #f0f8ff; padding: 20px; border-left: 4px solid #0073aa;'>\n";
        echo "<h4>📊 Estadísticas Totales:</h4>\n";
        echo "<ul>\n";
        echo "<li>✅ <strong>Despachos creados:</strong> <span style='color: green; font-size: 18px;'>{$total_created}</span></li>\n";
        echo "<li>⚠️ <strong>Despachos omitidos (ya existían):</strong> <span style='color: orange;'>{$total_skipped}</span></li>\n";
        echo "<li>❌ <strong>Errores:</strong> <span style='color: red;'>{$total_errors}</span></li>\n";
        echo "<li>📦 <strong>Bloques procesados:</strong> {$total_blocks}</li>\n";
        echo "</ul>\n";
        echo "</div>\n";
        
        // Verificar estado final
        $final_wp_count = get_posts(array(
            'post_type' => 'despacho',
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids'
        ));
        $final_count = count($final_wp_count);
        
        echo "<p><strong>Estado final de WordPress:</strong> <span style='color: green; font-size: 18px;'>{$final_count} despachos</span></p>\n";
        
        if ($total_created > 0) {
            echo "<p style='color: green; font-size: 18px;'>🎉 ¡Migración completada exitosamente!</p>\n";
        }

    } catch (Exception $e) {
        echo "<h3>Error durante la migración:</h3>\n";
        echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
    }

    echo "<hr>\n";
    echo "<p><a href='javascript:history.back()'>← Volver</a></p>\n";
}
?> 
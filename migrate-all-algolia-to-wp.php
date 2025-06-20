<?php
/**
 * Script de Migración COMPLETA desde Algolia a WordPress
 * 
 * Este script migra TODOS los registros de Algolia a WordPress,
 * sin filtrar por validación. Úsalo solo si estás seguro de que
 * quieres todos los registros tal como están en Algolia.
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>🚀 Migración COMPLETA: Algolia → WordPress</h1>\n";
echo "<p><strong>⚠️ ATENCIÓN:</strong> Este script migrará TODOS los registros de Algolia a WordPress.</p>\n";
echo "<p><strong>📋 INCLUYE:</strong> Registros válidos, vacíos y generados automáticamente.</p>\n";

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
        execute_complete_migration($client);
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
    $result = $client->browse_all_unfiltered();
    
    if (!$result['success']) {
        throw new Exception('Error al obtener registros: ' . $result['message']);
    }

    $all_hits = $result['hits'];
    $total_algolia = count($all_hits);
    
    echo "<p>Total de registros en Algolia: <strong>{$total_algolia}</strong></p>\n";

    // Analizar registros por tipo (solo para información)
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
    
    echo "<h3>2. Análisis de Registros (solo informativo):</h3>\n";
    echo "<ul>\n";
    echo "<li>✅ <strong>Registros válidos:</strong> <span style='color: green; font-weight: bold;'>" . count($valid_records) . "</span></li>\n";
    echo "<li>❌ <strong>Registros vacíos:</strong> <span style='color: red;'>" . count($empty_records) . "</span></li>\n";
    echo "<li>⚠️ <strong>Registros generados automáticamente:</strong> <span style='color: orange;'>" . count($generated_records) . "</span></li>\n";
    echo "</ul>\n";
    echo "<p><strong>🎯 IMPORTANTE:</strong> Se migrarán TODOS los {$total_algolia} registros, sin importar su estado.</p>\n";

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

    // Mostrar ejemplos de registros
    echo "<h3>4. Ejemplos de registros que se migrarán:</h3>\n";
    echo "<div style='max-height: 300px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>\n";
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
        echo "</div>\n";
    }
    echo "</div>\n";

    // Formulario de migración
    echo "<h2>🚀 Iniciar Migración COMPLETA</h2>\n";
    echo "<p><strong>Configuración de la migración:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>📦 <strong>Tamaño de bloque:</strong> 50 registros por lote</li>\n";
    echo "<li>⏱️ <strong>Pausa entre bloques:</strong> 2 segundos</li>\n";
    echo "<li>🔄 <strong>Migración:</strong> TODOS los registros sin filtrado</li>\n";
    echo "<li>📝 <strong>Log detallado:</strong> Se muestra el progreso en tiempo real</li>\n";
    echo "</ul>\n";
    
    echo "<form method='post' style='margin-top: 20px;'>\n";
    echo "<input type='hidden' name='action' value='start_migration'>\n";
    echo "<button type='submit' class='button button-primary' style='background: #d63638; color: white; padding: 15px 30px; border: none; cursor: pointer; font-size: 16px; font-weight: bold;'>";
    echo "🚀 MIGRAR TODOS LOS {$total_algolia} REGISTROS DE ALGOLIA";
    echo "</button>\n";
    echo "</form>\n";

    echo "<hr>\n";
    echo "<p><a href='javascript:history.back()'>← Volver</a></p>\n";
}

function execute_complete_migration($client) {
    echo "<h2>🚀 Ejecutando Migración COMPLETA</h2>\n";
    
    // Función de logging local
    function migration_log($message) {
        $wp_content_dir = WP_CONTENT_DIR ?: dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-content';
        $log_file = $wp_content_dir . '/lexhoy-debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] MIGRATION: {$message}" . PHP_EOL;
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        echo "<p style='font-family: monospace; font-size: 12px; color: #666;'>[LOG] {$message}</p>\n";
    }
    
    migration_log("=== INICIO DE MIGRACIÓN COMPLETA ===");
    
    try {
        // Obtener TODOS los registros de Algolia
        echo "<h3>1. Obteniendo TODOS los registros de Algolia...</h3>\n";
        migration_log("Iniciando obtención de registros de Algolia");
        
        $result = $client->browse_all_unfiltered();
        
        if (!$result['success']) {
            migration_log("ERROR: " . $result['message']);
            throw new Exception('Error al obtener registros: ' . $result['message']);
        }

        $all_hits = $result['hits'];
        $total_records = count($all_hits);
        
        migration_log("Registros obtenidos exitosamente: {$total_records}");
        echo "<p>Total de registros a migrar: <strong>{$total_records}</strong></p>\n";

        if ($total_records === 0) {
            migration_log("ADVERTENCIA: No hay registros para migrar");
            echo "<p style='color: orange;'>⚠️ No hay registros para migrar.</p>\n";
            return;
        }

        // Configuración de la migración
        $batch_size = 50;
        $total_batches = ceil($total_records / $batch_size);
        $migrated_count = 0;
        $errors = [];

        migration_log("Configuración: {$total_batches} lotes de {$batch_size} registros cada uno");
        echo "<h3>2. Iniciando migración por lotes...</h3>\n";
        echo "<p>Procesando {$total_records} registros en {$total_batches} lotes de {$batch_size} registros cada uno.</p>\n";

        // Procesar por lotes
        for ($batch = 0; $batch < $total_batches; $batch++) {
            $start_index = $batch * $batch_size;
            $end_index = min($start_index + $batch_size, $total_records);
            $batch_records = array_slice($all_hits, $start_index, $batch_size);

            migration_log("Procesando lote " . ($batch + 1) . "/{$total_batches} (registros " . ($start_index + 1) . "-{$end_index})");
            echo "<h4>📦 Procesando lote " . ($batch + 1) . "/{$total_batches} (registros " . ($start_index + 1) . "-{$end_index})</h4>\n";

            foreach ($batch_records as $index => $record) {
                $record_index = $start_index + $index + 1;
                
                try {
                    migration_log("Procesando registro {$record_index}: " . ($record['objectID'] ?? 'sin ID'));
                    
                    // Crear el despacho en WordPress
                    $post_data = array(
                        'post_title' => $record['nombre'] ?? 'Despacho sin nombre',
                        'post_content' => $record['descripcion'] ?? '',
                        'post_status' => 'publish',
                        'post_type' => 'despacho'
                    );

                    migration_log("Intentando crear post con título: " . $post_data['post_title']);
                    $post_id = wp_insert_post($post_data);

                    if ($post_id && !is_wp_error($post_id)) {
                        migration_log("Post creado exitosamente con ID: {$post_id}");
                        
                        // Guardar metadatos
                        $meta_fields = [
                            'localidad' => $record['localidad'] ?? '',
                            'provincia' => $record['provincia'] ?? '',
                            'codigo_postal' => $record['codigo_postal'] ?? '',
                            'direccion' => $record['direccion'] ?? '',
                            'telefono' => $record['telefono'] ?? '',
                            'email' => $record['email'] ?? '',
                            'web' => $record['web'] ?? '',
                            'estado_verificacion' => $record['estado_verificacion'] ?? '',
                            'isVerified' => $record['isVerified'] ?? '',
                            'experiencia' => $record['experiencia'] ?? '',
                            'tamaño_despacho' => $record['tamaño_despacho'] ?? '',
                            'año_fundacion' => $record['año_fundacion'] ?? '',
                            'estado_registro' => $record['estado_registro'] ?? '',
                            'ultima_actualizacion' => $record['ultima_actualizacion'] ?? '',
                            'algolia_object_id' => $record['objectID'] ?? '',
                            'algolia_slug' => $record['slug'] ?? ''
                        ];

                        foreach ($meta_fields as $key => $value) {
                            update_post_meta($post_id, $key, $value);
                        }

                        // Guardar arrays como JSON
                        if (!empty($record['areas_practica'])) {
                            update_post_meta($post_id, 'areas_practica', json_encode($record['areas_practica']));
                        }
                        if (!empty($record['especialidades'])) {
                            update_post_meta($post_id, 'especialidades', json_encode($record['especialidades']));
                        }
                        if (!empty($record['horario'])) {
                            update_post_meta($post_id, 'horario', json_encode($record['horario']));
                        }
                        if (!empty($record['redes_sociales'])) {
                            update_post_meta($post_id, 'redes_sociales', json_encode($record['redes_sociales']));
                        }

                        $migrated_count++;
                        migration_log("Registro {$record_index} migrado exitosamente (ID: {$post_id})");
                        echo "<span style='color: green;'>✅ Registro {$record_index}: Creado (ID: {$post_id})</span><br>\n";
                    } else {
                        $error_msg = is_wp_error($post_id) ? $post_id->get_error_message() : 'Error desconocido';
                        $errors[] = "Registro {$record_index}: {$error_msg}";
                        migration_log("ERROR en registro {$record_index}: {$error_msg}");
                        echo "<span style='color: red;'>❌ Registro {$record_index}: Error - {$error_msg}</span><br>\n";
                    }

                } catch (Exception $e) {
                    $errors[] = "Registro {$record_index}: " . $e->getMessage();
                    migration_log("EXCEPTION en registro {$record_index}: " . $e->getMessage());
                    echo "<span style='color: red;'>❌ Registro {$record_index}: Error - " . $e->getMessage() . "</span><br>\n";
                }

                // Mostrar progreso cada 10 registros
                if (($index + 1) % 10 === 0) {
                    echo "<strong>Progreso: " . ($index + 1) . "/{$batch_size} en este lote</strong><br>\n";
                }
            }

            migration_log("Lote " . ($batch + 1) . " completado. Total migrados: {$migrated_count}");
            echo "<p><strong>✅ Lote " . ($batch + 1) . " completado. Total migrados: {$migrated_count}</strong></p>\n";

            // Pausa entre lotes (excepto el último)
            if ($batch < $total_batches - 1) {
                echo "<p>⏱️ Pausa de 2 segundos...</p>\n";
                sleep(2);
            }
        }

        // Resumen final
        migration_log("=== MIGRACIÓN COMPLETADA ===");
        migration_log("Total migrados: {$migrated_count}");
        migration_log("Total errores: " . count($errors));
        
        echo "<h3>🎉 Migración Completada</h3>\n";
        echo "<div style='background: #f0f8ff; padding: 20px; border-left: 4px solid #0073aa;'>\n";
        echo "<h4>📊 Resumen:</h4>\n";
        echo "<ul>\n";
        echo "<li>✅ <strong>Registros migrados exitosamente:</strong> {$migrated_count}</li>\n";
        echo "<li>❌ <strong>Errores:</strong> " . count($errors) . "</li>\n";
        echo "<li>📈 <strong>Total procesados:</strong> {$total_records}</li>\n";
        echo "</ul>\n";

        if (!empty($errors)) {
            echo "<h4>❌ Errores encontrados:</h4>\n";
            echo "<div style='max-height: 200px; overflow-y: scroll; background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7;'>\n";
            foreach ($errors as $error) {
                echo "<p style='margin: 5px 0; color: #856404;'>{$error}</p>\n";
            }
            echo "</div>\n";
        }
        echo "</div>\n";

    } catch (Exception $e) {
        migration_log("ERROR FATAL: " . $e->getMessage());
        echo "<h3>❌ Error durante la migración:</h3>\n";
        echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
        echo "<p><strong>Stack trace:</strong></p>\n";
        echo "<pre style='background: #f8f9fa; padding: 10px; overflow-x: auto;'>" . $e->getTraceAsString() . "</pre>\n";
    }

    echo "<hr>\n";
    echo "<p><a href='javascript:history.back()'>← Volver</a></p>\n";
}
?> 
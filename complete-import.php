<?php
/**
 * Script optimizado para completar la importación de despachos
 * Maneja timeouts y errores de conexión
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

// Configuración
$block_size = 500; // Bloques más pequeños para evitar timeouts
$timeout = 60; // Timeout más largo

echo "<h1>🚀 Importación Completa Optimizada</h1>";
echo "<p><strong>Configuración:</strong> Bloques de {$block_size} registros, timeout de {$timeout}s</p>";

// Verificar configuración
$app_id = get_option('lexhoy_despachos_algolia_app_id');
$admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
$index_name = get_option('lexhoy_despachos_algolia_index_name');

if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
    die('<p style="color: red;"><strong>❌ Configuración de Algolia incompleta</strong></p>');
}

try {
    require_once('includes/class-lexhoy-algolia-client.php');
    $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, '', $index_name);
    
    // Obtener todos los registros de Algolia
    echo "<h2>1. Obteniendo registros de Algolia...</h2>";
    $result = $client->browse_all_unfiltered();
    
    if (!$result['success']) {
        throw new Exception('Error al obtener registros: ' . $result['message']);
    }
    
    $all_records = $result['hits'];
    $total_records = count($all_records);
    
    echo "<p><strong>Total de registros en Algolia:</strong> " . number_format($total_records) . "</p>";
    
    // Contar despachos existentes
    $existing_despachos = get_posts(array(
        'post_type' => 'despacho',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids'
    ));
    $existing_count = count($existing_despachos);
    
    echo "<p><strong>Despachos existentes en WordPress:</strong> " . number_format($existing_count) . "</p>";
    
    // Calcular bloques necesarios
    $total_blocks = ceil($total_records / $block_size);
    $processed_records = 0;
    $created_records = 0;
    $updated_records = 0;
    $skipped_records = 0;
    $error_records = 0;
    
    echo "<h2>2. Iniciando importación por bloques...</h2>";
    echo "<p><strong>Total de bloques:</strong> {$total_blocks}</p>";
    
    // Procesar por bloques
    for ($block = 0; $block < $total_blocks; $block++) {
        $start_index = $block * $block_size;
        $end_index = min($start_index + $block_size, $total_records);
        $block_records = array_slice($all_records, $start_index, $block_size);
        
        echo "<h3>📦 Procesando Bloque " . ($block + 1) . " de {$total_blocks}</h3>";
        echo "<p>Registros " . ($start_index + 1) . "-{$end_index} de {$total_records}</p>";
        
        $block_created = 0;
        $block_updated = 0;
        $block_skipped = 0;
        $block_errors = 0;
        
        foreach ($block_records as $index => $record) {
            $record_number = $start_index + $index + 1;
            $object_id = $record['objectID'] ?? '';
            $nombre = trim($record['nombre'] ?? '');
            
            try {
                // Verificar si ya existe
                $existing_posts = get_posts(array(
                    'post_type' => 'despacho',
                    'meta_query' => array(
                        array(
                            'key' => '_algolia_object_id',
                            'value' => $object_id,
                            'compare' => '='
                        )
                    ),
                    'post_status' => 'any',
                    'numberposts' => 1
                ));
                
                if (!empty($existing_posts)) {
                    $block_skipped++;
                    $skipped_records++;
                } else {
                    // Crear nuevo despacho
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
                            '_despacho_nombre' => $nombre,
                            '_despacho_localidad' => trim($record['localidad'] ?? ''),
                            '_despacho_provincia' => trim($record['provincia'] ?? ''),
                            '_despacho_codigo_postal' => trim($record['codigo_postal'] ?? ''),
                            '_despacho_direccion' => trim($record['direccion'] ?? ''),
                            '_despacho_telefono' => trim($record['telefono'] ?? ''),
                            '_despacho_email' => trim($record['email'] ?? ''),
                            '_despacho_web' => trim($record['web'] ?? ''),
                            '_despacho_descripcion' => trim($record['descripcion'] ?? ''),
                            '_despacho_estado_verificacion' => trim($record['estado_verificacion'] ?? 'pendiente'),
                            '_despacho_is_verified' => trim($record['isVerified'] ?? '0'),
                            '_despacho_experiencia' => trim($record['experiencia'] ?? ''),
                            '_despacho_tamaño' => trim($record['tamaño_despacho'] ?? ''),
                            '_despacho_año_fundacion' => trim($record['año_fundacion'] ?? ''),
                            '_despacho_estado_registro' => trim($record['estado_registro'] ?? 'activo'),
                            '_algolia_object_id' => $object_id
                        );
                        
                        foreach ($meta_fields as $key => $value) {
                            update_post_meta($post_id, $key, $value);
                        }
                        
                        // Guardar arrays como JSON
                        if (!empty($record['areas_practica'])) {
                            update_post_meta($post_id, '_despacho_areas_practica', json_encode($record['areas_practica']));
                        }
                        if (!empty($record['especialidades'])) {
                            update_post_meta($post_id, '_despacho_especialidades', json_encode($record['especialidades']));
                        }
                        if (!empty($record['horario'])) {
                            update_post_meta($post_id, '_despacho_horario', json_encode($record['horario']));
                        }
                        if (!empty($record['redes_sociales'])) {
                            update_post_meta($post_id, '_despacho_redes_sociales', json_encode($record['redes_sociales']));
                        }
                        
                        $block_created++;
                        $created_records++;
                    } else {
                        $block_errors++;
                        $error_records++;
                    }
                }
                
                $processed_records++;
                
            } catch (Exception $e) {
                $block_errors++;
                $error_records++;
            }
        }
        
        echo "<p><strong>Resumen del bloque " . ($block + 1) . ":</strong></p>";
        echo "<ul>";
        echo "<li>✅ Creados: {$block_created}</li>";
        echo "<li>⚠️ Saltados: {$block_skipped}</li>";
        echo "<li>❌ Errores: {$block_errors}</li>";
        echo "</ul>";
        
        // Pausa entre bloques para evitar sobrecarga
        if ($block < $total_blocks - 1) {
            echo "<p>⏱️ Pausa de 2 segundos...</p>";
            sleep(2);
        }
        
        // Flush output para mostrar progreso en tiempo real
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
    
    // Resumen final
    echo "<h2>3. Resumen Final</h2>";
    echo "<div style='background: #f0f8ff; padding: 20px; border-left: 4px solid #0073aa;'>";
    echo "<h3>📊 Estadísticas Totales:</h3>";
    echo "<ul>";
    echo "<li>✅ <strong>Despachos creados:</strong> " . number_format($created_records) . "</li>";
    echo "<li>⚠️ <strong>Despachos omitidos (ya existían):</strong> " . number_format($skipped_records) . "</li>";
    echo "<li>❌ <strong>Errores:</strong> " . number_format($error_records) . "</li>";
    echo "<li>📦 <strong>Bloques procesados:</strong> {$total_blocks}</li>";
    echo "<li>📈 <strong>Total procesados:</strong> " . number_format($processed_records) . "</li>";
    echo "</ul>";
    echo "</div>";
    
    // Verificar estado final
    $final_wp_count = get_posts(array(
        'post_type' => 'despacho',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids'
    ));
    $final_count = count($final_wp_count);
    
    echo "<p><strong>Estado final de WordPress:</strong> " . number_format($final_count) . " despachos</p>";
    
    if ($created_records > 0) {
        echo "<p style='color: green; font-size: 18px;'>🎉 ¡Importación completada exitosamente!</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Error durante la importación</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='check-import-status.php'>📊 Verificar estado completo</a></p>";
echo "<p><a href='javascript:location.reload()'>🔄 Actualizar</a></p>";
?> 
<?php
/**
 * Script Robusto de Importación desde Algolia
 * Maneja timeouts, errores de conexión y usa bloques pequeños
 * Ejecutar desde: http://lexhoy.local/wp-content/plugins/LexHoy-Despachos/import-robust-algolia.php
 */

// Cargar WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    require_once('../../../wp-load.php');
}

// Verificar permisos
if (!current_user_can('manage_options')) {
    wp_die('❌ Acceso denegado. Necesitas permisos de administrador.');
}

// Aumentar límites y configurar output buffering
set_time_limit(0);
ini_set('memory_limit', '512M');
ini_set('output_buffering', 0);
ini_set('implicit_flush', 1);
ob_end_clean();
ob_implicit_flush(1);

// Función para forzar salida inmediata
function force_output($text) {
    echo $text;
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

force_output("<h1>🚀 Importación Robusta desde Algolia</h1>\n");
force_output("<p><strong>Configuración optimizada:</strong> Bloques pequeños, manejo de errores, reintentos automáticos</p>\n");

// Obtener credenciales
$app_id = get_option('lexhoy_despachos_algolia_app_id');
$admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
$index_name = get_option('lexhoy_despachos_algolia_index_name');

if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
    wp_die('❌ Error: Las credenciales de Algolia no están configuradas.');
}

// Configuración del script
$block_size = 250;  // Bloques más pequeños para evitar timeouts
$max_retries = 3;   // Reintentos por bloque
$delay_between_blocks = 2; // Pausa entre bloques (segundos)

if (isset($_POST['action']) && $_POST['action'] === 'test_connection') {
    
    echo "<h2>🔍 Probando conexión con Algolia...</h2>\n";
    flush();
    
    try {
        $url = "https://{$app_id}-dsn.algolia.net/1/indexes/{$index_name}/query";
        $headers = [
            'X-Algolia-API-Key: ' . $admin_api_key,
            'X-Algolia-Application-Id: ' . $app_id,
            'Content-Type: application/json'
        ];
        
        $post_data = ['query' => '', 'hitsPerPage' => 1, 'page' => 0];
        
        echo "<p>🌐 <strong>URL:</strong> {$url}</p>\n";
        echo "<p>📤 <strong>Enviando petición...</strong></p>\n";
        flush();
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $start_time = microtime(true);
        $response = curl_exec($ch);
        $end_time = microtime(true);
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        $elapsed_time = round(($end_time - $start_time) * 1000, 2);
        
        echo "<p>⏱️ <strong>Tiempo de respuesta:</strong> {$elapsed_time}ms</p>\n";
        
        if ($curl_error) {
            echo "<h2 style='color: red;'>❌ Error de conexión:</h2>\n";
            echo "<p style='color: red;'>{$curl_error}</p>\n";
        } elseif ($http_code !== 200) {
            echo "<h2 style='color: red;'>❌ Error HTTP {$http_code}:</h2>\n";
            echo "<p style='color: red;'>" . substr($response, 0, 500) . "</p>\n";
        } else {
            $data = json_decode($response, true);
            
            if (isset($data['nbHits'])) {
                echo "<h2 style='color: green;'>✅ ¡Conexión exitosa!</h2>\n";
                echo "<p><strong>Total registros:</strong> " . number_format($data['nbHits']) . "</p>\n";
                echo "<p><strong>Tiempo de procesamiento:</strong> " . ($data['processingTimeMS'] ?? 'N/A') . "ms</p>\n";
                
                if (isset($data['hits']) && count($data['hits']) > 0) {
                    $first_hit = $data['hits'][0];
                    echo "<p><strong>Ejemplo ObjectID:</strong> " . ($first_hit['objectID'] ?? 'N/A') . "</p>\n";
                    echo "<p><strong>Ejemplo nombre:</strong> " . ($first_hit['nombre'] ?? 'N/A') . "</p>\n";
                    echo "<p><strong>Es verificado:</strong> " . (isset($first_hit['isVerified']) ? ($first_hit['isVerified'] ? 'SÍ' : 'NO') : 'N/A') . "</p>\n";
                }
                
                echo "<div style='background: #e8f5e8; border-left: 4px solid #4caf50; padding: 15px; margin: 20px 0;'>\n";
                echo "<h3>🎉 La conexión funciona - Puedes proceder con la importación</h3>\n";
                echo "</div>\n";
            }
        }
        
    } catch (Exception $e) {
        echo "<h2 style='color: red;'>❌ Excepción:</h2>\n";
        echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
    }
    
} elseif (isset($_POST['action']) && $_POST['action'] === 'start_import') {
    
    force_output("<h2>🚀 Iniciando importación robusta...</h2>\n");
    force_output("<p>📦 <strong>Tamaño de bloque:</strong> {$block_size} registros</p>\n");
    force_output("<p>🔄 <strong>Reintentos por bloque:</strong> {$max_retries}</p>\n");
    force_output("<p>⏱️ <strong>Pausa entre bloques:</strong> {$delay_between_blocks}s</p>\n");
    
    // Función para obtener total de registros
    function get_total_records($app_id, $admin_api_key, $index_name) {
        force_output("<p>🌐 URL: https://{$app_id}-dsn.algolia.net/1/indexes/{$index_name}/query</p>\n");
        
        $url = "https://{$app_id}-dsn.algolia.net/1/indexes/{$index_name}/query";
        $headers = [
            'X-Algolia-API-Key: ' . $admin_api_key,
            'X-Algolia-Application-Id: ' . $app_id,
            'Content-Type: application/json'
        ];
        
        $post_data = ['query' => '', 'hitsPerPage' => 1, 'page' => 0];
        
        force_output("<p>📤 Enviando petición cURL...</p>\n");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        echo "<p>⏱️ Ejecutando cURL...</p>\n";
        flush();
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        echo "<p>📋 Respuesta HTTP: {$http_code}</p>\n";
        if ($curl_error) {
            echo "<p>❌ Error cURL: {$curl_error}</p>\n";
        }
        flush();
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            $total = isset($data['nbHits']) ? $data['nbHits'] : 0;
            echo "<p>✅ Total encontrado: {$total}</p>\n";
            flush();
            return $total;
        }
        
        throw new Exception("Error obteniendo total: HTTP {$http_code} - {$curl_error}");
    }
    
    // Función para obtener un bloque de registros
    function get_algolia_block($app_id, $admin_api_key, $index_name, $page, $hits_per_page) {
        $url = "https://{$app_id}-dsn.algolia.net/1/indexes/{$index_name}/query";
        $headers = [
            'X-Algolia-API-Key: ' . $admin_api_key,
            'X-Algolia-Application-Id: ' . $app_id,
            'Content-Type: application/json'
        ];
        
        $post_data = [
            'query' => '',
            'hitsPerPage' => $hits_per_page,
            'page' => $page
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("Error de conexión: {$curl_error}");
        }
        
        if ($http_code !== 200) {
            throw new Exception("Error HTTP {$http_code}: {$response}");
        }
        
        $data = json_decode($response, true);
        return isset($data['hits']) ? $data['hits'] : [];
    }
    
    // Función para crear/actualizar despacho en WordPress
    function create_or_update_despacho($record, $overwrite = true) {
        // Buscar si existe por objectID
        $existing_posts = get_posts([
            'post_type' => 'despacho',
            'meta_key' => '_despacho_algolia_object_id',
            'meta_value' => $record['objectID'],
            'post_status' => 'any',
            'numberposts' => 1
        ]);
        
        $post_data = [
            'post_type' => 'despacho',
            'post_status' => 'publish',
            'post_title' => isset($record['nombre']) ? sanitize_text_field($record['nombre']) : 'Despacho ' . $record['objectID'],
            'post_content' => '',
            'meta_input' => [
                '_despacho_algolia_object_id' => $record['objectID'],
                '_despacho_is_verified' => '0', // NO verificado por defecto
            ]
        ];
        
        // Mapear campos de Algolia a meta fields
        $field_mapping = [
            'nombre' => '_despacho_nombre',
            'localidad' => '_despacho_localidad', 
            'provincia' => '_despacho_provincia',
            'direccion' => '_despacho_direccion',
            'telefono' => '_despacho_telefono',
            'email' => '_despacho_email',
            'web' => '_despacho_web',
            'areas_practica' => '_despacho_areas_practica'
        ];
        
        foreach ($field_mapping as $algolia_field => $meta_key) {
            if (isset($record[$algolia_field])) {
                $post_data['meta_input'][$meta_key] = sanitize_text_field($record[$algolia_field]);
            }
        }
        
        if (!empty($existing_posts) && $overwrite) {
            // Actualizar existente
            $post_data['ID'] = $existing_posts[0]->ID;
            $result = wp_update_post($post_data);
            return $result ? 'updated' : 'error';
        } elseif (empty($existing_posts)) {
            // Crear nuevo
            $result = wp_insert_post($post_data);
            return $result ? 'created' : 'error';
        } else {
            // Existe pero no sobrescribir
            return 'skipped';
        }
    }
    
    try {
        // Obtener total
        echo "<p>📊 Obteniendo total de registros...</p>\n";
        flush();
        
        echo "<p>🔄 Llamando a get_total_records()...</p>\n";
        flush();
        
        $total_records = get_total_records($app_id, $admin_api_key, $index_name);
        
        echo "<p>✅ get_total_records() completado</p>\n";
        flush();
        $total_blocks = ceil($total_records / $block_size);
        
        echo "<p>✅ <strong>Total registros en Algolia:</strong> " . number_format($total_records) . "</p>\n";
        echo "<p>📦 <strong>Total bloques a procesar:</strong> {$total_blocks}</p>\n";
        flush();
        
        // Contadores
        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        
        // Procesar bloque por bloque
        for ($block = 0; $block < $total_blocks; $block++) {
            $block_number = $block + 1;
            $start_record = ($block * $block_size) + 1;
            $end_record = min(($block + 1) * $block_size, $total_records);
            
            echo "<h3>🔄 Procesando bloque {$block_number}/{$total_blocks} (registros {$start_record}-{$end_record})</h3>\n";
            flush();
            
            $retry_count = 0;
            $block_success = false;
            
            // Reintentos para este bloque
            while ($retry_count < $max_retries && !$block_success) {
                try {
                    if ($retry_count > 0) {
                        echo "<p>🔄 Reintento {$retry_count}/{$max_retries} para bloque {$block_number}</p>\n";
                        flush();
                        sleep(2); // Pausa antes de reintentar
                    }
                    
                    $records = get_algolia_block($app_id, $admin_api_key, $index_name, $block, $block_size);
                    
                    if (empty($records)) {
                        echo "<p>⚠️ Bloque {$block_number} vacío, continuando...</p>\n";
                        $block_success = true;
                        break;
                    }
                    
                    // Procesar registros del bloque
                    foreach ($records as $record) {
                        if (!isset($record['objectID'])) continue;
                        
                        $result = create_or_update_despacho($record, true);
                        
                        switch ($result) {
                            case 'created': $created++; break;
                            case 'updated': $updated++; break;
                            case 'skipped': $skipped++; break;
                            case 'error': $errors++; break;
                        }
                        
                        $processed++;
                    }
                    
                    echo "<p>✅ Bloque {$block_number} completado: " . count($records) . " registros procesados</p>\n";
                    echo "<p>📊 <strong>Progreso total:</strong> {$processed}/{$total_records} ({$created} creados, {$updated} actualizados)</p>\n";
                    flush();
                    
                    $block_success = true;
                    
                } catch (Exception $e) {
                    $retry_count++;
                    echo "<p>❌ Error en bloque {$block_number} (intento {$retry_count}): " . $e->getMessage() . "</p>\n";
                    flush();
                    
                    if ($retry_count >= $max_retries) {
                        echo "<p>💀 Bloque {$block_number} falló después de {$max_retries} intentos. Continuando con el siguiente...</p>\n";
                        flush();
                    }
                }
            }
            
            // Pausa entre bloques para no sobrecargar
            if ($block < $total_blocks - 1) {
                echo "<p>⏱️ Pausa de {$delay_between_blocks}s antes del siguiente bloque...</p>\n";
                flush();
                sleep($delay_between_blocks);
            }
        }
        
        echo "<h2>🎉 ¡IMPORTACIÓN COMPLETADA!</h2>\n";
        echo "<div style='background: #e8f5e8; border-left: 4px solid #4caf50; padding: 15px; margin: 20px 0;'>\n";
        echo "<h3>📊 Estadísticas Finales:</h3>\n";
        echo "<ul>\n";
        echo "<li><strong>Total procesados:</strong> " . number_format($processed) . "</li>\n";
        echo "<li><strong>Nuevos creados:</strong> " . number_format($created) . "</li>\n";
        echo "<li><strong>Actualizados:</strong> " . number_format($updated) . "</li>\n";
        echo "<li><strong>Omitidos:</strong> " . number_format($skipped) . "</li>\n";
        echo "<li><strong>Errores:</strong> " . number_format($errors) . "</li>\n";
        echo "</ul>\n";
        echo "</div>\n";
        
        // Verificar resultado final
        $wp_count = wp_count_posts('despacho');
        $wp_total = $wp_count->publish;
        
        echo "<h3>✅ Verificación Final:</h3>\n";
        echo "<p><strong>Despachos en WordPress:</strong> " . number_format($wp_total) . "</p>\n";
        echo "<p><strong>Registros en Algolia:</strong> " . number_format($total_records) . "</p>\n";
        
        if ($wp_total >= $total_records * 0.95) { // 95% o más
            echo "<p style='color: green;'><strong>✅ ÉXITO:</strong> Sincronización completada correctamente</p>\n";
        } else {
            echo "<p style='color: orange;'><strong>⚠️ ATENCIÓN:</strong> Puede que falten algunos registros por problemas de conexión</p>\n";
        }
        
    } catch (Exception $e) {
        echo "<h2>❌ Error crítico:</h2>\n";
        echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
    }
    
} else {
    
    // Mostrar formulario
    echo "<div style='background: #f0f0f1; padding: 20px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h3>📋 Características de este script:</h3>\n";
    echo "<ul>\n";
    echo "<li>🔧 <strong>Bloques pequeños:</strong> {$block_size} registros (más estable)</li>\n";
    echo "<li>🔄 <strong>Reintentos automáticos:</strong> {$max_retries} intentos por bloque</li>\n";
    echo "<li>⏱️ <strong>Pausas inteligentes:</strong> {$delay_between_blocks}s entre bloques</li>\n";
    echo "<li>📊 <strong>Progreso detallado:</strong> Estadísticas en tiempo real</li>\n";
    echo "<li>✅ <strong>Sobrescribe datos:</strong> Actualiza registros existentes</li>\n";
    echo "<li>🛡️ <strong>Manejo de errores:</strong> Continúa aunque falle un bloque</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
    // Mostrar estadísticas actuales
    $wp_count = wp_count_posts('despacho');
    $wp_total = $wp_count->publish;
    
    echo "<div style='background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 20px 0;'>\n";
    echo "<h3>📊 Estado Actual:</h3>\n";
    echo "<p><strong>Despachos en WordPress:</strong> " . number_format($wp_total) . "</p>\n";
    echo "<p><strong>Objetivo:</strong> ~14,187 registros de Algolia</p>\n";
    echo "</div>\n";
    
    echo "<div style='display: flex; gap: 10px; margin: 20px 0;'>\n";
    echo "<form method='post' style='display: inline;'>\n";
    echo "<input type='hidden' name='action' value='test_connection'>\n";
    echo "<button type='submit' style='background: #f39c12; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;'>🔍 Probar Conexión</button>\n";
    echo "</form>\n";
    
    echo "<form method='post' style='display: inline;'>\n";
    echo "<input type='hidden' name='action' value='start_import'>\n";
    echo "<button type='submit' style='background: #0073aa; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;'>🚀 Iniciar Importación Robusta</button>\n";
    echo "</form>\n";
    echo "</div>\n";
    
    echo "<div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;'>\n";
    echo "<h3>⚠️ Importante:</h3>\n";
    echo "<ul>\n";
    echo "<li>Este proceso puede tomar <strong>30-60 minutos</strong></li>\n";
    echo "<li><strong>No cierres</strong> esta ventana durante el proceso</li>\n";
    echo "<li>El progreso se muestra en <strong>tiempo real</strong></li>\n";
    echo "<li>Si falla un bloque, <strong>continúa automáticamente</strong></li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
} 
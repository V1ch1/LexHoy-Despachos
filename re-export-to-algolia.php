<?php
/**
 * Script para re-exportar despachos válidos de WordPress a Algolia
 * Solo exporta despachos que tienen datos reales
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>Re-exportar desde WordPress a Algolia</h1>\n";
echo "<p><strong>IMPORTANTE:</strong> Este script exportará solo los despachos válidos de WordPress a Algolia.</p>\n";

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

    // Obtener despachos válidos de WordPress
    echo "<h2>1. Obteniendo despachos válidos de WordPress...</h2>\n";
    
    $wp_despachos = get_posts(array(
        'post_type' => 'despacho',
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'all'
    ));
    
    $valid_despachos = [];
    $empty_despachos = [];
    
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
        
        // Verificar si el despacho tiene datos válidos
        $has_data = !empty($nombre) || !empty($localidad) || !empty($provincia) || 
                   !empty($direccion) || !empty($telefono) || !empty($email) || 
                   !empty($web) || !empty($descripcion);
        
        if ($has_data) {
            $valid_despachos[] = array(
                'post' => $post,
                'meta' => array(
                    'nombre' => $nombre,
                    'localidad' => $localidad,
                    'provincia' => $provincia,
                    'direccion' => $direccion,
                    'telefono' => $telefono,
                    'email' => $email,
                    'web' => $web,
                    'descripcion' => $descripcion
                )
            );
        } else {
            $empty_despachos[] = $post;
        }
    }
    
    echo "<p>Total de despachos en WordPress: <strong>" . count($wp_despachos) . "</strong></p>\n";
    echo "<p>Despachos válidos (con datos): <strong>" . count($valid_despachos) . "</strong></p>\n";
    echo "<p>Despachos vacíos: <strong>" . count($empty_despachos) . "</strong></p>\n";

    if (empty($valid_despachos)) {
        echo "<p style='color: red;'>❌ No hay despachos válidos para exportar.</p>\n";
        return;
    }

    // Mostrar algunos ejemplos
    echo "<h3>Ejemplos de despachos que se exportarán:</h3>\n";
    echo "<ul>\n";
    for ($i = 0; $i < min(5, count($valid_despachos)); $i++) {
        $despacho = $valid_despachos[$i];
        $post = $despacho['post'];
        $meta = $despacho['meta'];
        echo "<li><strong>{$meta['nombre']}</strong> - {$meta['localidad']}, {$meta['provincia']}</li>\n";
    }
    echo "</ul>\n";

    // Preguntar si proceder
    if (isset($_POST['confirm_export']) && $_POST['confirm_export'] === 'yes') {
        echo "<h2>2. Limpiando índice de Algolia...</h2>\n";
        
        // Primero, limpiar el índice actual
        $clear_url = "https://{$app_id}.algolia.net/1/indexes/{$index_name}/clear";
        $headers = [
            'X-Algolia-API-Key: ' . $admin_api_key,
            'X-Algolia-Application-Id: ' . $app_id
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $clear_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            echo "<p style='color: green;'>✅ Índice de Algolia limpiado correctamente</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠️ No se pudo limpiar el índice (HTTP {$http_code}), continuando...</p>\n";
        }

        echo "<h2>3. Exportando despachos a Algolia...</h2>\n";
        
        $exported = 0;
        $errors = 0;
        $batch_size = 100;
        $batches = array_chunk($valid_despachos, $batch_size);
        
        foreach ($batches as $batch_num => $batch) {
            echo "<h3>Procesando lote " . ($batch_num + 1) . " de " . count($batches) . "</h3>\n";
            
            $algolia_records = [];
            
            foreach ($batch as $despacho) {
                $post = $despacho['post'];
                $meta = $despacho['meta'];
                
                // Crear registro para Algolia
                $record = array(
                    'objectID' => $post->ID,
                    'slug' => $post->post_name,
                    'nombre' => $meta['nombre'],
                    'localidad' => $meta['localidad'],
                    'provincia' => $meta['provincia'],
                    'direccion' => $meta['direccion'],
                    'telefono' => $meta['telefono'],
                    'email' => $meta['email'],
                    'web' => $meta['web'],
                    'descripcion' => $meta['descripcion'],
                    'post_title' => $post->post_title,
                    'post_content' => $post->post_content,
                    'post_date' => $post->post_date,
                    'post_modified' => $post->post_modified
                );
                
                $algolia_records[] = $record;
            }
            
            // Enviar lote a Algolia
            $batch_url = "https://{$app_id}.algolia.net/1/indexes/{$index_name}/batch";
            $batch_data = array('requests' => array());
            
            foreach ($algolia_records as $record) {
                $batch_data['requests'][] = array(
                    'action' => 'addObject',
                    'body' => $record
                );
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $batch_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($batch_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200) {
                $exported += count($algolia_records);
                echo "<p style='color: green;'>✅ Lote " . ($batch_num + 1) . " exportado: " . count($algolia_records) . " registros</p>\n";
            } else {
                $errors += count($algolia_records);
                echo "<p style='color: red;'>❌ Error en lote " . ($batch_num + 1) . " (HTTP {$http_code}): " . $response . "</p>\n";
            }
            
            // Pausa entre lotes
            if ($batch_num < count($batches) - 1) {
                usleep(500000); // 500ms
            }
        }
        
        echo "<h2>4. Resumen de exportación</h2>\n";
        echo "<ul>\n";
        echo "<li>Despachos exportados exitosamente: <strong>{$exported}</strong></li>\n";
        echo "<li>Errores: <strong>{$errors}</strong></li>\n";
        echo "</ul>\n";
        
        if ($exported > 0) {
            echo "<p style='color: green;'>✅ Exportación completada. Algolia ahora tiene {$exported} registros válidos.</p>\n";
        } else {
            echo "<p style='color: red;'>❌ No se pudo exportar ningún registro.</p>\n";
        }
        
    } else {
        echo "<h2>¿Proceder con la exportación?</h2>\n";
        echo "<p><strong>ATENCIÓN:</strong> Esta acción limpiará el índice de Algolia y exportará solo los despachos válidos de WordPress.</p>\n";
        echo "<form method='post'>\n";
        echo "<input type='hidden' name='confirm_export' value='yes'>\n";
        echo "<button type='submit' style='background: blue; color: white; padding: 10px 20px; border: none; cursor: pointer;'>";
        echo "SÍ, EXPORTAR A ALGOLIA";
        echo "</button>\n";
        echo "</form>\n";
    }

} catch (Exception $e) {
    echo "<h2>Error:</h2>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='javascript:history.back()'>← Volver</a></p>\n";
?> 
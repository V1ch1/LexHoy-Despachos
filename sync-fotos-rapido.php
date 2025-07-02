<?php
/**
 * Sincronización RÁPIDA de Fotos: Algolia → WordPress
 * Solo actualiza el campo foto_perfil sin tocar otros datos
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Solo para admins
if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos para ejecutar este script.');
}

// Auto-detectar entorno
$is_production = (strpos($_SERVER['HTTP_HOST'], 'lexhoy.com') !== false);
$base_url = $is_production ? 'https://lexhoy.com' : 'http://lexhoy.local';
$foto_url = $base_url . '/wp-content/uploads/2025/07/FOTO-DESPACHO-500X500.webp';

echo "<h1>⚡ Sincronización RÁPIDA de Fotos</h1>";
echo "<p><strong>Entorno:</strong> " . ($is_production ? 'PRODUCCIÓN' : 'DESARROLLO') . "</p>";
echo "<p><strong>Iniciado:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Obtener configuración de Algolia
$app_id = get_option('lexhoy_despachos_algolia_app_id');
$admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
$index_name = get_option('lexhoy_despachos_algolia_index_name');

if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
    echo "<p style='color: red;'>❌ Error: Configuración de Algolia incompleta</p>";
    exit;
}

// Cargar cliente Algolia
require_once('includes/class-lexhoy-algolia-client.php');
$algolia_client = new LexhoyAlgoliaClient($app_id, $admin_api_key, '', $index_name);

try {
    echo "<h2>📊 Obteniendo datos...</h2>";
    
    // Obtener todos los despachos de WordPress
    $wp_despachos = get_posts(array(
        'post_type' => 'despacho',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));
    
    echo "<p>✅ Despachos en WordPress: <strong>" . count($wp_despachos) . "</strong></p>";
    
    // Obtener registros de Algolia con fotos
    echo "<p>🔍 Buscando registros con fotos en Algolia...</p>";
    
    $result = $algolia_client->browse_all_unfiltered();
    if (!$result['success']) {
        throw new Exception('Error al obtener registros de Algolia: ' . $result['message']);
    }
    
    $algolia_records = $result['hits'];
    echo "<p>✅ Registros en Algolia: <strong>" . count($algolia_records) . "</strong></p>";
    
    // Filtrar solo registros que tienen foto_perfil
    $records_with_photo = [];
    foreach ($algolia_records as $record) {
        if (!empty($record['foto_perfil'])) {
            $records_with_photo[] = $record;
        }
    }
    
    echo "<p>📷 Registros con foto en Algolia: <strong>" . count($records_with_photo) . "</strong></p>";
    
    if (empty($records_with_photo)) {
        echo "<p style='color: orange;'>⚠️ No hay registros con fotos en Algolia. Asignando foto predeterminada a todos...</p>";
        
        // Asignar foto predeterminada a todos los despachos
        $updated = 0;
        foreach ($wp_despachos as $post_id) {
            $current_foto = get_post_meta($post_id, '_despacho_foto_perfil', true);
            if (empty($current_foto)) {
                update_post_meta($post_id, '_despacho_foto_perfil', $foto_url);
                $updated++;
            }
        }
        
        echo "<p>✅ Asignada foto predeterminada a <strong>{$updated}</strong> despachos</p>";
    } else {
        echo "<h2>⚡ Sincronización RÁPIDA iniciada...</h2>";
        
        $total_updated = 0;
        $total_assigned_default = 0;
        $batch_size = 50;
        $processed = 0;
        
        // Crear mapa de objectID → foto_perfil para búsqueda rápida
        $algolia_photos = [];
        foreach ($records_with_photo as $record) {
            if (!empty($record['objectID']) && !empty($record['foto_perfil'])) {
                $algolia_photos[$record['objectID']] = $record['foto_perfil'];
            }
        }
        
        echo "<p>📋 Mapa de fotos creado: <strong>" . count($algolia_photos) . "</strong> entradas</p>";
        
        // Procesar despachos en lotes
        $total_wp = count($wp_despachos);
        $batches = array_chunk($wp_despachos, $batch_size);
        
        foreach ($batches as $batch_num => $batch) {
            echo "<div style='border-left: 3px solid #0073aa; padding-left: 10px; margin: 10px 0;'>";
            echo "<strong>📦 Lote " . ($batch_num + 1) . "/" . count($batches) . "</strong> (" . count($batch) . " despachos)<br>";
            
            foreach ($batch as $post_id) {
                // Buscar algolia_object_id
                $algolia_object_id = get_post_meta($post_id, '_algolia_object_id', true);
                
                if ($algolia_object_id && isset($algolia_photos[$algolia_object_id])) {
                    // Tiene foto en Algolia
                    $algolia_foto = $algolia_photos[$algolia_object_id];
                    $current_foto = get_post_meta($post_id, '_despacho_foto_perfil', true);
                    
                    if ($current_foto !== $algolia_foto) {
                        update_post_meta($post_id, '_despacho_foto_perfil', $algolia_foto);
                        $total_updated++;
                        echo "✅ Post {$post_id}: Foto actualizada<br>";
                    }
                } else {
                    // No tiene foto en Algolia, asignar predeterminada
                    $current_foto = get_post_meta($post_id, '_despacho_foto_perfil', true);
                    if (empty($current_foto)) {
                        update_post_meta($post_id, '_despacho_foto_perfil', $foto_url);
                        $total_assigned_default++;
                        echo "📷 Post {$post_id}: Foto predeterminada asignada<br>";
                    }
                }
                
                $processed++;
            }
            
            echo "<em>Progreso: {$processed}/{$total_wp} (" . round(($processed/$total_wp)*100, 1) . "%)</em>";
            echo "</div>";
            
            // Pausa pequeña para evitar sobrecarga
            if ($batch_num < count($batches) - 1) {
                usleep(100000); // 0.1 segundos
            }
            
            // Flush output para mostrar progreso
            if (ob_get_level()) ob_flush();
            flush();
        }
    }
    
    echo "<h2>🎉 Sincronización COMPLETADA</h2>";
    echo "<div style='background: #d4edda; padding: 15px; border-left: 4px solid #28a745;'>";
    echo "<h3>📊 Resumen Final:</h3>";
    echo "<ul>";
    echo "<li>✅ <strong>Fotos actualizadas desde Algolia:</strong> {$total_updated}</li>";
    echo "<li>📷 <strong>Fotos predeterminadas asignadas:</strong> {$total_assigned_default}</li>";
    echo "<li>📋 <strong>Total procesados:</strong> " . count($wp_despachos) . "</li>";
    echo "<li>⏱️ <strong>Tiempo estimado:</strong> ~" . round(count($wp_despachos)/1000, 1) . " minutos</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<p><strong>🔍 Verificación:</strong> Ve a cualquier página de despacho para confirmar que las fotos se muestran correctamente.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ <strong>Error:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Finalizado:</strong> " . date('Y-m-d H:i:s') . "</p>";
?> 
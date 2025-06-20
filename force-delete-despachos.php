<?php
/**
 * Script para eliminar TODOS los despachos de forma directa y eficiente
 * Elimina de 10 en 10 con progreso visible
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>Eliminación Forzada de Despachos</h1>\n";
echo "<p><strong>⚠️ ATENCIÓN:</strong> Este script eliminará TODOS los despachos de 10 en 10.</p>\n";
echo "<p><strong>⚠️ ESTA ACCIÓN ES IRREVERSIBLE.</strong></p>\n";

try {
    global $wpdb;
    
    // Verificar cuántos despachos hay
    $total_despachos = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->posts} 
        WHERE post_type = 'despacho'
    ");
    
    echo "<h2>Estado actual:</h2>\n";
    echo "<p>Total de despachos en la base de datos: <strong>{$total_despachos}</strong></p>\n";
    
    if ($total_despachos == 0) {
        echo "<p style='color: green;'>✅ No hay despachos para eliminar.</p>\n";
        return;
    }
    
    // Preguntar confirmación
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        echo "<h2>Eliminando despachos de 10 en 10...</h2>\n";
        
        // Desactivar revisiones y autoguardado
        wp_suspend_cache_addition(true);
        
        $batch_size = 10;
        $total_deleted = 0;
        $total_meta_deleted = 0;
        $batch_number = 1;
        
        echo "<div id='delete-progress'>";
        echo "<div class='progress-bar' style='width: 100%; height: 20px; background-color: #f0f0f0; border-radius: 10px; overflow: hidden; margin: 10px 0;'>";
        echo "<div class='progress-fill' style='width: 0%; height: 100%; background-color: #0073aa; transition: width 0.3s;'></div>";
        echo "</div>";
        echo "<p class='progress-text'>Eliminando despachos... <span class='progress-count'>0</span> de <span class='progress-total'>{$total_despachos}</span></p>";
        echo "</div>";
        
        while ($total_deleted < $total_despachos) {
            echo "<h3>Lote {$batch_number}</h3>";
            
            // Obtener IDs de 10 despachos
            $despachos_ids = $wpdb->get_col($wpdb->prepare("
                SELECT ID 
                FROM {$wpdb->posts} 
                WHERE post_type = 'despacho' 
                LIMIT %d
            ", $batch_size));
            
            if (empty($despachos_ids)) {
                break;
            }
            
            $ids_string = implode(',', $despachos_ids);
            
            // Eliminar meta datos de estos despachos
            $meta_deleted = $wpdb->query("
                DELETE FROM {$wpdb->postmeta} 
                WHERE post_id IN ({$ids_string})
            ");
            
            $total_meta_deleted += $meta_deleted;
            echo "<p style='color: blue;'>📝 Meta datos eliminados: {$meta_deleted} registros</p>";
            
            // Eliminar los despachos
            $posts_deleted = $wpdb->query("
                DELETE FROM {$wpdb->posts} 
                WHERE ID IN ({$ids_string})
            ");
            
            $total_deleted += $posts_deleted;
            echo "<p style='color: green;'>✅ Despachos eliminados: {$posts_deleted} registros</p>";
            
            // Mostrar detalles de los despachos eliminados
            echo "<ul>";
            foreach ($despachos_ids as $id) {
                echo "<li>ID: {$id}</li>";
            }
            echo "</ul>";
            
            // Actualizar progreso
            $progress = round(($total_deleted / $total_despachos) * 100);
            echo "<script>
                document.querySelector('.progress-fill').style.width = '{$progress}%';
                document.querySelector('.progress-count').textContent = '{$total_deleted}';
            </script>";
            
            // Flush output para mostrar progreso en tiempo real
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
            
            $batch_number++;
            
            // Pausa pequeña entre lotes
            usleep(100000); // 100ms
        }
        
        // Limpiar cache
        wp_cache_flush();
        
        echo "<h3>Resumen final de eliminación:</h3>\n";
        echo "<ul>\n";
        echo "<li>Total de meta datos eliminados: <strong>{$total_meta_deleted}</strong></li>\n";
        echo "<li>Total de despachos eliminados: <strong>{$total_deleted}</strong></li>\n";
        echo "<li>Lotes procesados: <strong>" . ($batch_number - 1) . "</strong></li>\n";
        echo "</ul>\n";
        
        echo "<p style='color: green; font-size: 18px;'>✅ Eliminación completada exitosamente.</p>\n";
        echo "<p>WordPress ahora tiene 0 despachos.</p>\n";
        
    } else {
        echo "<h2>¿Proceder con la eliminación forzada?</h2>\n";
        echo "<p><strong>⚠️ ATENCIÓN CRÍTICA:</strong> Esta acción eliminará TODOS los despachos de 10 en 10.</p>\n";
        echo "<p><strong>⚠️ ESTA ACCIÓN ES IRREVERSIBLE.</strong></p>\n";
        echo "<p><strong>⚠️ NO SE PUEDE DESHACER.</strong></p>\n";
        echo "<p><strong>⚠️ SE ELIMINARÁN {$total_despachos} DESPACHOS.</strong></p>\n";
        echo "<form method='post'>\n";
        echo "<input type='hidden' name='confirm_delete' value='yes'>\n";
        echo "<button type='submit' style='background: darkred; color: white; padding: 15px 30px; border: none; cursor: pointer; font-size: 16px; font-weight: bold;'>";
        echo "⚠️ SÍ, ELIMINAR DESPACHOS DE 10 EN 10";
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
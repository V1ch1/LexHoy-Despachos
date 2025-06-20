<?php
/**
 * Script para eliminar TODOS los despachos del CPT
 * Elimina tanto los válidos como los vacíos
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>Eliminar Todos los Despachos de WordPress</h1>\n";
echo "<p><strong>⚠️ ATENCIÓN:</strong> Este script eliminará TODOS los despachos del CPT, tanto válidos como vacíos.</p>\n";
echo "<p><strong>⚠️ ESTA ACCIÓN ES IRREVERSIBLE.</strong></p>\n";

try {
    // Obtener todos los despachos
    echo "<h2>1. Analizando despachos en WordPress...</h2>\n";
    
    $wp_despachos = get_posts(array(
        'post_type' => 'despacho',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'all'
    ));
    
    $total_wp = count($wp_despachos);
    echo "<p>Total de despachos en WordPress: <strong>{$total_wp}</strong></p>\n";

    if ($total_wp === 0) {
        echo "<p style='color: green;'>✅ No hay despachos para eliminar.</p>\n";
        return;
    }

    // Analizar registros
    $wp_valid_records = [];
    $wp_empty_records = [];
    
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
        
        // Verificar si el registro tiene datos
        $has_data = !empty($nombre) || !empty($localidad) || !empty($provincia) || 
                   !empty($direccion) || !empty($telefono) || !empty($email) || 
                   !empty($web) || !empty($descripcion);
        
        if ($has_data) {
            $wp_valid_records[] = $post;
        } else {
            $wp_empty_records[] = $post;
        }
    }
    
    echo "<h3>Análisis de WordPress:</h3>\n";
    echo "<ul>\n";
    echo "<li>Despachos válidos (con datos): <strong style='color: green;'>" . count($wp_valid_records) . "</strong></li>\n";
    echo "<li>Despachos vacíos: <strong style='color: red;'>" . count($wp_empty_records) . "</strong></li>\n";
    echo "</ul>\n";

    // Mostrar ejemplos
    echo "<h3>Ejemplos de despachos que se eliminarán:</h3>\n";
    echo "<h4>Despachos válidos (primeros 5):</h4>\n";
    echo "<ul>\n";
    for ($i = 0; $i < min(5, count($wp_valid_records)); $i++) {
        $post = $wp_valid_records[$i];
        $nombre = get_post_meta($post->ID, '_despacho_nombre', true);
        $localidad = get_post_meta($post->ID, '_despacho_localidad', true);
        echo "<li>ID: {$post->ID} - <strong>{$nombre}</strong> - {$localidad}</li>\n";
    }
    echo "</ul>\n";
    
    echo "<h4>Despachos vacíos (primeros 5):</h4>\n";
    echo "<ul>\n";
    for ($i = 0; $i < min(5, count($wp_empty_records)); $i++) {
        $post = $wp_empty_records[$i];
        echo "<li>ID: {$post->ID} - Slug: {$post->post_name} - Título: {$post->post_title}</li>\n";
    }
    echo "</ul>\n";

    echo "<h2>2. Resumen de eliminación</h2>\n";
    echo "<p><strong>Se eliminarán TODOS:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>De WordPress: <strong style='color: red;'>" . count($wp_valid_records) . "</strong> despachos válidos</li>\n";
    echo "<li>De WordPress: <strong style='color: red;'>" . count($wp_empty_records) . "</strong> despachos vacíos</li>\n";
    echo "<li><strong>TOTAL: " . $total_wp . " despachos</strong></li>\n";
    echo "</ul>\n";

    // Preguntar confirmación
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        echo "<h2>3. Eliminando todos los despachos...</h2>\n";
        
        $deleted = 0;
        $errors = 0;
        
        // Eliminar todos los despachos
        foreach ($wp_despachos as $post) {
            try {
                $result = wp_delete_post($post->ID, true); // true = eliminar permanentemente
                
                if ($result) {
                    $deleted++;
                    echo "<p style='color: green;'>✅ Eliminado: ID {$post->ID} ({$post->post_title})</p>\n";
                } else {
                    $errors++;
                    echo "<p style='color: red;'>❌ Error eliminando: ID {$post->ID}</p>\n";
                }
                
            } catch (Exception $e) {
                $errors++;
                echo "<p style='color: red;'>❌ Error eliminando ID {$post->ID}: " . $e->getMessage() . "</p>\n";
            }
        }
        
        echo "<h3>Resumen de eliminación:</h3>\n";
        echo "<ul>\n";
        echo "<li>Eliminados de WordPress: <strong>{$deleted}</strong></li>\n";
        echo "<li>Errores: <strong>{$errors}</strong></li>\n";
        echo "</ul>\n";
        
        echo "<p style='color: green;'>✅ Eliminación completada. WordPress ahora tiene 0 despachos.</p>\n";
        
    } else {
        echo "<h2>¿Proceder con la eliminación TOTAL?</h2>\n";
        echo "<p><strong>⚠️ ATENCIÓN CRÍTICA:</strong> Esta acción eliminará TODOS los despachos de WordPress.</p>\n";
        echo "<p><strong>⚠️ ESTA ACCIÓN ES IRREVERSIBLE.</strong></p>\n";
        echo "<p><strong>⚠️ NO SE PUEDE DESHACER.</strong></p>\n";
        echo "<form method='post'>\n";
        echo "<input type='hidden' name='confirm_delete' value='yes'>\n";
        echo "<button type='submit' style='background: darkred; color: white; padding: 15px 30px; border: none; cursor: pointer; font-size: 16px; font-weight: bold;'>";
        echo "⚠️ SÍ, ELIMINAR TODOS LOS DESPACHOS";
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
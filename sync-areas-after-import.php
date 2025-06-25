<?php
/**
 * Script para sincronizar áreas de práctica después de importar despachos
 * Asigna las áreas a los despachos ya importados
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>🔗 Sincronizando Áreas de Práctica con Despachos</h1>";

// 1. Obtener todas las áreas de práctica
$areas = get_terms(array(
    'taxonomy' => 'area_practica',
    'hide_empty' => false,
));

if (empty($areas)) {
    echo "<p style='color: red;'><strong>❌ No hay áreas de práctica creadas</strong></p>";
    echo "<p>Primero debes sincronizar las áreas desde Algolia.</p>";
    echo "<p><a href='create-basic-areas.php'>Crear áreas básicas</a></p>";
    exit;
}

echo "<h2>1. Áreas de Práctica Disponibles</h2>";
echo "<p><strong>Total de áreas:</strong> " . count($areas) . "</p>";
echo "<ul>";
foreach ($areas as $area) {
    echo "<li>" . esc_html($area->name) . " (ID: " . $area->term_id . ")</li>";
}
echo "</ul>";

// 2. Obtener despachos que no tienen áreas asignadas
$despachos_sin_areas = get_posts(array(
    'post_type' => 'despacho',
    'post_status' => 'publish',
    'numberposts' => -1,
    'meta_query' => array(
        'relation' => 'OR',
        array(
            'key' => '_despacho_areas_practica',
            'compare' => 'NOT EXISTS'
        ),
        array(
            'key' => '_despacho_areas_practica',
            'value' => '',
            'compare' => '='
        )
    )
));

echo "<h2>2. Despachos sin Áreas Asignadas</h2>";
echo "<p><strong>Total de despachos sin áreas:</strong> " . count($despachos_sin_areas) . "</p>";

if (empty($despachos_sin_areas)) {
    echo "<p style='color: green;'><strong>✅ Todos los despachos ya tienen áreas asignadas</strong></p>";
    exit;
}

// 3. Procesar despachos en bloques
$block_size = 100;
$total_despachos = count($despachos_sin_areas);
$total_blocks = ceil($total_despachos / $block_size);
$processed = 0;
$assigned = 0;
$errors = 0;

echo "<h2>3. Asignando Áreas a Despachos</h2>";
echo "<p><strong>Total de bloques:</strong> {$total_blocks}</p>";

for ($block = 0; $block < $total_blocks; $block++) {
    $start_index = $block * $block_size;
    $end_index = min($start_index + $block_size, $total_despachos);
    $block_despachos = array_slice($despachos_sin_areas, $start_index, $block_size);
    
    echo "<h3>📦 Procesando Bloque " . ($block + 1) . " de {$total_blocks}</h3>";
    echo "<p>Despachos " . ($start_index + 1) . "-{$end_index} de {$total_despachos}</p>";
    
    $block_assigned = 0;
    $block_errors = 0;
    
    foreach ($block_despachos as $despacho) {
        try {
            $post_id = $despacho->ID;
            $areas_json = get_post_meta($post_id, '_despacho_areas_practica', true);
            
            if (!empty($areas_json)) {
                $areas_array = json_decode($areas_json, true);
                
                if (is_array($areas_array) && !empty($areas_array)) {
                    // Asignar áreas a la taxonomía
                    $area_terms = array();
                    
                    foreach ($areas_array as $area_name) {
                        // Buscar el término por nombre
                        $term = get_term_by('name', $area_name, 'area_practica');
                        
                        if ($term) {
                            $area_terms[] = $term->term_id;
                        } else {
                            // Si no existe, crear el término
                            $new_term = wp_insert_term($area_name, 'area_practica');
                            if (!is_wp_error($new_term)) {
                                $area_terms[] = $new_term['term_id'];
                            }
                        }
                    }
                    
                    if (!empty($area_terms)) {
                        wp_set_object_terms($post_id, $area_terms, 'area_practica');
                        $block_assigned++;
                        $assigned++;
                    }
                }
            }
            
            $processed++;
            
        } catch (Exception $e) {
            $block_errors++;
            $errors++;
        }
    }
    
    echo "<p><strong>Resumen del bloque " . ($block + 1) . ":</strong></p>";
    echo "<ul>";
    echo "<li>✅ Áreas asignadas: {$block_assigned}</li>";
    echo "<li>❌ Errores: {$block_errors}</li>";
    echo "</ul>";
    
    // Pausa entre bloques
    if ($block < $total_blocks - 1) {
        echo "<p>⏱️ Pausa de 1 segundo...</p>";
        sleep(1);
    }
    
    // Flush output
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

// 4. Resumen final
echo "<h2>4. Resumen Final</h2>";
echo "<div style='background: #f0f8ff; padding: 20px; border-left: 4px solid #0073aa;'>";
echo "<h3>📊 Estadísticas de Sincronización:</h3>";
echo "<ul>";
echo "<li>✅ <strong>Despachos procesados:</strong> " . number_format($processed) . "</li>";
echo "<li>🔗 <strong>Áreas asignadas:</strong> " . number_format($assigned) . "</li>";
echo "<li>❌ <strong>Errores:</strong> " . number_format($errors) . "</li>";
echo "<li>📦 <strong>Bloques procesados:</strong> {$total_blocks}</li>";
echo "</ul>";
echo "</div>";

// 5. Verificar estado final
$final_despachos_con_areas = get_posts(array(
    'post_type' => 'despacho',
    'post_status' => 'publish',
    'numberposts' => -1,
    'tax_query' => array(
        array(
            'taxonomy' => 'area_practica',
            'operator' => 'EXISTS'
        )
    )
));

$total_final = count($final_despachos_con_areas);
$total_despachos_final = get_posts(array(
    'post_type' => 'despacho',
    'post_status' => 'publish',
    'numberposts' => -1,
    'fields' => 'ids'
));
$total_despachos_final = count($total_despachos_final);

echo "<h2>5. Estado Final</h2>";
echo "<p><strong>Despachos con áreas:</strong> " . number_format($total_final) . " de " . number_format($total_despachos_final) . "</p>";

$percentage = $total_despachos_final > 0 ? round(($total_final / $total_despachos_final) * 100, 2) : 0;
echo "<p><strong>Cobertura de áreas:</strong> {$percentage}%</p>";

if ($assigned > 0) {
    echo "<p style='color: green; font-size: 18px;'>🎉 ¡Sincronización de áreas completada!</p>";
}

echo "<hr>";
echo "<p><a href='check-import-status.php'>📊 Verificar estado completo</a></p>";
echo "<p><a href='javascript:location.reload()'>🔄 Actualizar</a></p>";
?> 
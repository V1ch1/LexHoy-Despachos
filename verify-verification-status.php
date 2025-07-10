<?php
/**
 * Script para verificar el estado actual de verificación en WordPress
 * Ejecutar desde: http://lexhoy.local/wp-content/plugins/LexHoy-Despachos/verify-verification-status.php
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

echo "<h1>📊 Estado Actual de Verificación en WordPress</h1>\n";

// Obtener todos los despachos
$posts = get_posts([
    'post_type' => 'despacho',
    'post_status' => 'publish',
    'numberposts' => -1,
    'fields' => 'ids'
]);

$total = count($posts);
$verified = 0;
$not_verified = 0;
$no_meta = 0;

echo "<p>🔍 Analizando {$total} despachos...</p>\n";

foreach ($posts as $post_id) {
    $is_verified = get_post_meta($post_id, '_despacho_is_verified', true);
    
    if ($is_verified === '') {
        $no_meta++;
    } elseif ($is_verified === '1') {
        $verified++;
    } else {
        $not_verified++;
    }
}

echo "<h2>📈 Resultados:</h2>\n";
echo "<div style='background: #f0f0f1; padding: 20px; border-radius: 5px;'>\n";
echo "<ul style='font-size: 18px; line-height: 1.8;'>\n";
echo "<li><strong>Total despachos:</strong> {$total}</li>\n";
echo "<li><strong style='color: green;'>✅ Verificados:</strong> {$verified}</li>\n";
echo "<li><strong style='color: red;'>❌ NO verificados:</strong> {$not_verified}</li>\n";
echo "<li><strong style='color: orange;'>⚠️ Sin estado definido:</strong> {$no_meta}</li>\n";
echo "</ul>\n";
echo "</div>\n";

// Análisis
$percentage_verified = $total > 0 ? round(($verified / $total) * 100, 2) : 0;

echo "<h2>🎯 Análisis:</h2>\n";

if ($percentage_verified > 80) {
    echo "<div style='background: #ffebee; border-left: 4px solid #f44336; padding: 15px;'>\n";
    echo "<h3>🚨 PROBLEMA DETECTADO</h3>\n";
    echo "<p><strong>{$percentage_verified}%</strong> de los despachos están marcados como verificados.</p>\n";
    echo "<p>Esto indica que <strong>AÚN hay un problema</strong> con el estado de verificación.</p>\n";
    echo "</div>\n";
} elseif ($percentage_verified < 20) {
    echo "<div style='background: #e8f5e8; border-left: 4px solid #4caf50; padding: 15px;'>\n";
    echo "<h3>✅ CORRECCIÓN EXITOSA</h3>\n";
    echo "<p>Solo <strong>{$percentage_verified}%</strong> están verificados.</p>\n";
    echo "<p>La mayoría están correctamente marcados como <strong>NO verificados</strong>.</p>\n";
    echo "</div>\n";
} else {
    echo "<div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px;'>\n";
    echo "<h3>⚠️ ESTADO INTERMEDIO</h3>\n";
    echo "<p><strong>{$percentage_verified}%</strong> están verificados.</p>\n";
    echo "<p>Puede ser normal o necesitar ajustes adicionales.</p>\n";
    echo "</div>\n";
}

echo "<h2>🚀 Próximos Pasos para Producción:</h2>\n";
echo "<div style='background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px;'>\n";
echo "<ol>\n";
echo "<li><strong>Confirmar que el problema está resuelto localmente</strong></li>\n";
echo "<li><strong>Preparar archivos para subir a producción</strong></li>\n";
echo "<li><strong>Actualizar el plugin en el servidor</strong></li>\n";
echo "<li><strong>Ejecutar corrección en producción</strong></li>\n";
echo "</ol>\n";
echo "</div>\n"; 
<?php
/**
 * Script para verificar y corregir el estado de verificación de despachos
 * Ejecutar desde el navegador: http://tu-sitio.com/wp-content/plugins/LexHoy-Despachos/fix-verification-status.php
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos de administrador
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado. Necesitas permisos de administrador.');
}

echo "<h1>Verificando estado de verificación de despachos</h1>";

// Obtener todos los despachos
$despachos = get_posts(array(
    'post_type' => 'despacho',
    'posts_per_page' => -1,
    'post_status' => 'publish'
));

$total_despachos = count($despachos);
$verified_count = 0;
$unverified_count = 0;
$fixed_count = 0;

echo "<p>Total de despachos encontrados: <strong>$total_despachos</strong></p>";

echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th style='padding: 10px;'>ID</th>";
echo "<th style='padding: 10px;'>Nombre</th>";
echo "<th style='padding: 10px;'>Estado Actual</th>";
echo "<th style='padding: 10px;'>Valor Meta</th>";
echo "<th style='padding: 10px;'>Acción</th>";
echo "</tr>";

foreach ($despachos as $despacho) {
    $post_id = $despacho->ID;
    $nombre = $despacho->post_title;
    $meta_value = get_post_meta($post_id, '_despacho_is_verified', true);
    
    // Determinar estado actual
    $is_currently_verified = ($meta_value === '1');
    $status_text = $is_currently_verified ? '✅ Verificado' : '❌ No verificado';
    $status_color = $is_currently_verified ? '#d4edda' : '#f8d7da';
    
    if ($is_currently_verified) {
        $verified_count++;
    } else {
        $unverified_count++;
    }
    
    echo "<tr style='background: $status_color;'>";
    echo "<td style='padding: 10px;'>$post_id</td>";
    echo "<td style='padding: 10px;'>" . esc_html($nombre) . "</td>";
    echo "<td style='padding: 10px;'>$status_text</td>";
    echo "<td style='padding: 10px;'>" . esc_html($meta_value) . "</td>";
    echo "<td style='padding: 10px;'>";
    
    // Botones para cambiar estado
    if ($is_currently_verified) {
        echo "<a href='?action=unverify&id=$post_id' style='background: #dc3545; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>Desverificar</a>";
    } else {
        echo "<a href='?action=verify&id=$post_id' style='background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>Verificar</a>";
    }
    
    echo "</td>";
    echo "</tr>";
}

echo "</table>";

// Procesar acciones
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $post_id = intval($_GET['id']);
    
    if ($action === 'verify') {
        update_post_meta($post_id, '_despacho_is_verified', '1');
        echo "<div style='background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "✅ Despacho ID $post_id marcado como verificado";
        echo "</div>";
        $fixed_count++;
    } elseif ($action === 'unverify') {
        update_post_meta($post_id, '_despacho_is_verified', '0');
        echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "❌ Despacho ID $post_id marcado como no verificado";
        echo "</div>";
        $fixed_count++;
    }
}

echo "<h2>Resumen</h2>";
echo "<ul>";
echo "<li><strong>Total de despachos:</strong> $total_despachos</li>";
echo "<li><strong>Verificados:</strong> $verified_count</li>";
echo "<li><strong>No verificados:</strong> $unverified_count</li>";
if ($fixed_count > 0) {
    echo "<li><strong>Cambios realizados:</strong> $fixed_count</li>";
}
echo "</ul>";

echo "<h3>Información técnica</h3>";
echo "<p>El campo <code>_despacho_is_verified</code> debe tener el valor:</p>";
echo "<ul>";
echo "<li><code>'1'</code> para despachos verificados</li>";
echo "<li><code>'0'</code> para despachos no verificados</li>";
echo "</ul>";

echo "<p><a href='javascript:location.reload()'>🔄 Recargar página</a> | <a href='javascript:history.back()'>← Volver atrás</a></p>";
?> 
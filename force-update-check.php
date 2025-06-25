<?php
/**
 * Script para forzar la verificación de actualizaciones
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>🔄 Forzando Verificación de Actualizaciones</h1>";

// 1. Limpiar cache de actualizaciones
delete_option('lexhoy_last_update_check');
delete_site_transient('update_plugins');

echo "<p>✅ Cache de actualizaciones limpiado</p>";

// 2. Forzar verificación manual
$plugin_slug = 'lexhoy-despachos/lexhoy-despachos.php';
$github_url = 'https://api.github.com/repos/V1ch1/LexHoy-Despachos/releases/latest';

echo "<p>🔍 Verificando actualizaciones en GitHub...</p>";

$response = wp_remote_get($github_url, array(
    'timeout' => 15,
    'headers' => array(
        'User-Agent' => 'WordPress/' . get_bloginfo('version')
    )
));

if (is_wp_error($response)) {
    echo "<p style='color: red;'>❌ Error al conectar con GitHub: " . $response->get_error_message() . "</p>";
} else {
    $release = json_decode(wp_remote_retrieve_body($response));
    
    if ($release && isset($release->tag_name)) {
        echo "<p>✅ Última versión en GitHub: <strong>" . $release->tag_name . "</strong></p>";
        echo "<p>📅 Fecha: " . $release->published_at . "</p>";
        
        // Buscar el ZIP del plugin
        $download_url = '';
        if (isset($release->assets) && is_array($release->assets)) {
            foreach ($release->assets as $asset) {
                if (strpos($asset->name, 'lexhoy-despachos.zip') !== false) {
                    $download_url = $asset->browser_download_url;
                    echo "<p>✅ ZIP encontrado: " . $asset->name . "</p>";
                    break;
                }
            }
        }
        
        if (empty($download_url)) {
            echo "<p style='color: orange;'>⚠️ No se encontró el ZIP del plugin</p>";
        } else {
            echo "<p>🔗 URL de descarga: " . $download_url . "</p>";
        }
        
        // Comparar versiones
        $current_version = '1.0.0'; // Versión actual
        if (version_compare($current_version, $release->tag_name, '<')) {
            echo "<p style='color: green; font-size: 18px;'>🎉 ¡Hay una nueva versión disponible!</p>";
            echo "<p><strong>Versión actual:</strong> " . $current_version . "</p>";
            echo "<p><strong>Nueva versión:</strong> " . $release->tag_name . "</p>";
        } else {
            echo "<p style='color: blue;'>✅ Ya tienes la versión más reciente</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ No se pudo obtener información del release</p>";
    }
}

// 3. Forzar verificación de WordPress
echo "<h2>🔄 Forzando verificación de WordPress...</h2>";

// Simular la verificación de WordPress
$transient = new stdClass();
$transient->checked = array($plugin_slug => '1.0.0');

// Llamar a nuestra función de verificación
$updated_transient = lexhoy_check_github_updates($transient);

if (isset($updated_transient->response[$plugin_slug])) {
    echo "<p style='color: green;'>✅ WordPress detectó la actualización</p>";
    echo "<p><strong>Nueva versión:</strong> " . $updated_transient->response[$plugin_slug]->new_version . "</p>";
} else {
    echo "<p style='color: orange;'>⚠️ WordPress no detectó actualización</p>";
}

echo "<hr>";
echo "<p><a href='/wp-admin/plugins.php'>🔙 Volver a Plugins</a></p>";
echo "<p><a href='javascript:location.reload()'>🔄 Actualizar</a></p>";
?> 
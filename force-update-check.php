<?php
/**
 * Script para forzar la verificación de actualizaciones del plugin LexHoy Despachos
 * Uso: https://tudominio.com/wp-content/plugins/LexHoy-Despachos/force-update-check.php
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar que el usuario sea administrador
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado. Solo administradores pueden ejecutar este script.');
}

echo "<h1>Forzar Verificación de Actualizaciones - LexHoy Despachos</h1>";

// Limpiar la caché de actualizaciones
delete_option('lexhoy_last_update_check');
delete_site_transient('update_plugins');

echo "<p>✅ Caché de actualizaciones limpiada</p>";

// Forzar verificación de actualizaciones
$update_plugins = get_site_transient('update_plugins');
if ($update_plugins === false) {
    $update_plugins = new stdClass();
}

// Llamar a nuestra función de verificación
$update_plugins = lexhoy_check_github_updates($update_plugins);

// Guardar el resultado
set_site_transient('update_plugins', $update_plugins, 12 * HOUR_IN_SECONDS);

echo "<p>✅ Verificación de actualizaciones completada</p>";

// Mostrar información del plugin actual
$plugin_file = 'LexHoy-Despachos/lexhoy-despachos.php';
$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);

echo "<h2>Información del Plugin Actual:</h2>";
echo "<ul>";
echo "<li><strong>Versión actual:</strong> " . $plugin_data['Version'] . "</li>";
echo "<li><strong>Nombre:</strong> " . $plugin_data['Plugin Name'] . "</li>";
echo "<li><strong>Descripción:</strong> " . $plugin_data['Description'] . "</li>";
echo "</ul>";

// Verificar si hay actualizaciones disponibles
if (isset($update_plugins->response[$plugin_file])) {
    $update_info = $update_plugins->response[$plugin_file];
    echo "<h2>🎉 ¡Actualización Disponible!</h2>";
    echo "<ul>";
    echo "<li><strong>Nueva versión:</strong> " . $update_info->new_version . "</li>";
    echo "<li><strong>URL:</strong> <a href='" . $update_info->url . "' target='_blank'>" . $update_info->url . "</a></li>";
    echo "<li><strong>Última actualización:</strong> " . $update_info->last_updated . "</li>";
    echo "</ul>";
    echo "<p><a href='" . admin_url('plugins.php') . "' class='button button-primary'>Ir a Plugins para actualizar</a></p>";
} else {
    echo "<h2>✅ No hay actualizaciones disponibles</h2>";
    echo "<p>El plugin está actualizado a la última versión.</p>";
}

// Mostrar información de GitHub
echo "<h2>Información de GitHub:</h2>";
$github_url = 'https://api.github.com/repos/V1ch1/LexHoy-Despachos/releases/latest';
$response = wp_remote_get($github_url, array(
    'timeout' => 15,
    'headers' => array(
        'User-Agent' => 'WordPress/' . get_bloginfo('version')
    )
));

if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
    $release = json_decode(wp_remote_retrieve_body($response));
    echo "<ul>";
    echo "<li><strong>Último release:</strong> " . $release->tag_name . "</li>";
    echo "<li><strong>Fecha:</strong> " . $release->published_at . "</li>";
    echo "<li><strong>Descripción:</strong> " . $release->body . "</li>";
    echo "</ul>";
} else {
    echo "<p>❌ No se pudo obtener información de GitHub</p>";
}

echo "<hr>";
echo "<p><a href='" . admin_url() . "'>← Volver al Panel de Administración</a></p>";
?> 
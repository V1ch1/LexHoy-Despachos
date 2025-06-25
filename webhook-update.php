<?php
/**
 * Webhook para actualizaciones automáticas desde GitHub
 * Configurar en GitHub: Settings → Webhooks → Add webhook
 * Payload URL: https://tudominio.com/wp-content/plugins/LexHoy-Despachos/webhook-update.php
 * Content type: application/json
 * Events: Releases
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar que sea una petición POST de GitHub
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

// Verificar el User-Agent de GitHub
if (!isset($_SERVER['HTTP_USER_AGENT']) || strpos($_SERVER['HTTP_USER_AGENT'], 'GitHub-Hookshot') === false) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Obtener el payload
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Verificar que sea un evento de release
if (!isset($data['action']) || $data['action'] !== 'published') {
    http_response_code(200);
    exit('No es un release publicado');
}

// Verificar que sea nuestro repositorio
if (!isset($data['repository']['full_name']) || $data['repository']['full_name'] !== 'V1ch1/LexHoy-Despachos') {
    http_response_code(200);
    exit('Repositorio incorrecto');
}

// Limpiar caché de actualizaciones
delete_option('lexhoy_last_update_check');
delete_site_transient('update_plugins');

// Forzar verificación inmediata
$update_plugins = get_site_transient('update_plugins');
if ($update_plugins === false) {
    $update_plugins = new stdClass();
}

$update_plugins = lexhoy_check_github_updates($update_plugins);
set_site_transient('update_plugins', $update_plugins, 12 * HOUR_IN_SECONDS);

// Log del evento
error_log('GitHub Webhook: Release ' . $data['release']['tag_name'] . ' publicado. Actualización forzada.');

// Respuesta exitosa
http_response_code(200);
echo 'Webhook procesado correctamente';
?> 
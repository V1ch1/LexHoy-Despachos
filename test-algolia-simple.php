<?php
/**
 * Script simple para probar conectividad con Algolia
 */

// Cargar WordPress
require_once('../../../wp-load.php');

echo "<h1>🔍 Prueba Simple de Conectividad con Algolia</h1>";

// Verificar si WordPress está cargado
if (!function_exists('get_option')) {
    echo "<p style='color: red;'>❌ Error: WordPress no está cargado correctamente</p>";
    exit;
}

// Obtener las opciones de Algolia
$app_id = get_option('lexhoy_despachos_algolia_app_id');
$admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
$index_name = get_option('lexhoy_despachos_algolia_index_name');

echo "<h2>📋 Configuración</h2>";
echo "<p><strong>App ID:</strong> " . ($app_id ? esc_html($app_id) : 'No configurado') . "</p>";
echo "<p><strong>Index Name:</strong> " . ($index_name ? esc_html($index_name) : 'No configurado') . "</p>";
echo "<p><strong>Admin API Key:</strong> " . ($admin_api_key ? substr($admin_api_key, 0, 4) . '...' . substr($admin_api_key, -4) : 'No configurado') . "</p>";

if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
    echo "<p style='color: red;'>❌ Configuración incompleta</p>";
    exit;
}

echo "<h2>🔗 Prueba de Conectividad Simple</h2>";

// Prueba 1: Conexión básica con timeout corto
echo "<h3>Prueba 1: Conexión básica (5 segundos timeout)</h3>";

$url = "https://{$app_id}-dsn.algolia.net/1/indexes/{$index_name}/query";
$headers = [
    'X-Algolia-API-Key: ' . $admin_api_key,
    'X-Algolia-Application-Id: ' . $app_id,
    'Content-Type: application/json'
];

$post_data = [
    'query' => '',
    'hitsPerPage' => 1
];

echo "<p>URL: " . esc_html($url) . "</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Solo 5 segundos
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // 3 segundos para conectar
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Deshabilitar verificación SSL temporalmente
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$start_time = microtime(true);
$response = curl_exec($ch);
$end_time = microtime(true);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$total_time = round(($end_time - $start_time) * 1000, 2);
curl_close($ch);

echo "<p><strong>Tiempo de respuesta:</strong> {$total_time}ms</p>";
echo "<p><strong>HTTP Code:</strong> {$http_code}</p>";
echo "<p><strong>cURL Error:</strong> " . ($curl_error ? esc_html($curl_error) : 'Ninguno') . "</p>";

if ($curl_error) {
    echo "<p style='color: red;'>❌ Error de conexión: " . esc_html($curl_error) . "</p>";
} elseif ($http_code === 200) {
    echo "<p style='color: green;'>✅ Conexión exitosa</p>";
    
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($data['nbHits'])) {
        echo "<p style='color: green;'>✅ Total de registros: <strong>" . intval($data['nbHits']) . "</strong></p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Respuesta recibida pero formato inesperado</p>";
        echo "<pre>" . esc_html(substr($response, 0, 500)) . "</pre>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ HTTP Error {$http_code}</p>";
    echo "<pre>" . esc_html(substr($response, 0, 500)) . "</pre>";
}

// Prueba 2: Con verificación SSL habilitada
echo "<h3>Prueba 2: Con verificación SSL habilitada (10 segundos timeout)</h3>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$start_time = microtime(true);
$response = curl_exec($ch);
$end_time = microtime(true);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$total_time = round(($end_time - $start_time) * 1000, 2);
curl_close($ch);

echo "<p><strong>Tiempo de respuesta:</strong> {$total_time}ms</p>";
echo "<p><strong>HTTP Code:</strong> {$http_code}</p>";
echo "<p><strong>cURL Error:</strong> " . ($curl_error ? esc_html($curl_error) : 'Ninguno') . "</p>";

if ($curl_error) {
    echo "<p style='color: red;'>❌ Error de conexión con SSL: " . esc_html($curl_error) . "</p>";
} elseif ($http_code === 200) {
    echo "<p style='color: green;'>✅ Conexión exitosa con SSL</p>";
} else {
    echo "<p style='color: orange;'>⚠️ HTTP Error {$http_code} con SSL</p>";
}

echo "<hr>";
echo "<p><small>Script de prueba generado el " . date('Y-m-d H:i:s') . "</small></p>";
?> 
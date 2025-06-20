<?php
/**
 * Script para restaurar backup de Algolia
 * Lista las versiones disponibles y permite restaurar
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>Restaurar Backup de Algolia</h1>\n";

try {
    // Obtener configuración
    $app_id = get_option('lexhoy_despachos_algolia_app_id');
    $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
    $index_name = get_option('lexhoy_despachos_algolia_index_name');

    if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
        throw new Exception('Configuración de Algolia incompleta');
    }

    require_once('includes/class-lexhoy-algolia-client.php');
    $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, '', $index_name);

    // 1. Listar versiones disponibles
    echo "<h2>1. Versiones disponibles del índice</h2>\n";
    
    $url = "https://{$app_id}.algolia.net/1/indexes/{$index_name}/logs";
    $headers = [
        'X-Algolia-API-Key: ' . $admin_api_key,
        'X-Algolia-Application-Id: ' . $app_id
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $logs = json_decode($response, true);
        echo "<p>Logs de operaciones recientes:</p>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Timestamp</th><th>IP</th><th>Query</th><th>Nb Hits</th><th>Processing Time</th></tr>\n";
        
        $count = 0;
        foreach ($logs['logs'] as $log) {
            if ($count++ < 20) { // Mostrar solo los últimos 20
                echo "<tr>";
                echo "<td>" . date('Y-m-d H:i:s', $log['timestamp']) . "</td>";
                echo "<td>" . ($log['ip'] ?? 'N/A') . "</td>";
                echo "<td>" . ($log['query'] ?? 'N/A') . "</td>";
                echo "<td>" . ($log['nb_hits'] ?? 'N/A') . "</td>";
                echo "<td>" . ($log['processing_time_ms'] ?? 'N/A') . "ms</td>";
                echo "</tr>\n";
            }
        }
        echo "</table>\n";
    } else {
        echo "<p>No se pudieron obtener los logs (HTTP {$http_code})</p>\n";
    }

    // 2. Intentar obtener información del índice
    echo "<h2>2. Información del índice actual</h2>\n";
    
    $url = "https://{$app_id}.algolia.net/1/indexes/{$index_name}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $index_info = json_decode($response, true);
        echo "<ul>\n";
        echo "<li>Nombre: " . ($index_info['name'] ?? 'N/A') . "</li>\n";
        echo "<li>Registros: " . ($index_info['entries'] ?? 'N/A') . "</li>\n";
        echo "<li>Tamaño: " . ($index_info['dataSize'] ?? 'N/A') . "</li>\n";
        echo "<li>Última actualización: " . ($index_info['updatedAt'] ?? 'N/A') . "</li>\n";
        echo "</ul>\n";
    } else {
        echo "<p>No se pudo obtener información del índice (HTTP {$http_code})</p>\n";
    }

    // 3. Verificar si hay réplicas (copias de seguridad)
    echo "<h2>3. Réplicas disponibles</h2>\n";
    
    $url = "https://{$app_id}.algolia.net/1/indexes";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $indexes = json_decode($response, true);
        echo "<p>Índices disponibles:</p>\n";
        echo "<ul>\n";
        foreach ($indexes['items'] as $index) {
            $index_name_item = $index['name'];
            $entries = $index['entries'] ?? 0;
            $data_size = $index['dataSize'] ?? 'N/A';
            $updated_at = $index['updatedAt'] ?? 'N/A';
            
            echo "<li><strong>{$index_name_item}</strong> - {$entries} registros - {$data_size} - {$updated_at}</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p>No se pudieron obtener los índices (HTTP {$http_code})</p>\n";
    }

    // 4. Opciones de restauración
    echo "<h2>4. Opciones de restauración</h2>\n";
    echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>\n";
    echo "<h3>Opción 1: Contactar soporte de Algolia</h3>\n";
    echo "<p>Algolia mantiene backups automáticos. Contacta a su soporte técnico:</p>\n";
    echo "<ul>\n";
    echo "<li>Email: support@algolia.com</li>\n";
    echo "<li>Menciona tu App ID: <strong>{$app_id}</strong></li>\n";
    echo "<li>Menciona tu índice: <strong>{$index_name}</strong></li>\n";
    echo "<li>Pide restaurar a una versión anterior (antes de la importación)</li>\n";
    echo "</ul>\n";
    
    echo "<h3>Opción 2: Restaurar desde WordPress</h3>\n";
    echo "<p>Si los datos están en WordPress, podemos reexportarlos a Algolia:</p>\n";
    echo "<a href='re-export-to-algolia.php' style='background: blue; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>";
    echo "Re-exportar desde WordPress a Algolia";
    echo "</a>\n";
    
    echo "<h3>Opción 3: Verificar fuente original</h3>\n";
    echo "<p>Si tienes acceso a la fuente original de datos (Excel, CSV, base de datos), podemos reimportar desde ahí.</p>\n";
    echo "</div>\n";

} catch (Exception $e) {
    echo "<h2>Error:</h2>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='javascript:history.back()'>← Volver</a></p>\n";
?> 
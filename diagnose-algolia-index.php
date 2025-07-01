<?php
/**
 * Script para diagnosticar y corregir la configuración del índice de Algolia
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>🔍 Diagnóstico de Configuración de Algolia</h1>\n";

try {
    // Obtener configuración actual
    $app_id = get_option('lexhoy_despachos_algolia_app_id');
    $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
    $search_api_key = get_option('lexhoy_despachos_algolia_search_api_key');
    $index_name = get_option('lexhoy_despachos_algolia_index_name');

    echo "<h2>📋 Configuración actual:</h2>\n";
    echo "<ul>\n";
    echo "<li><strong>App ID:</strong> " . ($app_id ?: '❌ No configurado') . "</li>\n";
    echo "<li><strong>Admin API Key:</strong> " . ($admin_api_key ? '✅ ' . substr($admin_api_key, 0, 8) . '...' : '❌ No configurado') . "</li>\n";
    echo "<li><strong>Search API Key:</strong> " . ($search_api_key ? '✅ ' . substr($search_api_key, 0, 8) . '...' : '❌ No configurado') . "</li>\n";
    echo "<li><strong>Index Name:</strong> <span style='color: red;'>" . ($index_name ?: '❌ No configurado') . "</span></li>\n";
    echo "</ul>\n";

    if (empty($app_id) || empty($admin_api_key)) {
        throw new Exception('❌ Configuración de Algolia incompleta. App ID y Admin API Key son requeridos.');
    }

    // Probar conexión con el índice actual
    echo "<h2>🔧 Probando conexión con índice actual...</h2>\n";
    
    if (!empty($index_name)) {
        $url = "https://{$app_id}-dsn.algolia.net/1/indexes/{$index_name}/query";
        $headers = [
            'X-Algolia-API-Key: ' . $admin_api_key,
            'X-Algolia-Application-Id: ' . $app_id,
            'Content-Type: application/json'
        ];

        $post_data = json_encode([
            'query' => '',
            'hitsPerPage' => 1
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "<p><strong>Prueba con '{$index_name}':</strong> ";
        if ($http_code === 200) {
            echo "<span style='color: green;'>✅ Conexión exitosa</span></p>\n";
            $data = json_decode($response, true);
            if (isset($data['nbHits'])) {
                echo "<p>Total de registros: <strong>" . number_format($data['nbHits']) . "</strong></p>\n";
            }
        } else {
            echo "<span style='color: red;'>❌ Error HTTP {$http_code}</span></p>\n";
        }
    }

    // Probar con el nombre correcto: LexHoy_Despachos
    echo "<h2>🔍 Probando con el nombre correcto: 'LexHoy_Despachos'...</h2>\n";
    
    $correct_index = 'LexHoy_Despachos';
    $url = "https://{$app_id}-dsn.algolia.net/1/indexes/{$correct_index}/query";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "<p><strong>Prueba con '{$correct_index}':</strong> ";
    if ($http_code === 200) {
        echo "<span style='color: green;'>✅ Conexión exitosa</span></p>\n";
        $data = json_decode($response, true);
        if (isset($data['nbHits'])) {
            echo "<p>Total de registros: <strong>" . number_format($data['nbHits']) . "</strong></p>\n";
        }
        
        // Mostrar algunos registros de muestra
        if (isset($data['hits']) && !empty($data['hits'])) {
            echo "<h3>📋 Primeros registros encontrados:</h3>\n";
            echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>\n";
            echo "<tr style='background: #f8f9fa;'>\n";
            echo "<th style='padding: 8px;'>Object ID</th>\n";
            echo "<th style='padding: 8px;'>Nombre</th>\n";
            echo "<th style='padding: 8px;'>Localidad</th>\n";
            echo "<th style='padding: 8px;'>Provincia</th>\n";
            echo "</tr>\n";
            
            foreach (array_slice($data['hits'], 0, 5) as $hit) {
                $object_id = $hit['objectID'] ?? 'N/A';
                $nombre = $hit['nombre'] ?? 'N/A';
                $localidad = $hit['localidad'] ?? 'N/A';
                $provincia = $hit['provincia'] ?? 'N/A';
                
                $nombre_color = empty($hit['nombre']) ? 'color: red;' : 'color: green;';
                
                echo "<tr>\n";
                echo "<td style='padding: 8px;'>" . esc_html($object_id) . "</td>\n";
                echo "<td style='padding: 8px; {$nombre_color}'>" . esc_html($nombre) . "</td>\n";
                echo "<td style='padding: 8px;'>" . esc_html($localidad) . "</td>\n";
                echo "<td style='padding: 8px;'>" . esc_html($provincia) . "</td>\n";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
        
        // Ofrecer corregir la configuración
        if ($index_name !== $correct_index) {
            echo "<h2>🔧 Corregir configuración</h2>\n";
            echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
            echo "<p>⚠️ El índice configurado (<strong>{$index_name}</strong>) no coincide con el índice funcional (<strong>{$correct_index}</strong>).</p>\n";
            echo "</div>\n";
            
            if (isset($_POST['fix_config']) && $_POST['fix_config'] === 'yes') {
                update_option('lexhoy_despachos_algolia_index_name', $correct_index);
                echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
                echo "<h4>✅ ¡Configuración corregida!</h4>\n";
                echo "<p>El nombre del índice se ha actualizado a: <strong>{$correct_index}</strong></p>\n";
                echo "<p><a href='javascript:location.reload()'>🔄 Recargar página para verificar</a></p>\n";
                echo "</div>\n";
            } else {
                echo "<form method='post' style='margin: 20px 0;'>\n";
                echo "<input type='hidden' name='fix_config' value='yes'>\n";
                echo "<button type='submit' style='background: #28a745; color: white; padding: 15px 30px; border: none; cursor: pointer; font-size: 16px; font-weight: bold; border-radius: 5px;'>";
                echo "✅ CORREGIR CONFIGURACIÓN";
                echo "</button>\n";
                echo "</form>\n";
            }
        }
        
    } else {
        echo "<span style='color: red;'>❌ Error HTTP {$http_code}</span></p>\n";
        echo "<p>Respuesta: " . esc_html($response) . "</p>\n";
    }

    // Listar todos los índices disponibles
    echo "<h2>📋 Listar todos los índices disponibles...</h2>\n";
    
    $url = "https://{$app_id}-dsn.algolia.net/1/indexes";
    $headers = [
        'X-Algolia-API-Key: ' . $admin_api_key,
        'X-Algolia-Application-Id: ' . $app_id,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (isset($data['items']) && is_array($data['items'])) {
            echo "<p>✅ Índices encontrados:</p>\n";
            echo "<ul>\n";
            foreach ($data['items'] as $index) {
                $index_name_api = $index['name'] ?? 'N/A';
                $entries = $index['entries'] ?? 0;
                echo "<li><strong>{$index_name_api}</strong> - {$entries} registros</li>\n";
            }
            echo "</ul>\n";
        } else {
            echo "<p>❌ No se pudieron listar los índices</p>\n";
        }
    } else {
        echo "<p>❌ Error al listar índices: HTTP {$http_code}</p>\n";
    }

} catch (Exception $e) {
    echo "<h2>❌ Error:</h2>\n";
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<p><strong>Error:</strong> " . esc_html($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

echo "<hr style='margin: 40px 0;'>\n";
echo "<div style='text-align: center;'>\n";
echo "<a href='javascript:history.back()' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>← Volver</a>\n";
echo "<a href='javascript:location.reload()' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔄 Actualizar</a>\n";
echo "</div>\n";
?> 
<?php
/**
 * Script Simple: Marcar TODOS los registros de Algolia como NO verificados
 * Ejecutar desde: http://lexhoy.local/wp-content/plugins/LexHoy-Despachos/fix-algolia-verification-simple.php
 */

// Cargar WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    // Intentar ruta alternativa
    require_once('../../../wp-load.php');
}

// Verificar permisos
if (!current_user_can('manage_options')) {
    wp_die('❌ Acceso denegado. Necesitas permisos de administrador.');
}

// Obtener credenciales reales de WordPress
$algolia_config = [
    'app_id' => get_option('lexhoy_despachos_algolia_app_id'),
    'admin_api_key' => get_option('lexhoy_despachos_algolia_admin_api_key'),
    'index_name' => get_option('lexhoy_despachos_algolia_index_name')
];

// Verificar que tenemos credenciales
if (empty($algolia_config['app_id']) || empty($algolia_config['admin_api_key']) || empty($algolia_config['index_name'])) {
    wp_die('❌ Error: Las credenciales de Algolia no están configuradas en WordPress. 
           Ve a Despachos > Configuración de Algolia para configurarlas.');
}

echo "<h1>🔧 Corrección Simple de Verificación en Algolia</h1>\n";
echo "<p><strong>Objetivo:</strong> Marcar TODOS los registros como NO verificados</p>\n";
echo "<p><strong>Configuración:</strong></p>\n";
echo "<ul>\n";
echo "<li>App ID: " . $algolia_config['app_id'] . "</li>\n";
echo "<li>Index: " . $algolia_config['index_name'] . "</li>\n";
echo "<li>Admin API Key: " . substr($algolia_config['admin_api_key'], 0, 8) . "...</li>\n";
echo "</ul>\n";

class SimpleAlgoliaFix {
    
    private $app_id;
    private $admin_api_key;
    private $index_name;
    
    public function __construct($config) {
        $this->app_id = $config['app_id'];
        $this->admin_api_key = $config['admin_api_key'];
        $this->index_name = $config['index_name'];
    }
    
    private function log($message) {
        echo "[" . date('H:i:s') . "] {$message}<br>\n";
        flush();
    }
    
    /**
     * Obtener todos los registros de Algolia
     */
    public function get_all_records() {
        $this->log("📥 Obteniendo todos los registros de Algolia...");
        
        $url = "https://{$this->app_id}-dsn.algolia.net/1/indexes/{$this->index_name}/query";
        $headers = [
            'X-Algolia-API-Key: ' . $this->admin_api_key,
            'X-Algolia-Application-Id: ' . $this->app_id,
            'Content-Type: application/json'
        ];
        
        $all_records = [];
        $page = 0;
        $hits_per_page = 1000;
        
        do {
            $post_data = [
                'query' => '',
                'hitsPerPage' => $hits_per_page,
                'page' => $page
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 200) {
                throw new Exception("Error HTTP {$http_code}: {$response}");
            }
            
            $data = json_decode($response, true);
            if (!$data || !isset($data['hits'])) {
                break;
            }
            
            $all_records = array_merge($all_records, $data['hits']);
            $this->log("📄 Página {$page}: " . count($data['hits']) . " registros obtenidos");
            
            $page++;
            
        } while (count($data['hits']) == $hits_per_page);
        
        $this->log("✅ Total obtenido: " . count($all_records) . " registros");
        return $all_records;
    }
    
    /**
     * Actualizar un registro en Algolia
     */
    public function update_record($record) {
        $url = "https://{$this->app_id}.algolia.net/1/indexes/{$this->index_name}/{$record['objectID']}";
        $headers = [
            'X-Algolia-API-Key: ' . $this->admin_api_key,
            'X-Algolia-Application-Id: ' . $this->app_id,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($record));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $http_code === 200;
    }
    
    /**
     * Proceso principal: marcar todos como NO verificados
     */
    public function fix_all_verification() {
        $this->log("🚀 INICIANDO CORRECCIÓN DE VERIFICACIÓN");
        
        try {
            // Obtener todos los registros
            $records = $this->get_all_records();
            $total = count($records);
            
            if ($total == 0) {
                $this->log("❌ No se encontraron registros");
                return false;
            }
            
            // Estadísticas iniciales
            $verified_count = 0;
            foreach ($records as $record) {
                if (isset($record['isVerified']) && $record['isVerified'] === true) {
                    $verified_count++;
                }
            }
            
            $this->log("📊 ESTADO INICIAL:");
            $this->log("   Total registros: {$total}");
            $this->log("   Verificados: {$verified_count}");
            $this->log("   No verificados: " . ($total - $verified_count));
            
            // Actualizar registros
            $this->log("🔧 Iniciando actualización...");
            $updated = 0;
            $errors = 0;
            
            foreach ($records as $record) {
                // Marcar como NO verificado
                $record['isVerified'] = false;
                $record['estado_verificacion'] = 'pendiente';
                
                if ($this->update_record($record)) {
                    $updated++;
                    
                    if ($updated % 100 == 0) {
                        $this->log("⏳ Progreso: {$updated}/{$total} actualizados");
                    }
                } else {
                    $errors++;
                    if ($errors <= 5) {  // Solo mostrar los primeros 5 errores
                        $this->log("❌ Error actualizando: " . $record['objectID']);
                    }
                }
            }
            
            $this->log("📊 RESULTADO FINAL:");
            $this->log("   Total procesados: {$total}");
            $this->log("   Actualizados exitosamente: {$updated}");
            $this->log("   Errores: {$errors}");
            
            if ($updated > 0) {
                $this->log("✅ CORRECCIÓN COMPLETADA");
                return true;
            } else {
                $this->log("❌ NO SE PUDO COMPLETAR LA CORRECCIÓN");
                return false;
            }
            
        } catch (Exception $e) {
            $this->log("❌ ERROR: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Solo verificar estado actual
     */
    public function check_status() {
        $this->log("📊 VERIFICANDO ESTADO ACTUAL");
        
        try {
            $records = $this->get_all_records();
            $total = count($records);
            $verified = 0;
            $pending = 0;
            
            foreach ($records as $record) {
                if (isset($record['isVerified']) && $record['isVerified'] === true) {
                    $verified++;
                } else {
                    $pending++;
                }
            }
            
            $verified_percent = $total > 0 ? round(($verified / $total) * 100, 1) : 0;
            
            $this->log("📈 ESTADÍSTICAS:");
            $this->log("   Total: {$total} registros");
            $this->log("   Verificados: {$verified} ({$verified_percent}%)");
            $this->log("   No verificados: {$pending}");
            
            return [
                'total' => $total,
                'verified' => $verified,
                'pending' => $pending,
                'percent_verified' => $verified_percent
            ];
            
        } catch (Exception $e) {
            $this->log("❌ ERROR: " . $e->getMessage());
            return false;
        }
    }
}

// Procesamiento
if (isset($_GET['action'])) {
    $fixer = new SimpleAlgoliaFix($algolia_config);
    
    echo "<div style='background: #f0f0f0; padding: 20px; margin: 20px 0; font-family: monospace; border-radius: 5px;'>\n";
    
    switch ($_GET['action']) {
        case 'check':
            $stats = $fixer->check_status();
            if ($stats) {
                echo "<h3>📊 Estado Actual</h3>";
                echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
                echo "<tr><th style='padding: 8px;'>Métrica</th><th style='padding: 8px;'>Valor</th></tr>";
                echo "<tr><td style='padding: 8px;'>Total</td><td style='padding: 8px;'>{$stats['total']}</td></tr>";
                echo "<tr><td style='padding: 8px;'>Verificados</td><td style='padding: 8px;'>{$stats['verified']} ({$stats['percent_verified']}%)</td></tr>";
                echo "<tr><td style='padding: 8px;'>No verificados</td><td style='padding: 8px;'>{$stats['pending']}</td></tr>";
                echo "</table>";
            }
            break;
            
        case 'fix':
            $result = $fixer->fix_all_verification();
            if ($result) {
                echo "<h3 style='color: green;'>✅ ¡Corrección Exitosa!</h3>";
                echo "<p>Todos los registros han sido marcados como NO verificados.</p>";
            } else {
                echo "<h3 style='color: red;'>❌ Error en la Corrección</h3>";
                echo "<p>Revisa los logs de arriba para más detalles.</p>";
            }
            break;
    }
    
    echo "</div>\n";
    echo "<p><a href='?' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;'>← Volver al menú</a></p>\n";
    
} else {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Corrección Simple de Verificación - Algolia</title>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
            .container { max-width: 800px; }
            .action-button { 
                display: inline-block; 
                padding: 15px 25px; 
                margin: 10px 5px; 
                background: #0073aa; 
                color: white; 
                text-decoration: none; 
                border-radius: 5px; 
                font-weight: bold;
            }
            .action-button:hover { background: #005a87; }
            .action-button.danger { background: #dc3545; }
            .action-button.danger:hover { background: #c82333; }
            .action-button.success { background: #28a745; }
            .action-button.success:hover { background: #218838; }
            .warning { 
                background: #fff3cd; 
                border: 1px solid #ffeaa7; 
                padding: 20px; 
                margin: 20px 0; 
                border-radius: 5px;
            }
            .info {
                background: #d1ecf1;
                border: 1px solid #bee5eb;
                padding: 20px;
                margin: 20px 0;
                border-radius: 5px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔧 Corrección Simple de Verificación - Algolia</h1>
            
            <div class="info">
                <h3>🎯 Objetivo</h3>
                <p>Marcar <strong>TODOS</strong> los registros de Algolia como <strong>NO verificados</strong></p>
                <ul>
                    <li>✅ Configuración: <?php echo $algolia_config['app_id']; ?> → <?php echo $algolia_config['index_name']; ?></li>
                    <li>🔧 Campo isVerified → false</li>
                    <li>📝 Campo estado_verificacion → "pendiente"</li>
                </ul>
            </div>
            
            <div class="warning">
                <h3>📋 Flujo Completo</h3>
                <ol>
                    <li><strong>Paso 1:</strong> Corregir Algolia (este script)</li>
                    <li><strong>Paso 2:</strong> WordPress se sincronizará automáticamente</li>
                    <li><strong>Paso 3:</strong> Importar registros faltantes (2,778)</li>
                </ol>
            </div>
            
            <h2>🚀 Acciones</h2>
            
            <h3>1. Diagnóstico</h3>
            <a href="?action=check" class="action-button">📊 Ver Estado Actual</a>
            
            <h3>2. Corrección</h3>
            <a href="?action=fix" class="action-button danger">🔧 MARCAR TODOS COMO NO VERIFICADOS</a>
            
            <h3>📝 Notas</h3>
            <ul>
                <li><strong>Seguro:</strong> Solo cambia el estado de verificación</li>
                <li><strong>Masivo:</strong> Procesa todos los registros de una vez</li>
                <li><strong>Progreso:</strong> Muestra avance cada 100 registros</li>
            </ul>
        </div>
    </body>
    </html>
    <?php
}
?> 
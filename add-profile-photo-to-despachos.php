<?php
/**
 * Script para añadir fotos de perfil a todos los despachos en Algolia
 * 
 * Este script actualiza todos los registros existentes en Algolia
 * para añadir una foto de perfil predeterminada.
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    require_once(dirname(__FILE__) . '/../../../wp-load.php');
}

// Verificar permisos de administrador
if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos suficientes para ejecutar este script.');
}

// Incluir las clases necesarias
require_once(dirname(__FILE__) . '/includes/class-lexhoy-algolia-client.php');

class LexhoyAddProfilePhoto {
    
    private $algolia_client;
    private $default_photo_url;
    
    public function __construct() {
        // URL de la foto predeterminada - Auto-detecta entorno
        $this->default_photo_url = $this->get_photo_url();
        
        // Inicializar cliente de Algolia
        $this->init_algolia_client();
    }
    
    /**
     * Obtener URL de la foto según el entorno
     */
    private function get_photo_url() {
        // Detectar si estamos en desarrollo o producción
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        if (strpos($host, '.local') !== false || strpos($host, 'localhost') !== false) {
            // Entorno de desarrollo
            return 'http://lexhoy.local/wp-content/uploads/2025/07/FOTO-DESPACHO-500X500.webp';
        } else {
            // Entorno de producción
            return 'https://lexhoy.com/wp-content/uploads/2025/07/FOTO-DESPACHO-500X500.webp';
        }
    }
    
    /**
     * Inicializar cliente de Algolia
     */
    private function init_algolia_client() {
        $app_id = get_option('lexhoy_despachos_algolia_app_id');
        $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
        $search_api_key = get_option('lexhoy_despachos_algolia_search_api_key');
        $index_name = get_option('lexhoy_despachos_algolia_index_name');

        if ($app_id && $admin_api_key && $search_api_key && $index_name) {
            $this->algolia_client = new LexhoyAlgoliaClient($app_id, $admin_api_key, $search_api_key, $index_name);
        } else {
            throw new Exception('Configuración de Algolia incompleta');
        }
    }
    
    /**
     * Ejecutar la actualización masiva
     */
    public function execute() {
        try {
            echo '<div class="wrap">';
            echo '<h1>🖼️ Añadir Fotos de Perfil a Despachos</h1>';
            echo '<p>Este script añadirá fotos de perfil predeterminadas a todos los despachos en Algolia.</p>';
            
            // Obtener todos los registros de Algolia
            echo '<h2>📊 Obteniendo registros de Algolia...</h2>';
            $result = $this->algolia_client->browse_all_unfiltered();
            
            if (!$result['success']) {
                throw new Exception('Error al obtener registros: ' . $result['message']);
            }
            
            $all_records = $result['hits'];
            $total_records = count($all_records);
            
            echo '<p>✅ Total de registros encontrados: <strong>' . number_format($total_records) . '</strong></p>';
            
            // Filtrar registros que ya tienen foto de perfil
            $records_without_photo = [];
            $records_with_photo = [];
            
            foreach ($all_records as $record) {
                $has_photo = !empty($record['foto_perfil'] ?? '');
                
                if ($has_photo) {
                    $records_with_photo[] = $record;
                } else {
                    $records_without_photo[] = $record;
                }
            }
            
            echo '<div class="notice notice-info">';
            echo '<h3>📈 Análisis de fotos de perfil:</h3>';
            echo '<ul>';
            echo '<li>✅ <strong>Despachos CON foto:</strong> ' . number_format(count($records_with_photo)) . '</li>';
            echo '<li>❌ <strong>Despachos SIN foto:</strong> ' . number_format(count($records_without_photo)) . '</li>';
            echo '</ul>';
            echo '</div>';
            
            if (empty($records_without_photo)) {
                echo '<div class="notice notice-success">';
                echo '<h3>🎉 ¡Excelente!</h3>';
                echo '<p>✅ Todos los despachos ya tienen foto de perfil asignada.</p>';
                echo '</div>';
                echo '</div>';
                return;
            }
            
            // Mostrar la foto que se va a usar
            echo '<h3>🖼️ Foto predeterminada que se asignará:</h3>';
            echo '<div style="border: 1px solid #ccc; padding: 10px; background: #f9f9f9; margin: 10px 0;">';
            echo '<p><strong>URL:</strong> <code>' . esc_html($this->default_photo_url) . '</code></p>';
            echo '<img src="' . esc_url($this->default_photo_url) . '" alt="Foto predeterminada" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd;">';
            echo '</div>';
            
            // Procesar en lotes
            echo '<h2>🔄 Procesando actualizaciones...</h2>';
            
            $batch_size = 100; // Procesar 100 registros por lote
            $total_batches = ceil(count($records_without_photo) / $batch_size);
            $updated_count = 0;
            $error_count = 0;
            
            for ($batch = 0; $batch < $total_batches; $batch++) {
                $start_index = $batch * $batch_size;
                $batch_records = array_slice($records_without_photo, $start_index, $batch_size);
                
                echo '<h4>📦 Procesando lote ' . ($batch + 1) . ' de ' . $total_batches . '</h4>';
                echo '<p>Actualizando ' . count($batch_records) . ' registros...</p>';
                
                // Preparar las actualizaciones para este lote
                $updates = [];
                foreach ($batch_records as $record) {
                    $object_id = $record['objectID'];
                    $updates[$object_id] = [
                        'foto_perfil' => $this->default_photo_url
                    ];
                }
                
                try {
                    // Ejecutar actualización en lote
                    $update_result = $this->algolia_client->batch_partial_update(
                        $this->algolia_client->get_index_name(),
                        $updates
                    );
                    
                    $batch_updated = count($updates);
                    $updated_count += $batch_updated;
                    
                    echo '<span style="color: green;">✅ Lote ' . ($batch + 1) . ' completado: ' . $batch_updated . ' registros actualizados</span><br>';
                    
                    // Mostrar algunos IDs de ejemplo
                    $sample_ids = array_slice(array_keys($updates), 0, 3);
                    echo '<small>Ejemplos: ' . implode(', ', $sample_ids) . '</small><br>';
                    
                } catch (Exception $e) {
                    $error_count += count($updates);
                    echo '<span style="color: red;">❌ Error en lote ' . ($batch + 1) . ': ' . $e->getMessage() . '</span><br>';
                }
                
                // Pausa entre lotes para no sobrecargar la API
                if ($batch < $total_batches - 1) {
                    echo '<p>⏱️ Pausa de 2 segundos...</p>';
                    sleep(2);
                }
                
                // Flush output para mostrar progreso en tiempo real
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
            
            // Resumen final
            echo '<h2>📊 Resumen Final</h2>';
            echo '<div class="notice ' . ($error_count > 0 ? 'notice-warning' : 'notice-success') . '">';
            echo '<h3>🎯 Estadísticas de la actualización:</h3>';
            echo '<ul>';
            echo '<li>✅ <strong>Registros actualizados:</strong> ' . number_format($updated_count) . '</li>';
            echo '<li>❌ <strong>Errores:</strong> ' . number_format($error_count) . '</li>';
            echo '<li>📈 <strong>Tasa de éxito:</strong> ' . (count($records_without_photo) > 0 ? round(($updated_count / count($records_without_photo)) * 100, 2) : 0) . '%</li>';
            echo '<li>🖼️ <strong>Foto asignada:</strong> ' . esc_html($this->default_photo_url) . '</li>';
            echo '</ul>';
            echo '</div>';
            
            if ($updated_count > 0) {
                echo '<div class="notice notice-success">';
                echo '<h4>🎉 ¡Proceso completado!</h4>';
                echo '<p>Se añadieron fotos de perfil a <strong>' . number_format($updated_count) . '</strong> despachos en Algolia.</p>';
                echo '<p>Los cambios se reflejarán en las búsquedas inmediatamente.</p>';
                echo '</div>';
            }
            
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error">';
            echo '<h3>❌ Error durante el proceso:</h3>';
            echo '<p>' . esc_html($e->getMessage()) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Método para cambiar la URL de la foto predeterminada
     */
    public function set_default_photo_url($url) {
        $this->default_photo_url = $url;
    }
}

// Si se está ejecutando desde la web, mostrar la interfaz
if (isset($_GET['action']) && $_GET['action'] === 'execute') {
    $updater = new LexhoyAddProfilePhoto();
    
    // IMPORTANTE: ¡Cambia esta URL por la tuya!
    // $updater->set_default_photo_url('https://tu-dominio.com/path/to/foto-abogado-default.jpg');
    
    $updater->execute();
} else {
    // Mostrar formulario para confirmar la ejecución
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Añadir Fotos de Perfil - LexHoy Despachos</title>
        <link rel="stylesheet" href="<?php echo admin_url('css/wp-admin.css'); ?>">
        <style>
            .wrap { max-width: 800px; margin: 20px auto; padding: 20px; }
            .photo-preview { border: 1px solid #ccc; padding: 20px; background: #f9f9f9; text-align: center; margin: 20px 0; }
            .photo-preview img { max-width: 300px; max-height: 300px; border: 2px solid #0073aa; }
            .warning-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .info-box { background: #d1ecf1; border: 1px solid #b8daff; padding: 15px; margin: 20px 0; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="wrap">
            <h1>🖼️ Añadir Fotos de Perfil a Despachos</h1>
            
            <div class="info-box">
                <h3>📋 ¿Qué hace este script?</h3>
                <p>Este script añadirá una foto de perfil predeterminada a <strong>todos los despachos</strong> en Algolia que actualmente no tengan una.</p>
                <ul>
                    <li>✅ Obtiene todos los registros de Algolia</li>
                    <li>🔍 Identifica cuáles no tienen foto de perfil</li>
                    <li>🖼️ Les asigna una foto predeterminada</li>
                    <li>🚀 Los actualiza en lotes para mayor eficiencia</li>
                </ul>
            </div>
            
            <div class="photo-preview">
                <h3>🖼️ Foto Predeterminada</h3>
                <p><strong>⚠️ IMPORTANTE:</strong> Actualmente está configurada una URL de ejemplo.</p>
                <p><strong>Debes editarla en el archivo antes de ejecutar el script.</strong></p>
                <p><code>Línea 23: $this->default_photo_url = 'TU_URL_AQUÍ';</code></p>
                <hr>
                <p><small>Ejemplo de cómo se verá:</small></p>
                <div style="background: #f0f0f0; padding: 20px; border: 1px dashed #ccc;">
                    <p>🖼️ [Aquí aparecerá tu foto cuando configures la URL]</p>
                </div>
            </div>
            
            <div class="warning-box">
                <h3>⚠️ Antes de continuar:</h3>
                <ol>
                    <li><strong>Edita el archivo:</strong> Cambia la URL en la línea 23 por tu foto real</li>
                    <li><strong>Sube tu foto:</strong> Asegúrate de que la foto esté subida a tu servidor</li>
                    <li><strong>Verifica la URL:</strong> Comprueba que la URL sea accesible públicamente</li>
                    <li><strong>Formato recomendado:</strong> JPG o PNG, máximo 500x500px</li>
                </ol>
            </div>
            
            <?php
            // Verificar configuración de Algolia
            $app_id = get_option('lexhoy_despachos_algolia_app_id');
            $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
            $index_name = get_option('lexhoy_despachos_algolia_index_name');
            
            if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
                echo '<div class="notice notice-error">';
                echo '<h3>❌ Configuración de Algolia incompleta</h3>';
                echo '<p>Completa la configuración de Algolia antes de ejecutar este script.</p>';
                echo '</div>';
            } else {
                echo '<form method="get" style="text-align: center; margin: 30px 0;">';
                echo '<input type="hidden" name="action" value="execute">';
                echo '<button type="submit" class="button button-primary" style="background: #d63638; color: white; padding: 15px 30px; font-size: 16px; font-weight: bold;" onclick="return confirm(\'¿Has configurado la URL de la foto? Esta acción añadirá fotos a TODOS los despachos sin foto en Algolia.\');">';
                echo '🚀 EJECUTAR ACTUALIZACIÓN MASIVA';
                echo '</button>';
                echo '</form>';
            }
            ?>
            
            <div class="info-box">
                <h3>🛡️ Información de Seguridad</h3>
                <p>✅ <strong>Operación segura:</strong> Solo añade fotos, no modifica otros datos</p>
                <p>✅ <strong>No sobrescribe:</strong> Respeta las fotos ya existentes</p>
                <p>✅ <strong>Procesos en lotes:</strong> Optimizado para gran cantidad de registros</p>
                <p>✅ <strong>Log detallado:</strong> Muestra progreso y errores en tiempo real</p>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?> 
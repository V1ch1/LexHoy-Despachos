<?php
/**
 * Script Simple para Añadir Fotos de Perfil a Despachos en Algolia
 * Ejecutar desde terminal: php add-photos-simple.php
 */

// Cargar WordPress si está disponible
if (file_exists('../../../wp-config.php')) {
    require_once('../../../wp-config.php');
} elseif (file_exists('../../wp-config.php')) {
    require_once('../../wp-config.php');
} elseif (file_exists('../wp-config.php')) {
    require_once('../wp-config.php');
}

// Cargar el cliente de Algolia
require_once('includes/class-lexhoy-algolia-client.php');

class SimplePhotoUpdater {
    private $algolia_client;
    private $default_photo_url;
    
    public function __construct() {
        // Auto-detecta entorno y configura URL de foto
        $this->default_photo_url = $this->get_photo_url();
        
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
    
    private function init_algolia_client() {
        // Obtener configuración desde WordPress o definir manualmente
        if (function_exists('get_option')) {
            $app_id = get_option('lexhoy_despachos_algolia_app_id');
            $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
            $search_api_key = get_option('lexhoy_despachos_algolia_search_api_key');
            $index_name = get_option('lexhoy_despachos_algolia_index_name');
        } else {
            // Si no tienes WordPress cargado, define manualmente aquí:
            $app_id = 'TU_APP_ID';
            $admin_api_key = 'TU_ADMIN_API_KEY';
            $search_api_key = 'TU_SEARCH_API_KEY';
            $index_name = 'TU_INDEX_NAME';
        }

        if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
            throw new Exception('❌ Configuración de Algolia incompleta. Verifica las credenciales.');
        }

        $this->algolia_client = new LexhoyAlgoliaClient($app_id, $admin_api_key, $search_api_key, $index_name);
        echo "✅ Cliente de Algolia inicializado correctamente\n";
    }
    
    public function execute() {
        try {
            echo "🚀 INICIANDO ACTUALIZACIÓN MASIVA DE FOTOS DE PERFIL\n";
            echo "====================================================\n\n";
            
            // Obtener todos los registros
            echo "📊 Obteniendo todos los registros de Algolia...\n";
            $result = $this->algolia_client->browse_all_unfiltered();
            
            if (!$result['success']) {
                throw new Exception('Error al obtener registros: ' . $result['message']);
            }
            
            $all_records = $result['hits'];
            $total_records = count($all_records);
            
            echo "✅ Total de registros encontrados: " . number_format($total_records) . "\n\n";
            
            // Analizar registros
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
            
            echo "📈 ANÁLISIS DE FOTOS DE PERFIL:\n";
            echo "  ✅ Despachos CON foto: " . number_format(count($records_with_photo)) . "\n";
            echo "  ❌ Despachos SIN foto: " . number_format(count($records_without_photo)) . "\n";
            echo "  🖼️ Foto que se asignará: " . $this->default_photo_url . "\n\n";
            
            if (empty($records_without_photo)) {
                echo "🎉 ¡PERFECTO! Todos los despachos ya tienen foto de perfil.\n";
                return;
            }
            
            // Confirmar ejecución
            echo "⚠️  ATENCIÓN: Se va a añadir la foto predeterminada a " . count($records_without_photo) . " despachos.\n";
            echo "¿Deseas continuar? (y/N): ";
            $handle = fopen("php://stdin", "r");
            $confirmation = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($confirmation) !== 'y' && strtolower($confirmation) !== 'yes') {
                echo "❌ Operación cancelada por el usuario.\n";
                return;
            }
            
            // Procesar en lotes
            echo "\n🔄 PROCESANDO ACTUALIZACIONES EN LOTES...\n";
            echo "=========================================\n";
            
            $batch_size = 100;
            $total_batches = ceil(count($records_without_photo) / $batch_size);
            $updated_count = 0;
            $error_count = 0;
            
            for ($batch = 0; $batch < $total_batches; $batch++) {
                $start_index = $batch * $batch_size;
                $batch_records = array_slice($records_without_photo, $start_index, $batch_size);
                
                echo "\n📦 Lote " . ($batch + 1) . "/" . $total_batches . " - Procesando " . count($batch_records) . " registros...\n";
                
                // Preparar actualizaciones
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
                    
                    echo "   ✅ Lote completado: " . $batch_updated . " registros actualizados\n";
                    
                    // Mostrar algunos IDs de ejemplo
                    $sample_ids = array_slice(array_keys($updates), 0, 3);
                    echo "   📝 Ejemplos: " . implode(', ', $sample_ids) . "\n";
                    
                } catch (Exception $e) {
                    $error_count += count($updates);
                    echo "   ❌ Error en lote: " . $e->getMessage() . "\n";
                }
                
                // Pausa entre lotes
                if ($batch < $total_batches - 1) {
                    echo "   ⏱️  Pausa de 2 segundos...\n";
                    sleep(2);
                }
            }
            
            // Resumen final
            echo "\n📊 RESUMEN FINAL\n";
            echo "================\n";
            echo "✅ Registros actualizados: " . number_format($updated_count) . "\n";
            echo "❌ Errores: " . number_format($error_count) . "\n";
            echo "📈 Tasa de éxito: " . (count($records_without_photo) > 0 ? round(($updated_count / count($records_without_photo)) * 100, 2) : 0) . "%\n";
            echo "🖼️ Foto asignada: " . $this->default_photo_url . "\n\n";
            
            if ($updated_count > 0) {
                echo "🎉 ¡PROCESO COMPLETADO EXITOSAMENTE!\n";
                echo "Se añadieron fotos de perfil a " . number_format($updated_count) . " despachos.\n";
                echo "Los cambios se reflejan inmediatamente en las búsquedas de Algolia.\n";
            }
            
        } catch (Exception $e) {
            echo "\n❌ ERROR: " . $e->getMessage() . "\n";
        }
    }
}

// Ejecutar solo si se llama directamente
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $updater = new SimplePhotoUpdater();
        $updater->execute();
    } catch (Exception $e) {
        echo "❌ ERROR FATAL: " . $e->getMessage() . "\n";
        exit(1);
    }
} 
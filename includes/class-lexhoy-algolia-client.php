<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para manejar la conexión con Algolia
 */
class LexhoyAlgoliaClient {
    private $app_id;
    private $admin_api_key;
    private $search_api_key;
    private $index_name;
    private $api_url;
    private $index;

    /**
     * Constructor
     */
    public function __construct($app_id, $admin_api_key, $search_api_key = '', $index_name = '', $api_url = '') {
        $this->app_id = $app_id;
        $this->admin_api_key = $admin_api_key;
        $this->search_api_key = $search_api_key;
        $this->index_name = $index_name;
        $this->api_url = $api_url ?: "https://{$app_id}.algolia.net";
    }

    /**
     * Logging personalizado - igual que en LexhoyDespachosCPT
     */
    private function custom_log($message) {
        // Usar una ruta absoluta más confiable
        $wp_content_dir = WP_CONTENT_DIR ?: dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-content';
        $log_file = $wp_content_dir . '/lexhoy-debug.log';
        
        // Crear el directorio si no existe
        $log_dir = dirname($log_file);
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
        
        // Intentar escribir el log, pero no fallar si no se puede
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Obtener el nombre del índice
     */
    public function get_index_name() {
        return $this->index_name;
    }

    /**
     * Verifica las credenciales de Algolia
     */
    public function verify_credentials() {
        try {
            if (empty($this->app_id) || empty($this->admin_api_key)) {
                throw new Exception('El Application ID y la Admin API Key son requeridos.');
            }

            $url = "{$this->api_url}/1/indexes";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'X-Algolia-API-Key: ' . $this->admin_api_key,
                'X-Algolia-Application-Id: ' . $this->app_id
            ));
            // Deshabilitar verificación SSL temporalmente para resolver timeout
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                throw new Exception('Error de conexión: ' . curl_error($ch));
            }
            
            curl_close($ch);

            if ($http_code === 200 || $http_code === 201) {
                error_log('Conexión exitosa con Algolia. Respuesta: ' . $response);
                return true;
            } else {
                $error_data = json_decode($response, true);
                $error_message = isset($error_data['message']) ? $error_data['message'] : 'Error desconocido';
                throw new Exception('Error de Algolia (HTTP ' . $http_code . '): ' . $error_message);
            }
        } catch (Exception $e) {
            error_log('Error al verificar credenciales de Algolia: ' . $e->getMessage());
            error_log('App ID usado: ' . $this->app_id);
            error_log('Admin API Key usado: ' . substr($this->admin_api_key, 0, 4) . '...');
            return false;
        }
    }

    /**
     * Realiza una búsqueda en Algolia
     */
    public function search($index_name, $query, $params = array()) {
        try {
            if (empty($this->app_id) || empty($this->search_api_key)) {
                throw new Exception('El Application ID y la Search API Key son requeridos para realizar búsquedas.');
            }

            $url = "{$this->api_url}/1/indexes/{$index_name}/query";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
                'query' => $query,
                'params' => http_build_query($params)
            )));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'X-Algolia-API-Key: ' . $this->search_api_key,
                'X-Algolia-Application-Id: ' . $this->app_id
            ));

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                throw new Exception('Error de conexión: ' . curl_error($ch));
            }
            
            curl_close($ch);

            if ($http_code === 200) {
                return json_decode($response, true);
            } else {
                $error_data = json_decode($response, true);
                $error_message = isset($error_data['message']) ? $error_data['message'] : 'Error desconocido';
                throw new Exception('Error de Algolia (HTTP ' . $http_code . '): ' . $error_message);
            }
        } catch (Exception $e) {
            error_log('Error al realizar búsqueda en Algolia: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener la URL de la API
     */
    public function get_api_url() {
        return $this->api_url;
    }

    /**
     * Obtener un objeto específico de Algolia
     */
    public function get_object($index_name, $object_id) {
        try {
            error_log('Intentando obtener objeto de Algolia - Index: ' . $index_name . ', ID: ' . $object_id);
            
            if (empty($this->app_id) || empty($this->admin_api_key)) {
                error_log('Error: Credenciales de Algolia no configuradas');
                throw new Exception('Credenciales de Algolia no configuradas');
            }

            $url = $this->get_api_url() . "/1/indexes/{$index_name}/{$object_id}";
            error_log('URL de búsqueda: ' . $url);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'X-Algolia-API-Key: ' . $this->admin_api_key,
                'X-Algolia-Application-Id: ' . $this->app_id
            ));

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                error_log('Error de cURL: ' . curl_error($ch));
                throw new Exception('Error de conexión: ' . curl_error($ch));
            }
            
            curl_close($ch);

            if ($http_code !== 200) {
                error_log('Error de Algolia (HTTP ' . $http_code . '): ' . $response);
                throw new Exception('Error de Algolia (HTTP ' . $http_code . ')');
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Error al decodificar respuesta JSON: ' . json_last_error_msg());
                throw new Exception('Error al procesar respuesta de Algolia');
            }

            error_log('Objeto obtenido exitosamente: ' . print_r($data, true));
            return $data;
        } catch (Exception $e) {
            error_log('Error en get_object: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Obtener todos los objetos de Algolia con paginación
     */
    public function browse_all() {
        if (!$this->verify_credentials()) {
            error_log('LexHoy Despachos - Error: Credenciales de Algolia no configuradas');
            return [
                'success' => false,
                'message' => 'Credenciales de Algolia no configuradas',
                'error' => 'missing_credentials'
            ];
        }

        $url = "https://{$this->app_id}-dsn.algolia.net/1/indexes/{$this->index_name}/browse";
        $headers = [
            'X-Algolia-API-Key: ' . $this->admin_api_key,
            'X-Algolia-Application-Id: ' . $this->app_id,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?hitsPerPage=1000');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        error_log('LexHoy Despachos - Respuesta de Algolia: ' . print_r($response, true));
        error_log('LexHoy Despachos - Código HTTP: ' . $http_code);

        if ($curl_error) {
            error_log('LexHoy Despachos - Error cURL: ' . $curl_error);
            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $curl_error,
                'error' => 'curl_error'
            ];
        }

        if ($http_code !== 200 && $http_code !== 201) {
            error_log('LexHoy Despachos - Error HTTP: ' . $http_code);
            return [
                'success' => false,
                'message' => 'Error de Algolia (HTTP ' . $http_code . ')',
                'error' => 'http_error'
            ];
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('LexHoy Despachos - Error al decodificar JSON: ' . json_last_error_msg());
            return [
                'success' => false,
                'message' => 'Error al procesar la respuesta de Algolia',
                'error' => 'json_error'
            ];
        }

        if (!isset($data['hits']) || !is_array($data['hits'])) {
            error_log('LexHoy Despachos - Formato de respuesta inválido: ' . print_r($data, true));
            return [
                'success' => false,
                'message' => 'Formato de respuesta de Algolia inválido',
                'error' => 'invalid_format'
            ];
        }

        if (empty($data['hits'])) {
            error_log('LexHoy Despachos - No se encontraron registros en Algolia');
            return [
                'success' => false,
                'message' => 'No se encontraron registros en Algolia',
                'error' => 'no_records'
            ];
        }

        $valid_records = [];
        foreach ($data['hits'] as $hit) {
            $nombre = $hit['nombre'] ?? '';
            $localidad = $hit['localidad'] ?? '';
            $provincia = $hit['provincia'] ?? '';
            $has_minimal_data = !empty($nombre) || !empty($localidad) || !empty($provincia);
            $is_generated = strpos($hit['objectID'], '_dashboard_generated_id') !== false;
            if (!$is_generated && $has_minimal_data) {
                $valid_records[] = $hit;
            }
        }

        return [
            'success' => true,
            'hits' => $valid_records,
            'total_records' => $data['nbHits'] ?? count($valid_records)
        ];
    }

    /**
     * Obtener todos los objetos de Algolia usando el método browse con cursor
     * Este método puede obtener todos los registros sin el límite de 1000
     */
    public function browse_all_with_cursor() {
        try {
            $this->custom_log("=== ALGOLIA browse_all_with_cursor iniciado ===");
            
            if (!$this->verify_credentials()) {
                $this->custom_log('ALGOLIA FATAL: Credenciales no válidas en browse_all_with_cursor');
                return [
                    'success' => false,
                    'message' => 'Credenciales de Algolia no configuradas',
                    'error' => 'missing_credentials'
                ];
            }

            $all_hits = [];
            $cursor = null;
            $page = 0;
            $max_pages = 100; // Límite de seguridad para evitar bucles infinitos

            do {
                $page++;
                $this->custom_log("ALGOLIA: Obteniendo página {$page} con cursor: " . ($cursor ?: 'null'));
                
                $url = "https://{$this->app_id}-dsn.algolia.net/1/indexes/{$this->index_name}/browse";
                $headers = [
                    'X-Algolia-API-Key: ' . $this->admin_api_key,
                    'X-Algolia-Application-Id: ' . $this->app_id,
                    'Content-Type: application/json'
                ];

                // Construir parámetros de la URL
                $params = ['hitsPerPage' => 1000];
                if ($cursor) {
                    $params['cursor'] = $cursor;
                }
                
                $full_url = $url . '?' . http_build_query($params);
                
                $this->custom_log('ALGOLIA URL: ' . $full_url);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $full_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                // Deshabilitar verificación SSL temporalmente para resolver timeout
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                $this->custom_log('ALGOLIA HTTP Code: ' . $http_code);
                $this->custom_log('ALGOLIA cURL Error: ' . ($curl_error ?: 'ninguno'));
                $this->custom_log('ALGOLIA Response (primeros 500 chars): ' . substr($response, 0, 500));

                if ($curl_error) {
                    error_log('LexHoy: Error cURL en browse_all_with_cursor: ' . $curl_error);
                    return [
                        'success' => false,
                        'message' => 'Error de conexión: ' . $curl_error,
                        'error' => 'curl_error'
                    ];
                }

                if ($http_code !== 200) {
                    error_log('LexHoy: HTTP Error en browse_all_with_cursor: ' . $http_code . ' - ' . $response);
                    return [
                        'success' => false,
                        'message' => 'Error de Algolia (HTTP ' . $http_code . ')',
                        'error' => 'http_error'
                    ];
                }

                $data = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log('LexHoy: JSON decode error en browse_all_with_cursor: ' . json_last_error_msg());
                    return [
                        'success' => false,
                        'message' => 'Error al procesar la respuesta de Algolia',
                        'error' => 'json_error'
                    ];
                }

                if (!isset($data['hits']) || !is_array($data['hits'])) {
                    error_log('LexHoy: Formato de respuesta inválido en browse_all_with_cursor: ' . print_r($data, true));
                    return [
                        'success' => false,
                        'message' => 'Formato de respuesta de Algolia inválido',
                        'error' => 'invalid_format'
                    ];
                }

                $hits_count = count($data['hits']);
                $this->custom_log("ALGOLIA: Página {$page} - {$hits_count} registros obtenidos");
                
                // Agregar los hits de esta página al total
                $all_hits = array_merge($all_hits, $data['hits']);
                
                // Obtener el cursor para la siguiente página
                $cursor = isset($data['cursor']) ? $data['cursor'] : null;
                
                // Verificar si hay más páginas
                $has_more = !empty($cursor);
                
                $this->custom_log("ALGOLIA: Total acumulado: " . count($all_hits) . " registros");
                $this->custom_log("ALGOLIA: Hay más páginas: " . ($has_more ? 'SI' : 'NO'));
                
                // Pausa pequeña para no sobrecargar la API
                if ($has_more) {
                    usleep(100000); // 100ms
                }

            } while ($has_more && $page < $max_pages);

            if ($page >= $max_pages) {
                $this->custom_log("ALGOLIA WARNING: Se alcanzó el límite máximo de páginas ({$max_pages})");
            }

            $total_records = count($all_hits);
            $this->custom_log("ALGOLIA: browse_all_with_cursor completado - Total: {$total_records} registros en {$page} páginas");

            if (empty($all_hits)) {
                return [
                    'success' => false,
                    'message' => 'No se encontraron registros en Algolia',
                    'error' => 'no_records'
                ];
            }

            $valid_records = [];
            foreach ($all_hits as $hit) {
                $nombre = $hit['nombre'] ?? '';
                $localidad = $hit['localidad'] ?? '';
                $provincia = $hit['provincia'] ?? '';
                $has_minimal_data = !empty($nombre) || !empty($localidad) || !empty($provincia);
                $is_generated = strpos($hit['objectID'], '_dashboard_generated_id') !== false;
                if (!$is_generated && $has_minimal_data) {
                    $valid_records[] = $hit;
                }
            }

            return [
                'success' => true,
                'hits' => $valid_records,
                'total_records' => $total_records,
                'pages_processed' => $page
            ];

        } catch (Exception $e) {
            error_log('LexHoy: Exception en browse_all_with_cursor: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
                'error' => 'exception'
            ];
        }
    }

    /**
     * Obtener TODOS los objetos de Algolia sin filtrar
     * Este método devuelve todos los registros tal como están en Algolia
     */
    public function browse_all_unfiltered() {
        try {
            $this->custom_log("=== ALGOLIA browse_all_unfiltered iniciado ===");
            
            if (!$this->verify_credentials()) {
                $this->custom_log('ALGOLIA FATAL: Credenciales no válidas en browse_all_unfiltered');
                return [
                    'success' => false,
                    'message' => 'Credenciales de Algolia no configuradas',
                    'error' => 'missing_credentials'
                ];
            }

            $all_hits = [];
            $cursor = null;
            $page = 0;
            $max_pages = 100; // Límite de seguridad para evitar bucles infinitos

            do {
                $page++;
                $this->custom_log("ALGOLIA: Obteniendo página {$page} con cursor: " . ($cursor ?: 'null'));
                
                $url = "https://{$this->app_id}-dsn.algolia.net/1/indexes/{$this->index_name}/browse";
                $headers = [
                    'X-Algolia-API-Key: ' . $this->admin_api_key,
                    'X-Algolia-Application-Id: ' . $this->app_id,
                    'Content-Type: application/json'
                ];

                // Construir parámetros de la URL
                $params = ['hitsPerPage' => 1000];
                if ($cursor) {
                    $params['cursor'] = $cursor;
                }
                
                $full_url = $url . '?' . http_build_query($params);
                
                $this->custom_log('ALGOLIA URL: ' . $full_url);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $full_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                // Deshabilitar verificación SSL temporalmente para resolver timeout
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                $this->custom_log('ALGOLIA HTTP Code: ' . $http_code);
                $this->custom_log('ALGOLIA cURL Error: ' . ($curl_error ?: 'ninguno'));
                $this->custom_log('ALGOLIA Response (primeros 500 chars): ' . substr($response, 0, 500));

                if ($curl_error) {
                    error_log('LexHoy: Error cURL en browse_all_unfiltered: ' . $curl_error);
                    return [
                        'success' => false,
                        'message' => 'Error de conexión: ' . $curl_error,
                        'error' => 'curl_error'
                    ];
                }

                if ($http_code !== 200) {
                    error_log('LexHoy: HTTP Error en browse_all_unfiltered: ' . $http_code . ' - ' . $response);
                    return [
                        'success' => false,
                        'message' => 'Error de Algolia (HTTP ' . $http_code . ')',
                        'error' => 'http_error'
                    ];
                }

                $data = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log('LexHoy: JSON decode error en browse_all_unfiltered: ' . json_last_error_msg());
                    return [
                        'success' => false,
                        'message' => 'Error al procesar la respuesta de Algolia',
                        'error' => 'json_error'
                    ];
                }

                if (!isset($data['hits']) || !is_array($data['hits'])) {
                    error_log('LexHoy: Formato de respuesta inválido en browse_all_unfiltered: ' . print_r($data, true));
                    return [
                        'success' => false,
                        'message' => 'Formato de respuesta de Algolia inválido',
                        'error' => 'invalid_format'
                    ];
                }

                $hits_count = count($data['hits']);
                $this->custom_log("ALGOLIA: Página {$page} - {$hits_count} registros obtenidos");
                
                // Agregar los hits de esta página al total SIN FILTRAR
                $all_hits = array_merge($all_hits, $data['hits']);
                
                // Obtener el cursor para la siguiente página
                $cursor = isset($data['cursor']) ? $data['cursor'] : null;
                
                // Verificar si hay más páginas
                $has_more = !empty($cursor);
                
                $this->custom_log("ALGOLIA: Total acumulado: " . count($all_hits) . " registros");
                $this->custom_log("ALGOLIA: Hay más páginas: " . ($has_more ? 'SI' : 'NO'));
                
                // Pausa pequeña para no sobrecargar la API
                if ($has_more) {
                    usleep(100000); // 100ms
                }

            } while ($has_more && $page < $max_pages);

            if ($page >= $max_pages) {
                $this->custom_log("ALGOLIA WARNING: Se alcanzó el límite máximo de páginas ({$max_pages})");
            }

            $total_records = count($all_hits);
            $this->custom_log("ALGOLIA: browse_all_unfiltered completado - Total: {$total_records} registros en {$page} páginas");

            if (empty($all_hits)) {
                return [
                    'success' => false,
                    'message' => 'No se encontraron registros en Algolia',
                    'error' => 'no_records'
                ];
            }

            // IMPORTANTE: NO FILTRAR - devolver todos los registros tal como están
            return [
                'success' => true,
                'hits' => $all_hits, // TODOS los registros sin filtrar
                'total_records' => $total_records,
                'pages_processed' => $page
            ];

        } catch (Exception $e) {
            error_log('LexHoy: Exception en browse_all_unfiltered: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
                'error' => 'exception'
            ];
        }
    }

    /**
     * Obtener registros paginados de Algolia (optimizado para importación por bloques)
     */
    public function get_paginated_records($page = 0, $hits_per_page = 1000) {
        try {
            $this->custom_log("ALGOLIA: get_paginated_records - Página {$page}, {$hits_per_page} registros por página");
            
            $url = "https://{$this->app_id}-dsn.algolia.net/1/indexes/{$this->index_name}/browse";
            $headers = [
                'X-Algolia-API-Key: ' . $this->admin_api_key,
                'X-Algolia-Application-Id: ' . $this->app_id,
                'Content-Type: application/json'
            ];

            // Si es la primera página, obtener el cursor inicial
            $cursor = null;
            if ($page > 0) {
                // Para páginas posteriores, necesitamos obtener el cursor correspondiente
                // Esto es una limitación de la API browse de Algolia
                // Tendremos que iterar hasta la página deseada
                $current_page = 0;
                
                while ($current_page < $page) {
                    $params = ['hitsPerPage' => $hits_per_page];
                    if ($cursor) {
                        $params['cursor'] = $cursor;
                    }
                    
                    $full_url = $url . '?' . http_build_query($params);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $full_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($http_code !== 200) {
                        throw new Exception('Error HTTP ' . $http_code . ' al navegar a página ' . $current_page);
                    }

                    $data = json_decode($response, true);
                    if (!isset($data['cursor'])) {
                        // No hay más páginas
                        break;
                    }
                    
                    $cursor = $data['cursor'];
                    $current_page++;
                    
                    // Pausa para no sobrecargar la API
                    usleep(50000); // 50ms
                }
            }

            // Ahora obtener la página específica
            $params = ['hitsPerPage' => $hits_per_page];
            if ($cursor) {
                $params['cursor'] = $cursor;
            }
            
            $full_url = $url . '?' . http_build_query($params);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $full_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                throw new Exception('Error cURL: ' . $curl_error);
            }

            if ($http_code !== 200) {
                throw new Exception('Error HTTP ' . $http_code);
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error JSON: ' . json_last_error_msg());
            }

            if (!isset($data['hits']) || !is_array($data['hits'])) {
                throw new Exception('Formato de respuesta inválido');
            }

            // Estimar el total usando el número de página actual y registros obtenidos
            $hits_count = count($data['hits']);
            $estimated_total = ($page * $hits_per_page) + $hits_count;
            
            // Si hay cursor, significa que hay más páginas
            if (isset($data['cursor']) && !empty($data['cursor'])) {
                $estimated_total += $hits_per_page; // Estimación conservadora
            }

            $this->custom_log("ALGOLIA: Página {$page} obtenida - {$hits_count} registros, total estimado: {$estimated_total}");

            return [
                'success' => true,
                'hits' => $data['hits'],
                'total_hits' => $estimated_total,
                'current_page' => $page,
                'has_more' => isset($data['cursor']) && !empty($data['cursor'])
            ];

        } catch (Exception $e) {
            $this->custom_log("ALGOLIA ERROR en get_paginated_records: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener registros paginados: ' . $e->getMessage(),
                'error' => 'exception'
            ];
        }
    }

    /**
     * Obtener el App ID
     */
    public function get_app_id() {
        return $this->app_id;
    }

    /**
     * Obtener la Admin API Key
     */
    public function get_admin_api_key() {
        return $this->admin_api_key;
    }

    /**
     * Guardar un objeto en Algolia
     */
    public function save_object($index_name, $record) {
        try {
            error_log('Intentando guardar objeto en Algolia - Index: ' . $index_name);
            error_log('Datos a guardar: ' . print_r($record, true));
            
            if (empty($this->app_id) || empty($this->admin_api_key)) {
                error_log('ERROR: Credenciales de Algolia no configuradas');
                throw new Exception('Credenciales de Algolia no configuradas');
            }

            $url = $this->get_api_url() . "/1/indexes/{$index_name}";
            error_log('URL de guardado: ' . $url);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($record));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'X-Algolia-API-Key: ' . $this->admin_api_key,
                'X-Algolia-Application-Id: ' . $this->app_id
            ));

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                error_log('Error de cURL: ' . curl_error($ch));
                throw new Exception('Error de conexión: ' . curl_error($ch));
            }
            
            curl_close($ch);

            if ($http_code !== 200 && $http_code !== 201) {
                error_log('Error de Algolia (HTTP ' . $http_code . '): ' . $response);
                throw new Exception('Error de Algolia (HTTP ' . $http_code . ')');
            }

            error_log('Objeto guardado exitosamente');
            return true;

        } catch (Exception $e) {
            error_log('Error en save_object: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Eliminar un objeto de Algolia
     */
    public function delete_object($index_name, $object_id) {
        try {
            error_log('Intentando eliminar objeto de Algolia - Index: ' . $index_name . ', ID: ' . $object_id);
            
            if (empty($this->app_id) || empty($this->admin_api_key)) {
                error_log('ERROR: Credenciales de Algolia no configuradas');
                throw new Exception('Credenciales de Algolia no configuradas');
            }

            $url = $this->get_api_url() . "/1/indexes/{$index_name}/{$object_id}";
            error_log('URL de eliminación: ' . $url);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'X-Algolia-API-Key: ' . $this->admin_api_key,
                'X-Algolia-Application-Id: ' . $this->app_id
            ));

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                error_log('Error de cURL: ' . curl_error($ch));
                throw new Exception('Error de conexión: ' . curl_error($ch));
            }
            
            curl_close($ch);

            if ($http_code !== 200) {
                error_log('Error de Algolia (HTTP ' . $http_code . '): ' . $response);
                throw new Exception('Error de Algolia (HTTP ' . $http_code . ')');
            }

            error_log('Objeto eliminado exitosamente');
            return true;

        } catch (Exception $e) {
            error_log('Error en delete_object: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Actualizar parcialmente un objeto en Algolia
     */
    public function partial_update_object($index_name, $object_id, $partial_data) {
        try {
            $this->custom_log('Intentando actualización parcial de objeto en Algolia - Index: ' . $index_name . ', ID: ' . $object_id);
            $this->custom_log('Datos parciales: ' . json_encode($partial_data));
            
            if (empty($this->app_id) || empty($this->admin_api_key)) {
                throw new Exception('Credenciales de Algolia no configuradas');
            }

            $url = $this->get_api_url() . "/1/indexes/{$index_name}/{$object_id}/partial";
            $this->custom_log('URL de actualización parcial: ' . $url);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($partial_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'X-Algolia-API-Key: ' . $this->admin_api_key,
                'X-Algolia-Application-Id: ' . $this->app_id
            ));

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                throw new Exception('Error de conexión: ' . curl_error($ch));
            }
            
            curl_close($ch);

            if ($http_code !== 200 && $http_code !== 201) {
                $this->custom_log('Error de Algolia (HTTP ' . $http_code . '): ' . $response);
                throw new Exception('Error de Algolia (HTTP ' . $http_code . ')');
            }

            $this->custom_log('Objeto actualizado parcialmente exitosamente');
            return true;

        } catch (Exception $e) {
            $this->custom_log('Error en partial_update_object: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar múltiples objetos en lote (batch update)
     */
    public function batch_partial_update($index_name, $updates) {
        try {
            $this->custom_log('Iniciando actualización en lote - Index: ' . $index_name . ', ' . count($updates) . ' objetos');
            
            if (empty($this->app_id) || empty($this->admin_api_key)) {
                throw new Exception('Credenciales de Algolia no configuradas');
            }

            // Preparar las operaciones de actualización en lote
            $requests = array();
            foreach ($updates as $object_id => $partial_data) {
                $requests[] = array(
                    'action' => 'partialUpdateObject',
                    'objectID' => $object_id,
                    'body' => $partial_data
                );
            }

            $batch_data = array('requests' => $requests);
            
            $url = $this->get_api_url() . "/1/indexes/{$index_name}/batch";
            $this->custom_log('URL de actualización en lote: ' . $url);
            $this->custom_log('Enviando ' . count($requests) . ' actualizaciones');
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($batch_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'X-Algolia-API-Key: ' . $this->admin_api_key,
                'X-Algolia-Application-Id: ' . $this->app_id
            ));
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Mayor timeout para operaciones batch

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                throw new Exception('Error de conexión: ' . curl_error($ch));
            }
            
            curl_close($ch);

            if ($http_code !== 200 && $http_code !== 201) {
                $this->custom_log('Error de Algolia (HTTP ' . $http_code . '): ' . $response);
                throw new Exception('Error de Algolia (HTTP ' . $http_code . ')');
            }

            $result = json_decode($response, true);
            if (isset($result['taskID'])) {
                $this->custom_log('Actualización en lote exitosa, taskID: ' . $result['taskID']);
            }

            return $result;

        } catch (Exception $e) {
            $this->custom_log('Error en batch_partial_update: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener registros de Algolia por página específica usando search API
     */
    public function browse_page($page = 0, $hits_per_page = 200) {
        try {
            $this->custom_log("=== ALGOLIA browse_page iniciado - página {$page}, hits por página: {$hits_per_page} ===");
            $this->custom_log("ALGOLIA App ID: {$this->app_id}");
            $this->custom_log("ALGOLIA Index Name: {$this->index_name}");
            
            if (!$this->verify_credentials()) {
                $this->custom_log('ALGOLIA FATAL: Credenciales no válidas en browse_page');
                return [
                    'success' => false,
                    'message' => 'Credenciales de Algolia no configuradas',
                    'error' => 'missing_credentials'
                ];
            }

            // Usar la API de search POST con paginación por página
            $url = "https://{$this->app_id}-dsn.algolia.net/1/indexes/{$this->index_name}/query";
            $headers = [
                'X-Algolia-API-Key: ' . $this->admin_api_key,
                'X-Algolia-Application-Id: ' . $this->app_id,
                'Content-Type: application/json'
            ];

            $post_data = [
                'query' => '',
                'hitsPerPage' => $hits_per_page,
                'page' => $page
            ];

            $this->custom_log('ALGOLIA URL corregida: ' . $url);
            $this->custom_log('ALGOLIA Datos POST: ' . json_encode($post_data));
            $this->custom_log('ALGOLIA Headers: ' . print_r($headers, true));

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Reducido de 30 a 15 segundos
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Timeout de conexión de 10 segundos
            curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 300); // Cache DNS por 5 minutos
            curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1); // Mantener conexión activa
            curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 60); // Mantener conexión por 60 segundos
            curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 60); // Intervalo de keepalive
            // Deshabilitar verificación SSL temporalmente para resolver timeout
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            $this->custom_log('ALGOLIA HTTP Code: ' . $http_code);
            $this->custom_log('ALGOLIA cURL Error: ' . ($curl_error ?: 'ninguno'));
            $this->custom_log('ALGOLIA Response (primeros 500 chars): ' . substr($response, 0, 500));

            if ($curl_error) {
                error_log('LexHoy: Error cURL en browse_page: ' . $curl_error);
                return [
                    'success' => false,
                    'message' => 'Error de conexión: ' . $curl_error,
                    'error' => 'curl_error'
                ];
            }

            if ($http_code !== 200) {
                error_log('LexHoy: HTTP Error en browse_page: ' . $http_code . ' - ' . $response);
                return [
                    'success' => false,
                    'message' => 'Error de Algolia (HTTP ' . $http_code . ')',
                    'error' => 'http_error'
                ];
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('LexHoy: JSON decode error en browse_page: ' . json_last_error_msg());
                return [
                    'success' => false,
                    'message' => 'Error al procesar la respuesta de Algolia',
                    'error' => 'json_error'
                ];
            }

            if (!isset($data['hits']) || !is_array($data['hits'])) {
                error_log('LexHoy: Formato de respuesta inválido en browse_page: ' . print_r($data, true));
                return [
                    'success' => false,
                    'message' => 'Formato de respuesta de Algolia inválido',
                    'error' => 'invalid_format'
                ];
            }

            error_log('LexHoy: browse_page exitoso - ' . count($data['hits']) . ' registros obtenidos');

            $valid_records = [];
            foreach ($data['hits'] as $hit) {
                $nombre = $hit['nombre'] ?? '';
                $localidad = $hit['localidad'] ?? '';
                $provincia = $hit['provincia'] ?? '';
                $has_minimal_data = !empty($nombre) || !empty($localidad) || !empty($provincia);
                $is_generated = strpos($hit['objectID'], '_dashboard_generated_id') !== false;
                if (!$is_generated && $has_minimal_data) {
                    $valid_records[] = $hit;
                }
            }

            return [
                'success' => true,
                'hits' => $valid_records,
                'total_records' => $data['nbHits'] ?? count($valid_records),
                'page' => $data['page'] ?? $page,
                'nbPages' => $data['nbPages'] ?? 1
            ];

        } catch (Exception $e) {
            error_log('LexHoy: Exception en browse_page: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
                'error' => 'exception'
            ];
        }
    }

    /**
     * Obtener solo el conteo total de registros sin cargar datos
     */
    public function get_total_count() {
        try {
            $this->custom_log('=== ALGOLIA get_total_count iniciado ===');
            
            if (!$this->verify_credentials()) {
                $this->custom_log('ALGOLIA: Credenciales no válidas en get_total_count');
                return 0;
            }

            $url = "https://{$this->app_id}-dsn.algolia.net/1/indexes/{$this->index_name}/query";
            $this->custom_log('ALGOLIA URL para conteo: ' . $url);
            
            $headers = [
                'X-Algolia-API-Key: ' . $this->admin_api_key,
                'X-Algolia-Application-Id: ' . $this->app_id,
                'Content-Type: application/json'
            ];

            $post_data = [
                'query' => '',
                'hitsPerPage' => 1,
                'attributesToRetrieve' => ['objectID']
            ];

            $this->custom_log('ALGOLIA Datos POST: ' . json_encode($post_data));

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Reducido a 10 segundos
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Timeout de conexión de 5 segundos
            // Deshabilitar verificación SSL temporalmente para resolver timeout
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            $this->custom_log('ALGOLIA HTTP Code: ' . $http_code);
            $this->custom_log('ALGOLIA cURL Error: ' . ($curl_error ?: 'ninguno'));
            $this->custom_log('ALGOLIA Response: ' . substr($response, 0, 500));

            if ($curl_error) {
                $this->custom_log('ALGOLIA: Error cURL en get_total_count: ' . $curl_error);
                return 0;
            }

            if ($http_code === 200) {
                $data = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($data['nbHits'])) {
                    $total = intval($data['nbHits']);
                    $this->custom_log('ALGOLIA: Total encontrado: ' . $total);
                    return $total;
                } else {
                    $this->custom_log('ALGOLIA: Error decodificando JSON o nbHits no encontrado: ' . json_last_error_msg());
                    $this->custom_log('ALGOLIA: Data recibida: ' . print_r($data, true));
                }
            } else {
                $this->custom_log('ALGOLIA: HTTP Error en get_total_count: ' . $http_code . ' - ' . $response);
            }

            return 0;

        } catch (Exception $e) {
            $this->custom_log('ALGOLIA: Exception en get_total_count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Función alternativa para obtener el conteo total usando la primera página
     * Esta función es más confiable cuando la API de conteo falla
     */
    public function get_total_count_simple() {
        try {
            $this->custom_log('=== ALGOLIA get_total_count_simple iniciado ===');
            
            // Usar browse_page con una página pequeña para obtener el conteo
            $result = $this->browse_page(0, 1);
            
            if ($result['success'] && isset($result['total_records'])) {
                $total = intval($result['total_records']);
                $this->custom_log('ALGOLIA: Total encontrado (simple): ' . $total);
                return $total;
            } else {
                $this->custom_log('ALGOLIA: Error en get_total_count_simple: ' . ($result['message'] ?? 'Error desconocido'));
                return 0;
            }
            
        } catch (Exception $e) {
            $this->custom_log('ALGOLIA: Exception en get_total_count_simple: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener registros de Algolia por página específica SIN FILTRAR
     */
    public function browse_page_unfiltered($page = 0, $hits_per_page = 200) {
        try {
            $this->custom_log("=== ALGOLIA browse_page_unfiltered iniciado - página {$page}, hits por página: {$hits_per_page} ===");
            $this->custom_log("ALGOLIA App ID: {$this->app_id}");
            $this->custom_log("ALGOLIA Index Name: {$this->index_name}");
            
            if (!$this->verify_credentials()) {
                $this->custom_log('ALGOLIA FATAL: Credenciales no válidas en browse_page_unfiltered');
                return [
                    'success' => false,
                    'message' => 'Credenciales de Algolia no configuradas',
                    'error' => 'missing_credentials'
                ];
            }

            // Usar la API de search POST con paginación por página
            $url = "https://{$this->app_id}-dsn.algolia.net/1/indexes/{$this->index_name}/query";
            $headers = [
                'X-Algolia-API-Key: ' . $this->admin_api_key,
                'X-Algolia-Application-Id: ' . $this->app_id,
                'Content-Type: application/json'
            ];

            $post_data = [
                'query' => '',
                'hitsPerPage' => $hits_per_page,
                'page' => $page
            ];

            $this->custom_log('ALGOLIA URL corregida: ' . $url);
            $this->custom_log('ALGOLIA Datos POST: ' . json_encode($post_data));
            $this->custom_log('ALGOLIA Headers: ' . print_r($headers, true));

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Reducido de 30 a 15 segundos
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Timeout de conexión de 10 segundos
            curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 300); // Cache DNS por 5 minutos
            curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1); // Mantener conexión activa
            curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 60); // Mantener conexión por 60 segundos
            curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 60); // Intervalo de keepalive
            // Deshabilitar verificación SSL temporalmente para resolver timeout
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            $this->custom_log('ALGOLIA HTTP Code: ' . $http_code);
            $this->custom_log('ALGOLIA cURL Error: ' . ($curl_error ?: 'ninguno'));
            $this->custom_log('ALGOLIA Response (primeros 500 chars): ' . substr($response, 0, 500));

            if ($curl_error) {
                error_log('LexHoy: Error cURL en browse_page_unfiltered: ' . $curl_error);
                return [
                    'success' => false,
                    'message' => 'Error de conexión: ' . $curl_error,
                    'error' => 'curl_error'
                ];
            }

            if ($http_code !== 200) {
                error_log('LexHoy: HTTP Error en browse_page_unfiltered: ' . $http_code . ' - ' . $response);
                return [
                    'success' => false,
                    'message' => 'Error de Algolia (HTTP ' . $http_code . ')',
                    'error' => 'http_error'
                ];
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('LexHoy: JSON decode error en browse_page_unfiltered: ' . json_last_error_msg());
                return [
                    'success' => false,
                    'message' => 'Error al procesar la respuesta de Algolia',
                    'error' => 'json_error'
                ];
            }

            if (!isset($data['hits']) || !is_array($data['hits'])) {
                error_log('LexHoy: Formato de respuesta inválido en browse_page_unfiltered: ' . print_r($data, true));
                return [
                    'success' => false,
                    'message' => 'Formato de respuesta de Algolia inválido',
                    'error' => 'invalid_format'
                ];
            }

            error_log('LexHoy: browse_page_unfiltered exitoso - ' . count($data['hits']) . ' registros obtenidos');

            // IMPORTANTE: NO FILTRAR - devolver todos los registros tal como están
            return [
                'success' => true,
                'hits' => $data['hits'], // TODOS los registros sin filtrar
                'total_records' => $data['nbHits'] ?? count($data['hits']),
                'page' => $data['page'] ?? $page,
                'nbPages' => $data['nbPages'] ?? 1
            ];

        } catch (Exception $e) {
            error_log('LexHoy: Exception en browse_page_unfiltered: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
                'error' => 'exception'
            ];
        }
    }

    /**
     * Obtener página de registros SIN filtros para normalización
     * Retorna TODOS los registros tal como están en Algolia
     */
    public function browse_page_for_normalization($page = 0, $hits_per_page = 200) {
        try {
            $this->custom_log("=== ALGOLIA browse_page_for_normalization iniciado - página {$page}, hits por página: {$hits_per_page} ===");
            
            if (!$this->verify_credentials()) {
                $this->custom_log('ALGOLIA FATAL: Credenciales no válidas');
                return [
                    'success' => false,
                    'message' => 'Credenciales de Algolia no configuradas',
                    'error' => 'missing_credentials'
                ];
            }

            $url = "https://{$this->app_id}-dsn.algolia.net/1/indexes/{$this->index_name}/query";
            $headers = [
                'X-Algolia-API-Key: ' . $this->admin_api_key,
                'X-Algolia-Application-Id: ' . $this->app_id,
                'Content-Type: application/json'
            ];

            $post_data = [
                'query' => '',
                'hitsPerPage' => $hits_per_page,
                'page' => $page
            ];

            $this->custom_log('ALGOLIA URL: ' . $url);
            $this->custom_log('ALGOLIA Datos POST: ' . json_encode($post_data));

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            $this->custom_log('ALGOLIA HTTP Code: ' . $http_code);
            $this->custom_log('ALGOLIA Response (primeros 300 chars): ' . substr($response, 0, 300));

            if ($curl_error) {
                return [
                    'success' => false,
                    'message' => 'Error de conexión: ' . $curl_error,
                    'error' => 'curl_error'
                ];
            }

            if ($http_code !== 200) {
                return [
                    'success' => false,
                    'message' => 'Error de Algolia (HTTP ' . $http_code . ')',
                    'error' => 'http_error'
                ];
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'message' => 'Error al procesar la respuesta de Algolia',
                    'error' => 'json_error'
                ];
            }

            if (!isset($data['hits']) || !is_array($data['hits'])) {
                return [
                    'success' => false,
                    'message' => 'Formato de respuesta de Algolia inválido',
                    'error' => 'invalid_format'
                ];
            }

            $this->custom_log('browse_page_for_normalization exitoso - ' . count($data['hits']) . ' registros obtenidos SIN filtros');

            // SIN FILTROS - devolver todos los registros tal como están
            return [
                'success' => true,
                'hits' => $data['hits'], // TODOS los registros
                'total_records' => $data['nbHits'] ?? count($data['hits']),
                'page' => $data['page'] ?? $page,
                'nbPages' => $data['nbPages'] ?? 1
            ];

        } catch (Exception $e) {
            error_log('LexHoy: Exception en browse_page_for_normalization: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
                'error' => 'exception'
            ];
        }
    }
} 
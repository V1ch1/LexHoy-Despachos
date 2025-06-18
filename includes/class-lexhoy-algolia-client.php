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

        return [
            'success' => true,
            'hits' => $data['hits'],
            'total_records' => $data['nbHits'] ?? count($data['hits'])
        ];
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
} 
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

            if ($http_code === 200) {
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
} 
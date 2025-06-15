<?php
class LexHoy_Despachos_Admin {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . '../assets/css/lexhoy-despachos-admin.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . '../assets/js/lexhoy-despachos-admin.js', array('jquery'), $this->version, false);
    }

    public function add_plugin_admin_menu() {
        add_menu_page(
            'LexHoy Despachos',
            'LexHoy Despachos',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_admin_page'),
            'dashicons-admin-generic',
            81
        );
    }

    public function display_plugin_admin_page() {
        include_once('partials/lexhoy-despachos-admin-display.php');
    }

    private function log_message($message) {
        $log_file = plugin_dir_path(dirname(__FILE__)) . 'debug.log';
        $timestamp = date('[Y-m-d H:i:s] ');
        file_put_contents($log_file, $timestamp . $message . "\n", FILE_APPEND);
    }

    public function sync_with_algolia() {
        $this->log_message('=== INICIO SINCRONIZACIÓN ALGOLIA ===');
        
        try {
            // Verificar configuración
            $app_id = get_option('lexhoy_despachos_algolia_app_id');
            $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
            $index_name = get_option('lexhoy_despachos_algolia_index_name');

            $this->log_message('Configuración: App ID: ' . $app_id . ', Index: ' . $index_name);

            if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
                throw new Exception('Configuración incompleta de Algolia');
            }

            // Inicializar cliente Algolia
            $client = \Algolia\AlgoliaSearch\SearchClient::create($app_id, $admin_api_key);
            $index = $client->initIndex($index_name);

            // Obtener posts
            $args = array(
                'post_type' => 'post',
                'posts_per_page' => 1,
                'post_status' => 'publish'
            );

            $query = new WP_Query($args);
            $this->log_message('Posts encontrados: ' . $query->found_posts);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $this->log_message('Procesando post ID: ' . $post_id);
                    
                    // Obtener meta datos
                    $meta_data = get_post_meta($post_id);
                    $this->log_message('Meta datos encontrados: ' . print_r($meta_data, true));

                    // Preparar datos para Algolia
                    $record = array(
                        'objectID' => $post_id,
                        'title' => get_the_title(),
                        'content' => get_the_content(),
                        'excerpt' => get_the_excerpt(),
                        'date' => get_the_date('c'),
                        'modified' => get_the_modified_date('c'),
                        'url' => get_permalink(),
                        'meta_data' => $meta_data
                    );

                    $this->log_message('Datos preparados para Algolia: ' . print_r($record, true));

                    // Guardar en Algolia
                    $index->saveObject($record);
                    $this->log_message('Post guardado en Algolia exitosamente');
                }
            } else {
                $this->log_message('No se encontraron posts para sincronizar');
            }

            wp_reset_postdata();
            $this->log_message('=== FINALIZACIÓN SINCRONIZACIÓN ALGOLIA ===');
            return true;

        } catch (Exception $e) {
            $this->log_message('Error en sincronización: ' . $e->getMessage());
            $this->log_message('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
} 
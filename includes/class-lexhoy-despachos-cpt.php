<?php
/**
 * Custom Post Type para Despachos - Versión Limpia
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class LexhoyDespachosCPT {
    
    private $algolia_client;
    private $import_in_progress = false; // Variable para controlar si hay importación en progreso

    /**
     * Constructor
     */
    public function __construct() {
        // Constructor inicializado
        
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'), 1, 1);
        add_action('save_post', array($this, 'test_save_post_hook'), 1, 1);
        
        // Redirecciones para URLs limpias
        add_action('template_redirect', array($this, 'handle_clean_urls'));
        
        // Filtrar permalinks para URLs limpias de despachos
        add_filter('post_type_link', array($this, 'filter_post_type_link'), 10, 2);
        
        // Acciones para Algolia
        add_action('save_post_despacho', array($this, 'sync_to_algolia'), 20, 3);
        add_action('before_delete_post', array($this, 'delete_from_algolia'));
        add_action('wp_trash_post', array($this, 'delete_from_algolia'));
        add_action('trash_despacho', array($this, 'delete_from_algolia'));
        add_action('untrash_post', array($this, 'restore_from_trash'));
        
        // Acción para sincronización programada
        add_action('lexhoy_despachos_sync_from_algolia', array($this, 'sync_all_from_algolia'));

        // NUEVO: Handlers AJAX para importación masiva
        add_action('wp_ajax_lexhoy_get_algolia_count', array($this, 'ajax_get_algolia_count'));
        add_action('wp_ajax_lexhoy_bulk_import_block', array($this, 'ajax_bulk_import_block'));

        // Registrar taxonomía de áreas de práctica
        add_action('init', array($this, 'register_taxonomies'));
        
        // Mostrar notificación si no hay configuración de Algolia
        add_action('admin_notices', array($this, 'show_algolia_config_notice'));
        
        // Cargar estilos CSS en el admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        
        // Inicializar cliente de Algolia
        $this->init_algolia_client();

        // Nuevo: disparar sincronización cuando un despacho se publica - CORREGIDO para evitar nuevas instancias
        add_action(
            'transition_post_status',
            array($this, 'handle_transition_post_status'),
            10,
            3
        );

        // Evitar bucle infinito entre /despacho/slug y /slug
        add_filter('redirect_canonical', array($this, 'prevent_canonical_redirect_for_despachos'), 10, 2);

        // Registrar submenú para importación masiva desde Algolia
        add_action('admin_menu', array($this, 'register_import_submenu'));
        
        // Cargar plantilla personalizada para despachos individuales
        add_filter('single_template', array($this, 'load_single_despacho_template'));

        // Filtros para configurar títulos de página correctamente
        add_filter('document_title_parts', array($this, 'modify_page_title'), 10, 1);
        add_filter('wp_title', array($this, 'modify_wp_title'), 10, 2);

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
        }
    }

    /**
     * Registrar el Custom Post Type
     */
    public function register_post_type() {
        $labels = array(
            'name'               => 'Despachos',
            'singular_name'      => 'Despacho',
            'menu_name'          => 'Despachos',
            'name_admin_bar'     => 'Despacho',
            'add_new'           => 'Añadir Nuevo',
            'add_new_item'      => 'Añadir Nuevo Despacho',
            'new_item'          => 'Nuevo Despacho',
            'edit_item'         => 'Editar Despacho',
            'view_item'         => 'Ver Despacho',
            'all_items'         => 'Todos los Despachos',
            'search_items'      => 'Buscar Despachos',
            'parent_item_colon' => 'Despachos Padre:',
            'not_found'         => 'No se encontraron despachos.',
            'not_found_in_trash'=> 'No se encontraron despachos en la papelera.'
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'despacho', 'with_front' => false),
            'capability_type'   => 'post',
            'has_archive'       => false,
            'hierarchical'      => false,
            'menu_position'     => 5,
            'menu_icon'         => 'dashicons-building',
            'supports'          => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest'      => true,
            // OPTIMIZACIONES SEO
            'show_in_nav_menus' => true,
            'can_export'        => true,
            'delete_with_user'  => false,
            'map_meta_cap'      => true,
            // Soporte para Rank Math SEO
            'show_in_sitemap'   => true,
        );

        register_post_type('despacho', $args);
    }

    /**
     * Configurar títulos de página
     */
    public function modify_page_title($title) {
        if (is_singular('despacho')) {
            $despacho_name = get_post_meta(get_the_ID(), '_despacho_nombre', true);
            if ($despacho_name) {
                $title['title'] = $despacho_name . ' - Despacho de Abogados';
            }
        }
        
        if (is_post_type_archive('despacho') || (is_page() && get_query_var('page_id') == get_option('lexhoy_despachos_search_page_id'))) {
            $title['title'] = 'Buscador de despachos - Lexhoy';
        }

        return $title;
    }

    public function modify_wp_title($title, $sep) {
        if (is_singular('despacho')) {
            $despacho_name = get_post_meta(get_the_ID(), '_despacho_nombre', true);
            if ($despacho_name) {
                return $despacho_name . ' - Despacho de Abogados';
            }
        }
        
        if (is_post_type_archive('despacho') || (is_page() && get_query_var('page_id') == get_option('lexhoy_despachos_search_page_id'))) {
            return 'Buscador de despachos - Lexhoy';
        }

        return $title;
    }

    // PLACEHOLDER: Aquí irían todas las demás funciones del archivo original
    // Por ahora mantenemos solo las funciones esenciales para que no dé error fatal
    
    public function add_meta_boxes() {
        // Función placeholder
    }
    
    public function save_meta_boxes($post_id) {
        // Función placeholder
    }
    
    public function test_save_post_hook($post_id) {
        // Función placeholder
    }
    
    public function handle_transition_post_status($new_status, $old_status, $post) {
        // Función placeholder
    }
    
    public function handle_clean_urls() {
        // Función placeholder
    }
    
    public function filter_post_type_link($post_link, $post) {
        return $post_link;
    }
    
    public function sync_to_algolia($post_id) {
        // Función placeholder
    }
    
    public function delete_from_algolia($post_id) {
        // Función placeholder
    }
    
    public function restore_from_trash($post_id) {
        // Función placeholder
    }
    
    public function sync_all_from_algolia() {
        // Función placeholder
    }
    
    public function ajax_get_algolia_count() {
        // Función placeholder
    }
    
    public function ajax_bulk_import_block() {
        // Función placeholder
    }
    
    public function register_taxonomies() {
        // Función placeholder
    }
    
    public function show_algolia_config_notice() {
        // Función placeholder
    }
    
    public function enqueue_admin_styles($hook) {
        // Función placeholder
    }
    
    public function prevent_canonical_redirect_for_despachos($redirect_url, $requested_url) {
        return $redirect_url;
    }
    
    public function register_import_submenu() {
        // Función placeholder
    }
    
    public function load_single_despacho_template($template) {
        return $template;
    }
} 
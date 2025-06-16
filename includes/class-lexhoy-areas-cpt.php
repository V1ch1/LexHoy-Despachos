<?php
/**
 * Clase para manejar el Custom Post Type de Áreas de Práctica
 */
class LexhoyAreasCPT {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('admin_menu', array($this, 'add_sync_button'));
        add_action('admin_init', array($this, 'handle_sync_action'));
    }

    /**
     * Registrar el Custom Post Type
     */
    public function register_post_type() {
        $labels = array(
            'name'               => 'Áreas de Práctica',
            'singular_name'      => 'Área de Práctica',
            'menu_name'          => 'Áreas de Práctica',
            'name_admin_bar'     => 'Área de Práctica',
            'add_new'           => 'Añadir Nueva',
            'add_new_item'      => 'Añadir Nueva Área',
            'new_item'          => 'Nueva Área',
            'edit_item'         => 'Editar Área',
            'view_item'         => 'Ver Área',
            'all_items'         => 'Todas las Áreas',
            'search_items'      => 'Buscar Áreas',
            'parent_item_colon' => 'Áreas Padre:',
            'not_found'         => 'No se encontraron áreas.',
            'not_found_in_trash'=> 'No se encontraron áreas en la papelera.'
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'           => true,
            'show_in_menu'      => false, // No mostrar en el menú
            'query_var'         => true,
            'rewrite'           => array('slug' => 'areas-practica'),
            'capability_type'   => 'post',
            'has_archive'       => true,
            'hierarchical'      => false,
            'menu_position'     => null,
            'supports'          => array('title', 'editor', 'thumbnail'),
            'show_in_rest'      => true,
            'show_in_nav_menus' => false, // No mostrar en el menú de navegación
            'show_in_admin_bar' => false, // No mostrar en la barra de administración
            'menu_icon'         => null, // No mostrar icono
        );

        register_post_type('area_practica', $args);
    }

    /**
     * Agregar meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'area_practica_details',
            'Detalles del Área de Práctica',
            array($this, 'render_meta_box'),
            'area_practica',
            'normal',
            'high'
        );
    }

    /**
     * Renderizar meta box
     */
    public function render_meta_box($post) {
        // Obtener valores guardados
        $slug = get_post_meta($post->ID, '_area_practica_slug', true);
        $descripcion = get_post_meta($post->ID, '_area_practica_descripcion', true);

        // Nonce para seguridad
        wp_nonce_field('area_practica_meta_box', 'area_practica_meta_box_nonce');
        ?>
        <div class="area-practica-meta-box">
            <p>
                <label for="area_practica_slug">Slug:</label><br>
                <input type="text" id="area_practica_slug" name="area_practica_slug" 
                       value="<?php echo esc_attr($slug); ?>" class="widefat">
                <span class="description">URL amigable para el área de práctica</span>
            </p>
            <p>
                <label for="area_practica_descripcion">Descripción:</label><br>
                <textarea id="area_practica_descripcion" name="area_practica_descripcion" 
                          class="widefat" rows="3"><?php echo esc_textarea($descripcion); ?></textarea>
            </p>
        </div>
        <?php
    }

    /**
     * Guardar meta box
     */
    public function save_meta_boxes($post_id) {
        // Verificar nonce
        if (!isset($_POST['area_practica_meta_box_nonce'])) {
            return;
        }
        if (!wp_verify_nonce($_POST['area_practica_meta_box_nonce'], 'area_practica_meta_box')) {
            return;
        }

        // Verificar autoguardado
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Guardar datos
        if (isset($_POST['area_practica_slug'])) {
            update_post_meta($post_id, '_area_practica_slug', sanitize_title($_POST['area_practica_slug']));
        }
        if (isset($_POST['area_practica_descripcion'])) {
            update_post_meta($post_id, '_area_practica_descripcion', sanitize_textarea_field($_POST['area_practica_descripcion']));
        }
    }

    /**
     * Agregar botón de sincronización
     */
    public function add_sync_button() {
        add_submenu_page(
            'edit.php?post_type=despacho',
            'Sincronizar Áreas',
            'Sincronizar Áreas',
            'manage_options',
            'sync-areas',
            array($this, 'render_sync_page')
        );
    }

    /**
     * Renderizar página de sincronización
     */
    public function render_sync_page() {
        ?>
        <div class="wrap">
            <h1>Sincronizar Áreas de Práctica desde Algolia</h1>
            <p>Esta acción extraerá todas las áreas de práctica únicas de los despachos en Algolia y las sincronizará con WordPress.</p>
            <form method="post" action="">
                <?php wp_nonce_field('sync_areas_action', 'sync_areas_nonce'); ?>
                <input type="hidden" name="action" value="sync_areas">
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Sincronizar Áreas">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Sincronizar áreas desde Algolia
     */
    public function sync_areas_from_algolia() {
        // Obtener configuración de Algolia
        $app_id = get_option('lexhoy_despachos_algolia_app_id');
        $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
        $index_name = get_option('lexhoy_despachos_algolia_index_name');

        if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
            throw new Exception('Configuración incompleta de Algolia');
        }

        // Inicializar cliente Algolia
        $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, '', $index_name);

        // Obtener todos los despachos
        $result = $client->browse_all();
        
        // Log para depuración
        error_log('Respuesta de Algolia: ' . print_r($result, true));

        if (!$result['success']) {
            throw new Exception('Error al obtener datos de Algolia: ' . $result['message']);
        }

        if (!isset($result['hits']) || !is_array($result['hits'])) {
            error_log('Estructura de respuesta inesperada: ' . print_r($result, true));
            throw new Exception('No se encontraron despachos en Algolia');
        }

        // Extraer áreas únicas
        $areas = array();
        foreach ($result['hits'] as $hit) {
            if (isset($hit['areas_practica']) && is_array($hit['areas_practica'])) {
                foreach ($hit['areas_practica'] as $area) {
                    if (!empty($area)) {
                        $areas[$area] = true;
                    }
                }
            }
        }

        if (empty($areas)) {
            throw new Exception('No se encontraron áreas de práctica en los despachos');
        }

        // Crear o actualizar áreas en WordPress
        $areas_created = 0;
        $areas_updated = 0;

        foreach (array_keys($areas) as $area_name) {
            // Buscar si ya existe el término
            $term = term_exists($area_name, 'area_practica');
            
            if (!$term) {
                // Crear nuevo término
                $term = wp_insert_term($area_name, 'area_practica');
                if (is_wp_error($term)) {
                    error_log('Error al crear área: ' . $term->get_error_message());
                    continue;
                }
                $areas_created++;
            } else {
                // Actualizar slug si es necesario
                $term_id = is_array($term) ? $term['term_id'] : $term;
                $term_obj = get_term($term_id, 'area_practica');
                
                if ($term_obj && $term_obj->slug !== sanitize_title($area_name)) {
                    wp_update_term($term_id, 'area_practica', array(
                        'slug' => sanitize_title($area_name)
                    ));
                    $areas_updated++;
                }
            }
        }

        return array(
            'success' => true,
            'created' => $areas_created,
            'updated' => $areas_updated,
            'total' => count($areas)
        );
    }

    /**
     * Manejar acción de sincronización
     */
    public function handle_sync_action() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'sync_areas') {
            return;
        }

        if (!isset($_POST['sync_areas_nonce']) || !wp_verify_nonce($_POST['sync_areas_nonce'], 'sync_areas_action')) {
            wp_die('Acción no autorizada');
        }

        try {
            $result = $this->sync_areas_from_algolia();
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>Áreas sincronizadas exitosamente. Se crearon ' . 
                     $result['created'] . ' áreas nuevas y se actualizaron ' . $result['updated'] . ' áreas existentes.</p></div>';
            });
        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error is-dismissible"><p>Error al sincronizar áreas: ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
} 
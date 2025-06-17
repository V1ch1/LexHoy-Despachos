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

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        
        // Redirecciones para URLs limpias
        add_action('template_redirect', array($this, 'handle_clean_urls'));
        
        // Filtrar permalinks para URLs limpias de despachos
        add_filter('post_type_link', array($this, 'filter_post_type_link'), 10, 2);
        
        // Acciones para Algolia
        add_action('save_post_despacho', array($this, 'sync_to_algolia'), 10, 3);
        add_action('before_delete_post', array($this, 'delete_from_algolia'));
        add_action('wp_trash_post', array($this, 'delete_from_algolia'));
        add_action('trash_despacho', array($this, 'delete_from_algolia'));
        add_action('untrash_post', array($this, 'restore_from_trash'));
        
        // Acción para sincronización programada
        add_action('lexhoy_despachos_sync_from_algolia', array($this, 'sync_all_from_algolia'));

        // Registrar taxonomía de áreas de práctica
        add_action('init', array($this, 'register_taxonomies'));
        
        // Mostrar notificación si no hay configuración de Algolia
        add_action('admin_notices', array($this, 'show_algolia_config_notice'));
        
        // Cargar estilos CSS en el admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        
        // Inicializar cliente de Algolia
        $this->init_algolia_client();
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
        );

        register_post_type('despacho', $args);
    }

    /**
     * Agregar meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'despacho_details',
            'Detalles del Despacho',
            array($this, 'render_meta_box'),
            'despacho',
            'normal',
            'high'
        );
    }

    /**
     * Renderizar meta box
     */
    public function render_meta_box($post) {
        // Obtener valores guardados
        $nombre = get_post_meta($post->ID, '_despacho_nombre', true);
        $localidad = get_post_meta($post->ID, '_despacho_localidad', true);
        $provincia = get_post_meta($post->ID, '_despacho_provincia', true);
        $codigo_postal = get_post_meta($post->ID, '_despacho_codigo_postal', true);
        $direccion = get_post_meta($post->ID, '_despacho_direccion', true);
        $telefono = get_post_meta($post->ID, '_despacho_telefono', true);
        $email = get_post_meta($post->ID, '_despacho_email', true);
        $web = get_post_meta($post->ID, '_despacho_web', true);
        $descripcion = get_post_meta($post->ID, '_despacho_descripcion', true);
        $estado_verificacion = get_post_meta($post->ID, '_despacho_estado_verificacion', true);
        $is_verified = get_post_meta($post->ID, '_despacho_is_verified', true);

        // Nonce para seguridad
        wp_nonce_field('despacho_meta_box', 'despacho_meta_box_nonce');
        ?>
        <div class="despacho-meta-box">
            <p>
                <label for="despacho_nombre"><strong>Nombre: *</strong></label><br>
                <input type="text" id="despacho_nombre" name="despacho_nombre" 
                       value="<?php echo esc_attr($nombre); ?>" class="widefat" required>
                <span class="description">Nombre del despacho (obligatorio)</span>
            </p>
            <p>
                <label for="despacho_localidad"><strong>Localidad: *</strong></label><br>
                <input type="text" id="despacho_localidad" name="despacho_localidad" 
                       value="<?php echo esc_attr($localidad); ?>" class="widefat" required>
                <span class="description">Ciudad donde se encuentra el despacho (obligatorio)</span>
            </p>
            <p>
                <label for="despacho_provincia"><strong>Provincia: *</strong></label><br>
                <input type="text" id="despacho_provincia" name="despacho_provincia" 
                       value="<?php echo esc_attr($provincia); ?>" class="widefat" required>
                <span class="description">Provincia/estado (obligatorio)</span>
            </p>
            <p>
                <label for="despacho_codigo_postal">Código Postal:</label><br>
                <input type="text" id="despacho_codigo_postal" name="despacho_codigo_postal" 
                       value="<?php echo esc_attr($codigo_postal); ?>" class="widefat">
            </p>
            <p>
                <label for="despacho_direccion">Dirección:</label><br>
                <input type="text" id="despacho_direccion" name="despacho_direccion" 
                       value="<?php echo esc_attr($direccion); ?>" class="widefat">
            </p>
            <p>
                <label for="despacho_telefono"><strong>Teléfono: *</strong></label><br>
                <input type="tel" id="despacho_telefono" name="despacho_telefono" 
                       value="<?php echo esc_attr($telefono); ?>" class="widefat" required>
                <span class="description">Número de teléfono (obligatorio)</span>
            </p>
            <p>
                <label for="despacho_email"><strong>Email: *</strong></label><br>
                <input type="email" id="despacho_email" name="despacho_email" 
                       value="<?php echo esc_attr($email); ?>" class="widefat" required>
                <span class="description">Dirección de correo electrónico (obligatorio)</span>
            </p>
            <p>
                <label for="despacho_web">Web:</label><br>
                <input type="url" id="despacho_web" name="despacho_web" 
                       value="<?php echo esc_attr($web); ?>" class="widefat">
                <span class="description">Sitio web del despacho (opcional)</span>
            </p>
            <p>
                <label for="despacho_descripcion">Descripción:</label><br>
                <textarea id="despacho_descripcion" name="despacho_descripcion" 
                          class="widefat" rows="3"><?php echo esc_textarea($descripcion); ?></textarea>
                <span class="description">Descripción del despacho (opcional)</span>
            </p>
            <p>
                <label for="despacho_estado_verificacion">Estado de Verificación:</label><br>
                <select id="despacho_estado_verificacion" name="despacho_estado_verificacion" class="widefat">
                    <option value="pendiente" <?php selected($estado_verificacion, 'pendiente'); ?>>Pendiente</option>
                    <option value="verificado" <?php selected($estado_verificacion, 'verificado'); ?>>Verificado</option>
                    <option value="rechazado" <?php selected($estado_verificacion, 'rechazado'); ?>>Rechazado</option>
                </select>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="despacho_is_verified" value="1" 
                           <?php checked($is_verified, '1'); ?>>
                    Verificado
                </label>
            </p>
        </div>
        <?php
    }

    /**
     * Guardar meta boxes
     */
    public function save_meta_boxes($post_id) {
        // Verificar nonce
        if (!isset($_POST['despacho_meta_box_nonce']) || !wp_verify_nonce($_POST['despacho_meta_box_nonce'], 'despacho_meta_box')) {
            return;
        }

        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // No guardar en autoguardado
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Validar campos obligatorios
        $required_fields = array(
            'despacho_nombre' => 'Nombre',
            'despacho_localidad' => 'Localidad',
            'despacho_provincia' => 'Provincia',
            'despacho_telefono' => 'Teléfono',
            'despacho_email' => 'Email'
        );

        $errors = array();
        foreach ($required_fields as $field => $label) {
            if (empty($_POST[$field])) {
                $errors[] = "El campo '$label' es obligatorio.";
            }
        }

        // Si hay errores, mostrar mensaje y no guardar
        if (!empty($errors)) {
            add_action('admin_notices', function() use ($errors) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>Error al guardar el despacho:</strong></p>';
                echo '<ul>';
                foreach ($errors as $error) {
                    echo '<li>' . esc_html($error) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            });
            return;
        }

        // Guardar datos
        $fields = array(
            'despacho_nombre' => '_despacho_nombre',
            'despacho_localidad' => '_despacho_localidad',
            'despacho_provincia' => '_despacho_provincia',
            'despacho_codigo_postal' => '_despacho_codigo_postal',
            'despacho_direccion' => '_despacho_direccion',
            'despacho_telefono' => '_despacho_telefono',
            'despacho_email' => '_despacho_email',
            'despacho_web' => '_despacho_web',
            'despacho_descripcion' => '_despacho_descripcion',
            'despacho_estado_verificacion' => '_despacho_estado_verificacion'
        );

        foreach ($fields as $post_field => $meta_field) {
            if (isset($_POST[$post_field])) {
                $value = sanitize_text_field($_POST[$post_field]);
                
                // Validaciones específicas
                if ($post_field === 'despacho_email') {
                    $value = sanitize_email($_POST[$post_field]);
                } elseif ($post_field === 'despacho_web') {
                    $value = esc_url_raw($_POST[$post_field]);
                } elseif ($post_field === 'despacho_descripcion') {
                    $value = sanitize_textarea_field($_POST[$post_field]);
                }
                
                update_post_meta($post_id, $meta_field, $value);
            }
        }

        // Guardar checkbox de verificado
        $is_verified = isset($_POST['despacho_is_verified']) ? '1' : '0';
        update_post_meta($post_id, '_despacho_is_verified', $is_verified);
    }

    /**
     * Sincronizar un post a Algolia
     */
    public function sync_to_algolia($post_id, $post, $update) {
        // No hacer nada si es una revisión o autoguardado
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        // No hacer nada si no es un despacho
        if ($post->post_type !== 'despacho') {
            return;
        }

        // No hacer nada si el post está en la papelera
        if ($post->post_status === 'trash') {
            return;
        }

        try {
            // Obtener configuración de Algolia
            $app_id = get_option('lexhoy_despachos_algolia_app_id');
            $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
            $index_name = get_option('lexhoy_despachos_algolia_index_name');

            if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
                error_log('Configuración incompleta de Algolia. El despacho se guardó localmente pero no se sincronizó con Algolia.');
                return;
            }

            // Inicializar cliente Algolia
            $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, '', $index_name);

            // Obtener meta datos
            $meta_data = get_post_meta($post_id);
            
            // Obtener áreas de práctica como taxonomía
            $areas_practica = wp_get_post_terms($post_id, 'area_practica', array('fields' => 'names'));
            
            // Preparar datos para Algolia
            $record = array(
                'objectID' => $post_id,
                'nombre' => get_the_title($post_id),
                'localidad' => isset($meta_data['_despacho_localidad'][0]) ? $meta_data['_despacho_localidad'][0] : '',
                'provincia' => isset($meta_data['_despacho_provincia'][0]) ? $meta_data['_despacho_provincia'][0] : '',
                'areas_practica' => $areas_practica,
                'codigo_postal' => isset($meta_data['_despacho_codigo_postal'][0]) ? $meta_data['_despacho_codigo_postal'][0] : '',
                'direccion' => isset($meta_data['_despacho_direccion'][0]) ? $meta_data['_despacho_direccion'][0] : '',
                'telefono' => isset($meta_data['_despacho_telefono'][0]) ? $meta_data['_despacho_telefono'][0] : '',
                'email' => isset($meta_data['_despacho_email'][0]) ? $meta_data['_despacho_email'][0] : '',
                'web' => isset($meta_data['_despacho_web'][0]) ? $meta_data['_despacho_web'][0] : '',
                'descripcion' => isset($meta_data['_despacho_descripcion'][0]) ? $meta_data['_despacho_descripcion'][0] : '',
                'estado_verificacion' => isset($meta_data['_despacho_estado_verificacion'][0]) ? $meta_data['_despacho_estado_verificacion'][0] : 'pendiente',
                'isVerified' => isset($meta_data['_despacho_is_verified'][0]) ? $meta_data['_despacho_is_verified'][0] : false,
                'ultima_actualizacion' => date('d-m-Y'),
                'slug' => $post->post_name
            );

            // Sincronizar con Algolia
            $client->save_object($index_name, $record);

        } catch (Exception $e) {
            error_log('Error al sincronizar despacho con Algolia: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar de Algolia
     */
    public function delete_from_algolia($post_id) {
        try {
            if ($this->algolia_client) {
                $this->algolia_client->delete_object($this->algolia_client->get_index_name(), $post_id);
            }
        } catch (Exception $e) {
            error_log('Error al eliminar despacho de Algolia: ' . $e->getMessage());
        }
    }

    /**
     * Restaurar desde papelera
     */
    public function restore_from_trash($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'despacho') {
            $this->sync_to_algolia($post_id, $post, true);
        }
    }

    /**
     * Sincronizar desde Algolia
     */
    public function sync_from_algolia($object_id) {
        try {
            if ($this->algolia_client) {
                $record = $this->algolia_client->get_object($this->algolia_client->get_index_name(), $object_id);
                if ($record) {
                    // Actualizar post con datos de Algolia
            $post_data = array(
                        'ID' => $object_id,
                        'post_title' => $record['nombre'],
                        'post_content' => $record['descripcion'],
                        'post_name' => $record['slug']
                    );
                    
                    wp_update_post($post_data);

            // Actualizar meta datos
                    update_post_meta($object_id, '_despacho_nombre', $record['nombre']);
                    update_post_meta($object_id, '_despacho_localidad', $record['localidad']);
                    update_post_meta($object_id, '_despacho_provincia', $record['provincia']);
                    update_post_meta($object_id, '_despacho_codigo_postal', $record['codigo_postal']);
                    update_post_meta($object_id, '_despacho_direccion', $record['direccion']);
                    update_post_meta($object_id, '_despacho_telefono', $record['telefono']);
                    update_post_meta($object_id, '_despacho_email', $record['email']);
                    update_post_meta($object_id, '_despacho_web', $record['web']);
                    update_post_meta($object_id, '_despacho_descripcion', $record['descripcion']);
                    update_post_meta($object_id, '_despacho_estado_verificacion', $record['estado_verificacion']);
                    update_post_meta($object_id, '_despacho_is_verified', $record['isVerified']);
                }
            }
        } catch (Exception $e) {
            error_log('Error al sincronizar desde Algolia: ' . $e->getMessage());
        }
    }

    /**
     * Sincronizar todos desde Algolia
     */
    public function sync_all_from_algolia() {
        try {
            if ($this->algolia_client) {
                $result = $this->algolia_client->browse_all();
                if ($result['success'] && isset($result['hits'])) {
                    foreach ($result['hits'] as $record) {
                        if (isset($record['objectID'])) {
                            $this->sync_from_algolia($record['objectID']);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Error al sincronizar todos desde Algolia: ' . $e->getMessage());
        }
    }

    /**
     * Registrar taxonomías
     */
    public function register_taxonomies() {
        // Taxonomía para áreas de práctica
        $labels = array(
            'name' => 'Áreas de Práctica',
            'singular_name' => 'Área de Práctica',
            'search_items' => 'Buscar Áreas',
            'all_items' => 'Todas las Áreas',
            'parent_item' => 'Área Padre',
            'parent_item_colon' => 'Área Padre:',
            'edit_item' => 'Editar Área',
            'update_item' => 'Actualizar Área',
            'add_new_item' => 'Añadir Nueva Área',
            'new_item_name' => 'Nueva Área',
            'menu_name' => 'Áreas de Práctica'
        );

        $args = array(
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'area-practica'),
            'show_in_rest' => true
        );

        register_taxonomy('area_practica', array('despacho'), $args);
    }

    /**
     * Mostrar notificación de configuración de Algolia
     */
    public function show_algolia_config_notice() {
        $app_id = get_option('lexhoy_despachos_algolia_app_id');
        $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
        $search_api_key = get_option('lexhoy_despachos_algolia_search_api_key');
        $index_name = get_option('lexhoy_despachos_algolia_index_name');

        if (empty($app_id) || empty($admin_api_key) || empty($search_api_key) || empty($index_name)) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong>⚠️ Configuración de Algolia incompleta</strong><br>
                    Para que la sincronización con Algolia funcione correctamente, 
                    completa la configuración en <a href="<?php echo admin_url('edit.php?post_type=despacho&page=lexhoy-despachos-algolia'); ?>">Configuración de Algolia</a>.
                </p>
            </div>
            <?php
        }
    }

    /**
     * Cargar estilos CSS en el admin
     */
    public function enqueue_admin_styles($hook) {
        // Solo cargar en páginas de despachos
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            $screen = get_current_screen();
            if ($screen && $screen->post_type === 'despacho') {
                wp_enqueue_style(
                    'lexhoy-despachos-admin',
                    LEXHOY_DESPACHOS_PLUGIN_URL . 'assets/css/lexhoy-despachos-admin.css',
                    array(),
                    LEXHOY_DESPACHOS_VERSION
                );
            }
        }
    }

    /**
     * Manejar URLs limpias para despachos
     */
    public function handle_clean_urls() {
        // Solo procesar en el frontend, no en admin
        if (is_admin()) {
            return;
        }
        
        // Obtener la URL actual
        $current_url = $_SERVER['REQUEST_URI'];
        $path = trim(parse_url($current_url, PHP_URL_PATH), '/');
        
        // Si la URL contiene /despacho/, redirigir a URL limpia
        if (strpos($path, 'despacho/') === 0) {
            $despacho_name = substr($path, 9); // Quitar 'despacho/'
            
            // Buscar el despacho
            $despacho = get_posts(array(
                'post_type' => 'despacho',
                'name' => $despacho_name,
                'post_status' => 'publish',
                'numberposts' => 1
            ));
            
            if (!empty($despacho)) {
                // Redirigir a la URL limpia con 301 (permanente para SEO)
                $clean_url = home_url('/' . $despacho_name . '/');
                wp_redirect($clean_url, 301);
                exit;
            }
        }
        
        // Si la URL es limpia (sin /despacho/), verificar si es un despacho
        if (!empty($path) && !is_admin()) {
            // Buscar un despacho con este slug
            $despacho = get_posts(array(
                'post_type' => 'despacho',
                'name' => $path,
                'post_status' => 'publish',
                'numberposts' => 1
            ));
            
            if (!empty($despacho)) {
                // Configurar WordPress para mostrar el despacho
                global $wp_query, $post;
                $despacho = $despacho[0];
                
                $wp_query->is_single = true;
                $wp_query->is_singular = true;
                $wp_query->is_post_type_archive = false;
                $wp_query->is_archive = false;
                $wp_query->is_home = false;
                $wp_query->is_front_page = false;
                $wp_query->is_404 = false;
                
                $wp_query->post = $despacho;
                $wp_query->posts = array($despacho);
                $wp_query->post_count = 1;
                $wp_query->found_posts = 1;
                $wp_query->max_num_pages = 1;
                $wp_query->queried_object = $despacho;
                $wp_query->queried_object_id = $despacho->ID;
                
                $post = $despacho;
                setup_postdata($post);
            }
        }
    }

    /**
     * Generar enlaces permanentes sin el slug 'despacho'
     */
    public function filter_post_type_link($post_link, $post) {
        if ($post->post_type === 'despacho' && 'publish' === $post->post_status) {
            return home_url('/' . $post->post_name . '/');
        }
        return $post_link;
    }
} 
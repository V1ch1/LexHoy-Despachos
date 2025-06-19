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

        // NUEVO: menú y acción de importación manual de un despacho
        add_action('admin_menu', array($this, 'register_import_submenu'));
        add_action('admin_post_lexhoy_import_one_despacho', array($this, 'handle_import_one_despacho'));

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

        // NUEVOS CAMPOS
        $especialidades = get_post_meta($post->ID, '_despacho_especialidades', true);
        $horario = get_post_meta($post->ID, '_despacho_horario', true);
        $redes_sociales = get_post_meta($post->ID, '_despacho_redes_sociales', true);
        $experiencia = get_post_meta($post->ID, '_despacho_experiencia', true);
        $tamano_despacho = get_post_meta($post->ID, '_despacho_tamaño', true);
        $ano_fundacion = get_post_meta($post->ID, '_despacho_año_fundacion', true);
        $estado_registro = get_post_meta($post->ID, '_despacho_estado_registro', true);

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

            <!-- NUEVO: Especialidades -->
            <p>
                <label for="despacho_especialidades">Especialidades (separadas por coma):</label><br>
                <input type="text" id="despacho_especialidades" name="despacho_especialidades" value="<?php echo esc_attr($especialidades); ?>" class="widefat">
            </p>

            <!-- NUEVO: Horario -->
            <h4>Horario</h4>
            <div class="horario-pairs">
                <?php
                $dias = array('lunes','martes','miercoles','jueves','viernes','sabado','domingo');
                foreach($dias as $dia){
                    $valor = isset($horario[$dia]) ? $horario[$dia] : '';
                    echo '<div class="pair"><label for="despacho_horario_'.$dia.'">'.ucfirst($dia).':</label><input type="text" id="despacho_horario_'.$dia.'" name="despacho_horario['.$dia.']" value="'.esc_attr($valor).'" class="widefat"></div>';
                }
                ?>
            </div>

            <!-- NUEVO: Redes Sociales -->
            <h4>Redes Sociales</h4>
            <div class="redes-pairs">
                <?php
                $redes = array('facebook','twitter','linkedin','instagram');
                foreach($redes as $red){
                    $valor = isset($redes_sociales[$red]) ? $redes_sociales[$red] : '';
                    echo '<div class="pair"><label for="despacho_red_'.$red.'">'.ucfirst($red).':</label><input type="url" id="despacho_red_'.$red.'" name="despacho_redes_sociales['.$red.']" value="'.esc_attr($valor).'" class="widefat"></div>';
                }
                ?>
            </div>

            <!-- NUEVO: Experiencia -->
            <p>
                <label for="despacho_experiencia">Experiencia:</label><br>
                <textarea id="despacho_experiencia" name="despacho_experiencia" class="widefat" rows="3"><?php echo esc_textarea($experiencia); ?></textarea>
            </p>

            <!-- NUEVO: Tamaño del despacho y Año fundación -->
            <p>
                <label for="despacho_tamaño">Tamaño del Despacho:</label><br>
                <input type="text" id="despacho_tamaño" name="despacho_tamaño" value="<?php echo esc_attr($tamano_despacho); ?>" class="widefat">
            </p>
            <p>
                <label for="despacho_año_fundacion">Año de Fundación:</label><br>
                <input type="number" id="despacho_año_fundacion" name="despacho_año_fundacion" value="<?php echo esc_attr($ano_fundacion); ?>" class="widefat" min="1800" max="<?php echo date('Y'); ?>">
            </p>

            <!-- NUEVO: Estado de registro -->
            <p>
                <label for="despacho_estado_registro">Estado de Registro:</label><br>
                <select id="despacho_estado_registro" name="despacho_estado_registro" class="widefat">
                    <option value="activo" <?php selected($estado_registro, 'activo'); ?>>Activo</option>
                    <option value="inactivo" <?php selected($estado_registro, 'inactivo'); ?>>Inactivo</option>
                </select>
            </p>

            <!-- NUEVO: Áreas de Práctica -->
            <h4>Áreas de Práctica</h4>
            <div class="areas-checkboxes">
                <?php
                // Obtener todas las áreas (taxonomía area_practica)
                $all_areas = get_terms(array(
                    'taxonomy' => 'area_practica',
                    'hide_empty' => false,
                ));
                // Áreas seleccionadas para este despacho
                $selected_areas = wp_get_post_terms($post->ID, 'area_practica', array('fields' => 'ids'));
                foreach ($all_areas as $area) {
                    $checked = in_array($area->term_id, $selected_areas) ? 'checked' : '';
                    echo '<label><input type="checkbox" name="tax_input[area_practica][]" value="'.esc_attr($area->term_id).'" '.$checked.'> '.esc_html($area->name).'</label>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Test para verificar que el hook save_post funciona
     */
    public function test_save_post_hook($post_id) {
        // Solo logear si no estamos en importación masiva
        $is_bulk_import = isset($_POST['action']) && $_POST['action'] === 'lexhoy_bulk_import_block';
        
        if (!$is_bulk_import) {
            $this->custom_log("=== LexHoy TEST: save_post hook ejecutado para post {$post_id} ===");
            $post = get_post($post_id);
            if ($post) {
                $this->custom_log("LexHoy TEST: Post type = {$post->post_type}");
            }
        }
    }

    /**
     * Manejar cambios de estado de post para sincronización
     */
    public function handle_transition_post_status($new_status, $old_status, $post) {
        if ($post->post_type === 'despacho' && $new_status === 'publish') {
            $this->sync_to_algolia($post->ID, $post, true);
        }
    }

    /**
     * Sistema de logging personalizado
     */
    private function custom_log($message) {
        $log_file = ABSPATH . 'wp-content/lexhoy-debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Guardar meta boxes
     */
    public function save_meta_boxes($post_id) {
        // Solo logear si no estamos en importación masiva para evitar spam
        $is_bulk_import = isset($_POST['action']) && $_POST['action'] === 'lexhoy_bulk_import_block';
        
        if (!$is_bulk_import) {
            $this->custom_log('=== LexHoy DEBUG save_meta_boxes INICIO para post ' . $post_id . ' ===');
            $this->custom_log('POST data keys: ' . implode(', ', array_keys($_POST)));
        }
        
        // Verificar nonce - pero saltarlo durante importación masiva
        if (!$is_bulk_import && (!isset($_POST['despacho_meta_box_nonce']) || !wp_verify_nonce($_POST['despacho_meta_box_nonce'], 'despacho_meta_box'))) {
            if (!$is_bulk_import) {
                $this->custom_log('LexHoy DEBUG: nonce inválido o faltante');
                $this->custom_log('Nonce recibido: ' . (isset($_POST['despacho_meta_box_nonce']) ? $_POST['despacho_meta_box_nonce'] : 'NO EXISTE'));
            }
            return;
        }
        if (!$is_bulk_import) {
            $this->custom_log('LexHoy DEBUG: nonce válido');
        }

        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            if (!$is_bulk_import) {
                $this->custom_log('LexHoy DEBUG: usuario sin permisos para editar post ' . $post_id);
            }
            return;
        }
        if (!$is_bulk_import) {
            $this->custom_log('LexHoy DEBUG: permisos OK');
        }

        // No guardar en autoguardado
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            if (!$is_bulk_import) {
                $this->custom_log('LexHoy DEBUG: autoguardado detectado, saliendo');
            }
            return;
        }
        if (!$is_bulk_import) {
            $this->custom_log('LexHoy DEBUG: no es autoguardado');
        }

        // Validar campos obligatorios
        $required_fields = array(
            'despacho_nombre' => 'Nombre'
            // Quitamos email, telefono, etc para permitir guardado
        );

        if (!$is_bulk_import) {
            $this->custom_log('LexHoy DEBUG: Validando campos obligatorios...');
        }
        $errors = array();
        foreach ($required_fields as $field => $label) {
            $value = isset($_POST[$field]) ? $_POST[$field] : '';
            if (!$is_bulk_import) {
                $this->custom_log("LexHoy DEBUG: Campo {$field} = '{$value}'");
            }
            if (empty($value)) {
                $errors[] = "El campo '$label' es obligatorio.";
                if (!$is_bulk_import) {
                    $this->custom_log('LexHoy DEBUG campo requerido vacío: ' . $field);
                }
            }
        }

        // Si hay errores, mostrar mensaje y no guardar
        if (!empty($errors)) {
            if (!$is_bulk_import) {
                $this->custom_log('LexHoy DEBUG errores de validación. No se guarda.');
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
            }
            return;
        }
        if (!$is_bulk_import) {
            $this->custom_log('LexHoy DEBUG: validación OK, procediendo a guardar metadatos');
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
            'despacho_estado_verificacion' => '_despacho_estado_verificacion',
            // NUEVOS CAMPOS
            'despacho_especialidades' => '_despacho_especialidades',
            'despacho_experiencia' => '_despacho_experiencia',
            'despacho_tamaño' => '_despacho_tamaño',
            'despacho_año_fundacion' => '_despacho_año_fundacion',
            'despacho_estado_registro' => '_despacho_estado_registro'
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
                
                if (!$is_bulk_import) {
                    $this->custom_log("LexHoy DEBUG: Guardando {$meta_field} = '{$value}' para post {$post_id}");
                }
                $result = update_post_meta($post_id, $meta_field, $value);
                if (!$is_bulk_import) {
                    $this->custom_log("LexHoy DEBUG: update_post_meta resultado: " . ($result ? 'SUCCESS' : 'FAILED'));
                    
                    // Verificar que se guardó
                    $saved_value = get_post_meta($post_id, $meta_field, true);
                    $this->custom_log("LexHoy DEBUG: Valor guardado verificado: '{$saved_value}'");
                }
            } else {
                if (!$is_bulk_import) {
                    $this->custom_log("LexHoy DEBUG: Campo {$post_field} no existe en POST");
                }
            }
        }

        // Guardar checkbox de verificado
        $is_verified = isset($_POST['despacho_is_verified']) ? '1' : '0';
        if (!$is_bulk_import) {
            $this->custom_log("LexHoy DEBUG: Guardando _despacho_is_verified = '{$is_verified}'");
        }
        update_post_meta($post_id, '_despacho_is_verified', $is_verified);

        // Guardar horario (array de días)
        if (isset($_POST['despacho_horario']) && is_array($_POST['despacho_horario'])) {
            // Sanitizar cada valor
            $horario_clean = array_map('sanitize_text_field', $_POST['despacho_horario']);
            if (!$is_bulk_import) {
                $this->custom_log("LexHoy DEBUG: Guardando horario: " . print_r($horario_clean, true));
            }
            update_post_meta($post_id, '_despacho_horario', $horario_clean);
        }

        // Guardar redes sociales (array)
        if (isset($_POST['despacho_redes_sociales']) && is_array($_POST['despacho_redes_sociales'])) {
            $redes_clean = array_map('esc_url_raw', $_POST['despacho_redes_sociales']);
            if (!$is_bulk_import) {
                $this->custom_log("LexHoy DEBUG: Guardando redes sociales: " . print_r($redes_clean, true));
            }
            update_post_meta($post_id, '_despacho_redes_sociales', $redes_clean);
        }

        // --- NUEVO: Sincronizar título y slug con el campo Nombre ---
        if (isset($_POST['despacho_nombre'])) {
            // Eliminado: ya no sincronizamos título/slug automáticamente
        }

        if (!$is_bulk_import) {
            $this->custom_log('=== LexHoy DEBUG save_meta_boxes FINAL para post ' . $post_id . ' ===');
        }
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
            
            // Helper para preferir el dato recién enviado sobre el almacenado
            $posted_or_meta = function($post_field, $meta_key, $sanitize_cb = 'sanitize_text_field') use ($post_id) {
                if (isset($_POST[$post_field])) {
                    return is_callable($sanitize_cb) ? $sanitize_cb($_POST[$post_field]) : $_POST[$post_field];
                }
                // Limpiar caché de metadatos para este post antes de leer
                wp_cache_delete($post_id, 'post_meta');
                return get_post_meta($post_id, $meta_key, true);
            };

            error_log("LexHoy DEBUG: sync_to_algolia ejecutándose para post {$post_id}");
            error_log("LexHoy DEBUG: Datos POST disponibles: " . print_r(array_keys($_POST), true));

            $record = array(
                'objectID'         => get_post_meta($post_id, '_algolia_object_id', true) ?: $post_id,
                'nombre'           => $posted_or_meta('despacho_nombre', '_despacho_nombre'),
                'localidad'        => $posted_or_meta('despacho_localidad', '_despacho_localidad'),
                'provincia'        => $posted_or_meta('despacho_provincia', '_despacho_provincia'),
                'areas_practica'   => $areas_practica,
                'codigo_postal'    => $posted_or_meta('despacho_codigo_postal', '_despacho_codigo_postal'),
                'direccion'        => $posted_or_meta('despacho_direccion', '_despacho_direccion'),
                'telefono'         => $posted_or_meta('despacho_telefono', '_despacho_telefono'),
                'email'            => $posted_or_meta('despacho_email', '_despacho_email', 'sanitize_email'),
                'web'              => $posted_or_meta('despacho_web', '_despacho_web', 'esc_url_raw'),
                'descripcion'      => $posted_or_meta('despacho_descripcion', '_despacho_descripcion', 'sanitize_textarea_field'),
                'estado_verificacion'=> $posted_or_meta('despacho_estado_verificacion', '_despacho_estado_verificacion'),
                'isVerified'       => isset($_POST['despacho_is_verified']) ? true : (get_post_meta($post_id, '_despacho_is_verified', true) ? true : false),
                // NUEVOS CAMPOS
                'especialidades'   => isset($_POST['despacho_especialidades']) ? array_filter(array_map('trim', explode(',', $_POST['despacho_especialidades']))) : array_filter(array_map('trim', explode(',', get_post_meta($post_id, '_despacho_especialidades', true) ))),
                'horario'          => isset($_POST['despacho_horario']) ? array_map('sanitize_text_field', $_POST['despacho_horario']) : (array) get_post_meta($post_id, '_despacho_horario', true),
                'redes_sociales'   => isset($_POST['despacho_redes_sociales']) ? array_map('esc_url_raw', $_POST['despacho_redes_sociales']) : (array) get_post_meta($post_id, '_despacho_redes_sociales', true),
                'experiencia'      => $posted_or_meta('despacho_experiencia', '_despacho_experiencia'),
                'tamaño_despacho'  => $posted_or_meta('despacho_tamaño', '_despacho_tamaño'),
                'año_fundacion'    => (int) $posted_or_meta('despacho_año_fundacion', '_despacho_año_fundacion'),
                'estado_registro'  => $posted_or_meta('despacho_estado_registro', '_despacho_estado_registro'),
                'ultima_actualizacion' => date('d-m-Y'),
                'slug'             => $post->post_name,
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
                    // Determinar slug único
                    $slug = sanitize_title($record['slug'] ?? $record['nombre']);

                    // ¿Existe ya un despacho con este slug?
                    $existing = get_posts(array(
                        'post_type'   => 'despacho',
                        'name'        => $slug,
                        'post_status' => 'any',
                        'numberposts' => 1,
                        'fields'      => 'ids'
                    ));

                    if ($existing) {
                        $post_id = (int) $existing[0];
                    } else {
                        // Crear nuevo post
                        $post_id = wp_insert_post(array(
                            'post_type'   => 'despacho',
                            'post_title'  => $record['nombre'] ?? 'Despacho sin título',
                            'post_content'=> $record['descripcion'] ?? '',
                            'post_status' => 'publish',
                            'post_name'   => $slug
                        ));
                    }

                    // Verificar que se obtuvo un ID válido
                    if (is_wp_error($post_id) || $post_id <= 0) {
                        throw new Exception('No se pudo crear/obtener el post de WordPress.');
                    }

                    // Guardar meta para mapear con Algolia
                    update_post_meta($post_id, '_algolia_object_id', $object_id);

                    // Actualizar post si ya existía (título, contenido, etc.)
                    wp_update_post(array(
                        'ID'          => $post_id,
                        'post_title'  => $record['nombre'] ?? 'Despacho sin título',
                        'post_content'=> $record['descripcion'] ?? ''
                    ));

                    // Actualizar meta datos restantes
                    update_post_meta($post_id, '_despacho_nombre', $record['nombre'] ?? '');
                    update_post_meta($post_id, '_despacho_localidad', $record['localidad'] ?? '');
                    update_post_meta($post_id, '_despacho_provincia', $record['provincia'] ?? '');
                    update_post_meta($post_id, '_despacho_codigo_postal', $record['codigo_postal'] ?? '');
                    update_post_meta($post_id, '_despacho_direccion', $record['direccion'] ?? '');
                    update_post_meta($post_id, '_despacho_telefono', $record['telefono'] ?? '');
                    update_post_meta($post_id, '_despacho_email', $record['email'] ?? '');
                    update_post_meta($post_id, '_despacho_web', $record['web'] ?? '');
                    update_post_meta($post_id, '_despacho_descripcion', $record['descripcion'] ?? '');
                    update_post_meta($post_id, '_despacho_estado_verificacion', $record['estado_verificacion'] ?? 'pendiente');
                    update_post_meta($post_id, '_despacho_is_verified', $record['isVerified'] ?? 0);
                    // NUEVOS CAMPOS
                    update_post_meta($post_id, '_despacho_especialidades', isset($record['especialidades']) && is_array($record['especialidades']) ? implode(',', $record['especialidades']) : '');
                    update_post_meta($post_id, '_despacho_horario', $record['horario'] ?? array());
                    update_post_meta($post_id, '_despacho_redes_sociales', $record['redes_sociales'] ?? array());
                    update_post_meta($post_id, '_despacho_experiencia', $record['experiencia'] ?? '');
                    update_post_meta($post_id, '_despacho_tamaño', $record['tamaño_despacho'] ?? '');
                    update_post_meta($post_id, '_despacho_año_fundacion', $record['año_fundacion'] ?? 0);
                    update_post_meta($post_id, '_despacho_estado_registro', $record['estado_registro'] ?? 'activo');

                    // Sincronizar áreas de práctica (crear términos si no existen)
                    if (!empty($record['areas_practica']) && is_array($record['areas_practica'])) {
                        $term_ids = array();
                        foreach ($record['areas_practica'] as $area_name) {
                            $term = term_exists($area_name, 'area_practica');
                            if (!$term) {
                                $term = wp_insert_term($area_name, 'area_practica');
                            }
                            if (!is_wp_error($term)) {
                                $term_ids[] = intval($term['term_id']);
                            }
                        }
                        if ($term_ids) {
                            wp_set_post_terms($post_id, $term_ids, 'area_practica', false);
                        }
                    }
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

    /**
     * Registrar submenú para importar un despacho desde Algolia
     */
    public function register_import_submenu() {
        add_submenu_page(
            'edit.php?post_type=despacho',
            'Importar un Despacho',
            'Importar un Despacho',
            'manage_options',
            'lexhoy-despachos-import-one',
            array($this, 'render_import_page')
        );

        // NUEVO: Página de importación masiva
        add_submenu_page(
            'edit.php?post_type=despacho',
            'Importación Masiva desde Algolia',
            'Importación Masiva',
            'manage_options',
            'lexhoy-despachos-import-bulk',
            array($this, 'render_bulk_import_page')
        );
    }

    /**
     * Renderizar la página de importación
     */
    public function render_import_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.'));
        }

        // Comprobar mensajes
        $mensaje = isset($_GET['mensaje']) ? sanitize_text_field($_GET['mensaje']) : '';

        echo '<div class="wrap">';
        echo '<h1>Importar un Despacho desde Algolia</h1>';

        if ($mensaje === 'ok') {
            echo '<div class="notice notice-success is-dismissible"><p>Despacho importado correctamente.</p></div>';
        } elseif ($mensaje === 'error') {
            echo '<div class="notice notice-error is-dismissible"><p>No se pudo importar el despacho. Revisa los registros de error para más detalles.</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('lexhoy_import_one_despacho');
        echo '<input type="hidden" name="action" value="lexhoy_import_one_despacho" />';
        submit_button('Importar primer despacho');
        echo '</form>';
        echo '</div>';
    }

    /**
     * Manejar la importación de un despacho desde Algolia
     */
    public function handle_import_one_despacho() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes para realizar esta acción.'));
        }

        check_admin_referer('lexhoy_import_one_despacho');

        $redirect_url = admin_url('edit.php?post_type=despacho&page=lexhoy-despachos-import-one');

        try {
            if (!$this->algolia_client) {
                throw new Exception('Cliente de Algolia no inicializado.');
            }

            // Obtener el primer registro de Algolia
            $index_name = $this->algolia_client->get_index_name();
            $search_result = $this->algolia_client->search($index_name, '', array('hitsPerPage' => 1));

            if (!$search_result || empty($search_result['hits'])) {
                throw new Exception('No se encontró ningún registro en Algolia.');
            }

            $first_record = $search_result['hits'][0];
            if (!isset($first_record['objectID'])) {
                throw new Exception('El registro no tiene un objectID.');
            }

            // Sincronizar usando el método existente
            $this->sync_from_algolia($first_record['objectID']);

            // Redirigir con éxito
            wp_redirect(add_query_arg('mensaje', 'ok', $redirect_url));
            exit;
        } catch (Exception $e) {
            error_log('Error al importar un despacho: ' . $e->getMessage());
            wp_redirect(add_query_arg('mensaje', 'error', $redirect_url));
            exit;
        }
    }

    /**
     * Renderizar página de importación masiva
     */
    public function render_bulk_import_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.'));
        }

        $mensaje = isset($_GET['mensaje']) ? sanitize_text_field($_GET['mensaje']) : '';

        echo '<div class="wrap">';
        echo '<h1>Importación Masiva desde Algolia</h1>';

        if ($mensaje === 'iniciado') {
            echo '<div class="notice notice-info"><p>Importación iniciada. Revisa el progreso abajo.</p></div>';
        }

        // Verificar configuración de Algolia
        $app_id = get_option('lexhoy_despachos_algolia_app_id');
        $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
        $index_name = get_option('lexhoy_despachos_algolia_index_name');

        if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
            echo '<div class="notice notice-error"><p>⚠️ <strong>Configuración de Algolia incompleta.</strong> Completa la configuración antes de importar.</p></div>';
            echo '</div>';
            return;
        }

        // Obtener estadísticas
        $total_wp_despachos = wp_count_posts('despacho')->publish;
        
        try {
            $algolia_client = new LexhoyAlgoliaClient($app_id, $admin_api_key, '', $index_name);
            $result = $algolia_client->browse_all();
            $total_algolia = $result['success'] ? $result['total_records'] : 0;
        } catch (Exception $e) {
            $total_algolia = 'Error: ' . $e->getMessage();
        }

        ?>
        <div class="card" style="max-width: 600px;">
            <h2>📊 Estadísticas</h2>
            <table class="form-table">
                <tr>
                    <th>Despachos en WordPress:</th>
                    <td><strong><?php echo $total_wp_despachos; ?></strong></td>
                </tr>
                <tr>
                    <th>Registros en Algolia:</th>
                    <td><strong><?php echo $total_algolia; ?></strong></td>
                </tr>
            </table>
        </div>

        <div class="card" style="max-width: 600px; margin-top: 20px;">
            <h2>🚀 Iniciar Importación por Bloques</h2>
            <p>La importación se realizará en bloques de <strong>200 registros</strong> para evitar timeouts.</p>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" id="bulk-import-form">
                <input type="hidden" name="action" value="lexhoy_bulk_import_start" />
                <?php wp_nonce_field('lexhoy_bulk_import', 'bulk_import_nonce'); ?>
                
                <p>
                    <label>
                        <input type="checkbox" name="overwrite_existing" value="1" />
                        Sobrescribir despachos existentes (actualizar datos)
                    </label>
                </p>
                
                <button type="button" class="button button-primary" onclick="startBulkImport()">
                    🔄 Iniciar Importación Masiva
                </button>
            </form>
        </div>

        <!-- Área de progreso -->
        <div id="import-progress" style="display: none; margin-top: 20px;">
            <div class="card">
                <h2>📈 Progreso de Importación</h2>
                <div id="progress-bar-container" style="background: #f0f0f1; height: 20px; border-radius: 10px; overflow: hidden;">
                    <div id="progress-bar" style="background: #00a32a; height: 100%; width: 0%; transition: width 0.3s;"></div>
                </div>
                <p id="progress-text">Preparando importación...</p>
                <div id="import-log" style="background: #f8f9fa; padding: 15px; height: 300px; overflow-y: scroll; border: 1px solid #ccd0d4; font-family: monospace; font-size: 12px; margin-top: 15px;"></div>
            </div>
        </div>

        <style>
        .card { background: white; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
        .form-table th { width: 200px; }
        #import-log { white-space: pre-wrap; }
        </style>

        <script>
        let importInProgress = false;
        let currentBlock = 0;
        let totalBlocks = 0;
        let processedRecords = 0;
        let totalRecords = 0;

        function startBulkImport() {
            if (importInProgress) {
                alert('Ya hay una importación en curso.');
                return;
            }

            importInProgress = true;
            document.getElementById('import-progress').style.display = 'block';
            document.querySelector('#bulk-import-form button').disabled = true;
            document.querySelector('#bulk-import-form button').textContent = '⏳ Importando...';

            logMessage('🚀 Iniciando importación masiva...');
            
            // Primero obtener el total de registros
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'lexhoy_get_algolia_count',
                    nonce: '<?php echo wp_create_nonce("lexhoy_get_count"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        totalRecords = response.data.total;
                        totalBlocks = Math.ceil(totalRecords / 200);
                        logMessage(`📊 Total de registros en Algolia: ${totalRecords}`);
                        logMessage(`📦 Se procesarán ${totalBlocks} bloques de 200 registros`);
                        
                        // Iniciar el primer bloque
                        processNextBlock();
                    } else {
                        logMessage('❌ Error al obtener el conteo: ' + response.data);
                        finishImport();
                    }
                },
                error: function() {
                    logMessage('❌ Error de conexión al obtener el conteo');
                    finishImport();
                }
            });
        }

        function processNextBlock() {
            if (currentBlock >= totalBlocks) {
                logMessage('✅ ¡Importación completada!');
                finishImport();
                return;
            }

            currentBlock++;
            const startRecord = (currentBlock - 1) * 200;
            
            logMessage(`\n🔄 Procesando bloque ${currentBlock}/${totalBlocks} (registros ${startRecord + 1}-${Math.min(startRecord + 200, totalRecords)})`);
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'lexhoy_bulk_import_block',
                    nonce: '<?php echo wp_create_nonce("lexhoy_bulk_import_block"); ?>',
                    block: currentBlock,
                    overwrite: document.querySelector('input[name="overwrite_existing"]').checked ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        processedRecords += data.processed;
                        
                        logMessage(`✅ Bloque ${currentBlock} completado:`);
                        logMessage(`   • Procesados: ${data.processed}`);
                        logMessage(`   • Creados: ${data.created}`);
                        logMessage(`   • Actualizados: ${data.updated}`);
                        logMessage(`   • Saltados: ${data.skipped || 0}`);
                        logMessage(`   • Errores: ${data.errors}`);
                        
                        if (data.error_details && data.error_details.length > 0) {
                            data.error_details.forEach(error => {
                                logMessage(`   ⚠️ ${error}`);
                            });
                        }
                        
                        // Actualizar barra de progreso
                        const progress = (processedRecords / totalRecords) * 100;
                        document.getElementById('progress-bar').style.width = progress + '%';
                        document.getElementById('progress-text').textContent = 
                            `Progreso: ${processedRecords}/${totalRecords} (${Math.round(progress)}%)`;
                        
                        // Procesar siguiente bloque después de una pausa corta
                        setTimeout(processNextBlock, 1000);
                    } else {
                        logMessage(`❌ Error en bloque ${currentBlock}: ${response.data}`);
                        // Continuar con el siguiente bloque a pesar del error
                        setTimeout(processNextBlock, 2000);
                    }
                },
                error: function() {
                    logMessage(`❌ Error de conexión en bloque ${currentBlock}`);
                    // Continuar con el siguiente bloque a pesar del error
                    setTimeout(processNextBlock, 2000);
                }
            });
        }

        function logMessage(message) {
            const log = document.getElementById('import-log');
            const timestamp = new Date().toLocaleTimeString();
            log.textContent += `[${timestamp}] ${message}\n`;
            log.scrollTop = log.scrollHeight;
        }

        function finishImport() {
            importInProgress = false;
            document.querySelector('#bulk-import-form button').disabled = false;
            document.querySelector('#bulk-import-form button').textContent = '🔄 Iniciar Importación Masiva';
            
            // Actualizar estadísticas
            setTimeout(() => {
                location.reload();
            }, 3000);
        }
        </script>
        <?php

        echo '</div>';
    }

    public function prevent_canonical_redirect_for_despachos($redirect_url, $requested_url) {
        // Si ya estamos en un despacho singular => no redirigir
        if (is_singular('despacho')) {
            return false;
        }

        // Si la URL solicitada es del tipo /slug/ sin "despacho/"
        $path = trim(parse_url($requested_url, PHP_URL_PATH), '/');
        if ( $path && strpos( $path, 'despacho/' ) === false ) {
            $despacho = get_posts(array(
                'post_type'   => 'despacho',
                'name'        => $path,
                'post_status' => 'publish',
                'numberposts' => 1,
                'fields'      => 'ids',
            ));

            if ( $despacho ) {
                // Evitamos que WordPress redirija a /despacho/slug/
                return false;
            }
        }

        return $redirect_url; // para el resto de casos, comportamiento normal
    }

    /**
     * Manejar AJAX para obtener el conteo de registros en Algolia
     */
    public function ajax_get_algolia_count() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos suficientes para realizar esta acción.');
        }

        check_ajax_referer('lexhoy_get_count', 'nonce');

        try {
            if (!$this->algolia_client) {
                throw new Exception('Cliente de Algolia no inicializado.');
            }

            $this->custom_log('AJAX: Obteniendo conteo de registros de Algolia...');
            
            // Usar browse_all() temporalmente para obtener el conteo
            $result = $this->algolia_client->browse_all();
            $total_algolia = $result['success'] ? $result['total_records'] : 0;
            
            $this->custom_log("AJAX: Total encontrado: {$total_algolia}");

            wp_send_json_success(array('total' => $total_algolia));
        } catch (Exception $e) {
            $this->custom_log('AJAX Error: ' . $e->getMessage());
            wp_send_json_error('Error al obtener el conteo: ' . $e->getMessage());
        }
    }

    /**
     * Manejar AJAX para importar bloques de registros desde Algolia
     */
    public function ajax_bulk_import_block() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos suficientes para realizar esta acción.');
        }

        check_ajax_referer('lexhoy_bulk_import_block', 'nonce');

        $block = isset($_POST['block']) ? intval($_POST['block']) : 1;
        $overwrite = isset($_POST['overwrite']) ? boolval($_POST['overwrite']) : false;

        $this->custom_log("AJAX: Iniciando importación del bloque {$block} (overwrite: " . ($overwrite ? 'SI' : 'NO') . ")");

        try {
            if (!$this->algolia_client) {
                throw new Exception('Cliente de Algolia no inicializado.');
            }

            // Obtener solo los registros de este bloque específico
            $this->custom_log("AJAX: Obteniendo página {$block} de Algolia...");
            $result = $this->algolia_client->browse_page($block - 1, 200);
            
            if (!$result['success']) {
                throw new Exception('Error al obtener registros de Algolia: ' . $result['message']);
            }

            $hits = $result['hits'];
            $this->custom_log("AJAX: Obtenidos " . count($hits) . " registros de Algolia");

            $imported_records = 0;
            $created_records = 0;
            $updated_records = 0;
            $skipped_records = 0;
            $error_details = array();

            foreach ($hits as $index => $record) {
                try {
                    if (!isset($record['objectID'])) {
                        $error_details[] = "Registro sin objectID en posición {$index}";
                        continue;
                    }

                    $objectID = $record['objectID'];
                    $this->custom_log("AJAX: Procesando registro {$objectID}...");

                    // Verificar si el despacho ya existe
                    $existing_post = get_posts(array(
                        'post_type' => 'despacho',
                        'meta_key' => '_algolia_object_id',
                        'meta_value' => $objectID,
                        'post_status' => 'any',
                        'numberposts' => 1,
                        'fields' => 'ids'
                    ));

                    if ($existing_post && !$overwrite) {
                        // Ya existe y no queremos sobrescribir
                        $skipped_records++;
                        $this->custom_log("AJAX: Registro {$objectID} ya existe, saltando");
                        continue;
                    } elseif ($existing_post) {
                        $updated_records++;
                        $this->custom_log("AJAX: Actualizando registro existente {$objectID}");
                    } else {
                        $created_records++;
                        $this->custom_log("AJAX: Creando nuevo registro {$objectID}");
                    }

                    $this->sync_from_algolia($objectID);
                    $imported_records++;
                    $this->custom_log("AJAX: Registro {$objectID} procesado exitosamente");

                } catch (Exception $e) {
                    $error_msg = "Error en registro {$index} (ID: " . ($record['objectID'] ?? 'sin ID') . "): " . $e->getMessage();
                    $error_details[] = $error_msg;
                    $this->custom_log("AJAX ERROR: {$error_msg}");
                }
            }

            $this->custom_log("AJAX: Bloque {$block} completado - Procesados: {$imported_records}, Creados: {$created_records}, Actualizados: {$updated_records}, Saltados: {$skipped_records}, Errores: " . count($error_details));

            wp_send_json_success(array(
                'processed' => $imported_records,
                'created' => $created_records,
                'updated' => $updated_records,
                'skipped' => $skipped_records,
                'errors' => count($error_details),
                'error_details' => $error_details
            ));

        } catch (Exception $e) {
            $error_msg = 'Error al importar bloque: ' . $e->getMessage();
            $this->custom_log("AJAX FATAL ERROR: {$error_msg}");
            wp_send_json_error($error_msg);
        }
    }
} 
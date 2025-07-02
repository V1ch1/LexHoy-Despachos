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
        
        // Modificar títulos de páginas de despachos individuales
        add_filter('document_title_parts', array($this, 'modify_despacho_page_title'), 10, 1);
        add_filter('wp_title', array($this, 'modify_despacho_wp_title'), 10, 2);
        add_action('wp_head', array($this, 'add_despacho_page_meta'));


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
        $foto_perfil = get_post_meta($post->ID, '_despacho_foto_perfil', true);

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
                    <option value="pendiente" <?php selected($estado_verificacion, 'pendiente'); ?>>Pendiente verificación</option>
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

            <!-- NUEVO: Foto de Perfil -->
            <p>
                <label for="despacho_foto_perfil">Foto de Perfil:</label><br>
                <input type="url" id="despacho_foto_perfil" name="despacho_foto_perfil" 
                       value="<?php echo esc_attr($foto_perfil); ?>" class="widefat">
                <span class="description">URL de la foto de perfil del despacho (opcional)</span>
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
            'despacho_estado_registro' => '_despacho_estado_registro',
            'despacho_foto_perfil' => '_despacho_foto_perfil'
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
    public function sync_to_algolia($post_id) {
        // Verificar que no sea una revisión automática
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Verificar que sea un despacho
        if (get_post_type($post_id) !== 'despacho') {
            return;
        }

        // No hacer nada si hay una importación en progreso
        if ($this->import_in_progress) {
            $this->custom_log("SYNC: Sincronización omitida durante importación masiva para post {$post_id}");
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
            
            // Función helper para obtener datos de POST o meta
            $posted_or_meta = function($post_key, $meta_key, $sanitize_func = 'sanitize_text_field') use ($post_id) {
                // Limpiar caché de metadatos para este post antes de leer
                wp_cache_delete($post_id, 'post_meta');
                return get_post_meta($post_id, $meta_key, true);
            };

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
                'foto_perfil'      => $posted_or_meta('despacho_foto_perfil', '_despacho_foto_perfil', 'esc_url_raw'),
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
            
            // Intentar primero con el método simple
            $total_algolia = $this->algolia_client->get_total_count_simple();
            
            // Si falla, intentar con el método sin filtrar
            if ($total_algolia === 0) {
                $this->custom_log('AJAX: Método simple falló, intentando con browse_all_unfiltered...');
                $result = $this->algolia_client->browse_all_unfiltered();
                
                if (!$result['success']) {
                    throw new Exception('Error al obtener registros de Algolia: ' . $result['message']);
                }
                
                $all_hits = $result['hits'];
                $total_algolia = count($all_hits);
            }
            
            $this->custom_log("AJAX: Total encontrado: {$total_algolia} registros");

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
        $block_size = 1000; // Cambiado a 1000 para coincidir con el límite de Algolia

        $this->custom_log("AJAX: Iniciando importación del bloque {$block} (overwrite: " . ($overwrite ? 'SI' : 'NO') . ")");

        try {
            if (!$this->algolia_client) {
                throw new Exception('Cliente de Algolia no inicializado.');
            }

            // Activar control de importación para deshabilitar sincronización
            $this->import_in_progress = true;
            $this->custom_log("AJAX: Control de importación activado - Sincronización deshabilitada");

            // Calcular qué página necesitamos obtener (cada página = 1000 registros)
            $page = $block - 1; // Página 0 = bloque 1, Página 1 = bloque 2, etc.
            
            $this->custom_log("AJAX: Obteniendo todos los registros de Algolia para procesar bloque {$block}");
            
            // Obtener todos los registros usando browse_all sin filtrar
            $result = $this->algolia_client->browse_all_unfiltered();
            
            if (!$result['success']) {
                throw new Exception('Error al obtener registros de Algolia: ' . $result['message']);
            }

            $all_hits = $result['hits'];
            $total_hits = count($all_hits);
            $this->custom_log("AJAX: Obtenidos {$total_hits} registros totales de Algolia");

            // Extraer solo los registros para este bloque (1000 registros por bloque)
            $start_index = ($block - 1) * 1000;
            $hits_to_process = array_slice($all_hits, $start_index, 1000);
            $total_to_process = count($hits_to_process);
            
            $this->custom_log("AJAX: Procesando {$total_to_process} registros del bloque {$block} (inicio en posición {$start_index})");

            if ($total_to_process === 0) {
                $this->custom_log("AJAX: No hay registros para procesar en este bloque");
                // Desactivar control de importación
                $this->import_in_progress = false;
                $this->custom_log("AJAX: Control de importación desactivado");
                
                wp_send_json_success(array(
                    'processed' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'errors' => 0,
                    'error_details' => [],
                    'finished' => true,
                    'total_records' => $total_hits,
                    'processed_so_far' => $start_index,
                    'current_block' => $block,
                    'total_blocks' => ceil($total_hits / 1000)
                ));
            }

            $imported_records = 0;
            $created_records = 0;
            $updated_records = 0;
            $skipped_records = 0;
            $error_details = array();

            foreach ($hits_to_process as $index => $record) {
                try {
                    if (!isset($record['objectID'])) {
                        $error_details[] = "Registro sin objectID en posición " . $index;
                        continue;
                    }

                    $objectID = $record['objectID'];
                    
                    // Filtrar registros vacíos o generados automáticamente sin datos
                    $nombre = trim($record['nombre'] ?? '');
                    $localidad = trim($record['localidad'] ?? '');
                    $provincia = trim($record['provincia'] ?? '');
                    $is_generated = strpos($objectID, '_dashboard_generated_id') !== false;
                    $has_data = !empty($nombre) || !empty($localidad) || !empty($provincia);
                    
                    // Saltar registros generados automáticamente sin datos
                    if ($is_generated && !$has_data) {
                        $skipped_records++;
                        $this->custom_log("AJAX: Registro {$objectID} es generado automáticamente sin datos, saltando");
                        continue;
                    }
                    
                    $this->custom_log("AJAX: Procesando registro {$objectID}...");

                    // Verificar si el despacho ya existe
                    $existing_post = get_posts(array(
                        'post_type' => 'despacho',
                        'meta_key' => 'algolia_object_id',
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

                    // Procesar el registro directamente sin usar get_object
                    $this->process_algolia_record($record);
                    
                    $imported_records++;
                    $this->custom_log("AJAX: Registro {$objectID} procesado exitosamente");

                } catch (Exception $e) {
                    $error_msg = "Error en registro " . $index . " (ID: " . ($record['objectID'] ?? 'sin ID') . "): " . $e->getMessage();
                    $error_details[] = $error_msg;
                    $this->custom_log("AJAX ERROR: {$error_msg}");
                }
            }

            // Desactivar control de importación
            $this->import_in_progress = false;
            $this->custom_log("AJAX: Control de importación desactivado");

            $this->custom_log("AJAX: Bloque {$block} completado - Procesados: {$imported_records}, Creados: {$created_records}, Actualizados: {$updated_records}, Saltados: {$skipped_records}, Errores: " . count($error_details));

            // Calcular información de paginación
            $total_records_estimate = $total_hits;
            $total_blocks = ceil($total_hits / 1000);
            $processed_so_far = ($block * 1000);
            $is_last_block = $block >= $total_blocks || $total_to_process < 1000;

            wp_send_json_success(array(
                'processed' => $imported_records,
                'created' => $created_records,
                'updated' => $updated_records,
                'skipped' => $skipped_records,
                'errors' => count($error_details),
                'error_details' => $error_details,
                'finished' => $is_last_block,
                'total_records' => $total_records_estimate,
                'processed_so_far' => $processed_so_far,
                'current_block' => $block,
                'total_blocks' => $total_blocks,
                'block_size' => 1000
            ));

        } catch (Exception $e) {
            // Asegurar que se desactive el control de importación incluso si hay error
            $this->import_in_progress = false;
            $this->custom_log("AJAX: Control de importación desactivado por error");
            
            $error_msg = 'Error al importar bloque: ' . $e->getMessage();
            $this->custom_log("AJAX FATAL ERROR: {$error_msg}");
            wp_send_json_error($error_msg);
        }
    }

    /**
     * Procesar un registro de Algolia directamente (sin usar get_object)
     */
    private function process_algolia_record($record) {
        try {
            $object_id = $record['objectID'];
            
            // Verificar si el registro tiene datos mínimos
            $nombre = trim($record['nombre'] ?? '');
            $localidad = trim($record['localidad'] ?? '');
            $provincia = trim($record['provincia'] ?? '');
            $has_minimal_data = !empty($nombre) || !empty($localidad) || !empty($provincia);
            
            // Si no tiene datos mínimos, usar un título más descriptivo
            if (!$has_minimal_data) {
                $nombre = 'Despacho sin datos - ' . $object_id;
            }
            
            // Determinar slug único
            $slug = sanitize_title($record['slug'] ?? $nombre);
            if (empty($slug)) {
                $slug = 'despacho-' . sanitize_title($object_id);
            }

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
                    'post_title'  => $nombre,
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
                'post_title'  => $nombre,
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
            update_post_meta($post_id, '_despacho_foto_perfil', $record['foto_perfil'] ?? '');

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

        } catch (Exception $e) {
            $this->custom_log("ERROR en process_algolia_record: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Eliminar todos los posts del CPT despacho
     */
    public function delete_all_posts() {
        $result = array(
            'success' => false,
            'total' => 0,
            'deleted' => 0,
            'errors' => array()
        );

        try {
            // Obtener todos los despachos
            $despachos = get_posts(array(
                'post_type' => 'despacho',
                'post_status' => 'any',
                'numberposts' => -1,
                'fields' => 'ids'
            ));

            $result['total'] = count($despachos);

            if ($result['total'] === 0) {
                $result['success'] = true;
                $result['message'] = 'No hay despachos para eliminar.';
                return $result;
            }

            // Eliminar cada despacho
            foreach ($despachos as $post_id) {
                try {
                    $delete_result = wp_delete_post($post_id, true); // true = eliminar permanentemente
                    
                    if ($delete_result) {
                        $result['deleted']++;
                    } else {
                        $result['errors'][] = array(
                            'post_id' => $post_id,
                            'error' => 'No se pudo eliminar el post'
                        );
                    }
                } catch (Exception $e) {
                    $result['errors'][] = array(
                        'post_id' => $post_id,
                        'error' => $e->getMessage()
                    );
                }
            }

            $result['success'] = true;
            $result['message'] = sprintf(
                'Eliminación completada. Total: %d, Eliminados: %d, Errores: %d',
                $result['total'],
                $result['deleted'],
                count($result['errors'])
            );

        } catch (Exception $e) {
            $result['message'] = 'Error general: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Renderizar página de migración desde Algolia
     */
    public function render_migrate_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.'));
        }

        echo '<div class="wrap">';
        echo '<h1>🚀 Migración COMPLETA: Algolia → WordPress</h1>';
        echo '<p><strong>⚠️ ATENCIÓN:</strong> Este proceso migrará TODOS los registros de Algolia a WordPress.</p>';
        echo '<p><strong>📋 INCLUYE:</strong> Registros válidos, vacíos y generados automáticamente.</p>';
        
        // Verificar configuración de Algolia
        $app_id = get_option('lexhoy_despachos_algolia_app_id');
        $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
        $search_api_key = get_option('lexhoy_despachos_algolia_search_api_key');
        $index_name = get_option('lexhoy_despachos_algolia_index_name');

        if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>❌ Configuración de Algolia incompleta.</strong></p>';
            echo '<p>Completa la configuración en <a href="' . admin_url('edit.php?post_type=despacho&page=lexhoy-despachos-algolia') . '">Configuración de Algolia</a> antes de continuar.</p>';
            echo '</div>';
            echo '</div>';
            return;
        }

        // Verificar si se está ejecutando la migración
        if (isset($_POST['action']) && $_POST['action'] === 'start_migration') {
            $this->execute_migration_from_algolia();
        } else {
            $this->show_migration_form();
        }

        echo '</div>';
    }

    private function show_migration_form() {
        try {
            require_once('class-lexhoy-algolia-client.php');
            $client = new LexhoyAlgoliaClient(
                get_option('lexhoy_despachos_algolia_app_id'),
                get_option('lexhoy_despachos_algolia_admin_api_key'),
                get_option('lexhoy_despachos_algolia_search_api_key'),
                get_option('lexhoy_despachos_algolia_index_name')
            );

            echo '<h2>📊 Análisis Preliminar</h2>';
            
            // Obtener estadísticas de Algolia
            echo '<h3>1. Analizando registros en Algolia...</h3>';
            $result = $client->browse_all_unfiltered();
            
            if (!$result['success']) {
                throw new Exception('Error al obtener registros: ' . $result['message']);
            }

            $all_hits = $result['hits'];
            $total_algolia = count($all_hits);
            
            echo '<p>Total de registros en Algolia: <strong>' . $total_algolia . '</strong></p>';

            // Analizar registros por tipo (solo para información)
            $valid_records = [];
            $empty_records = [];
            $generated_records = [];
            
            foreach ($all_hits as $hit) {
                $object_id = $hit['objectID'] ?? '';
                $nombre = trim($hit['nombre'] ?? '');
                $localidad = trim($hit['localidad'] ?? '');
                $provincia = trim($hit['provincia'] ?? '');
                $direccion = trim($hit['direccion'] ?? '');
                $telefono = trim($hit['telefono'] ?? '');
                $email = trim($hit['email'] ?? '');
                $web = trim($hit['web'] ?? '');
                $descripcion = trim($hit['descripcion'] ?? '');
                
                // Verificar si el registro está vacío
                $is_empty = empty($nombre) && 
                           empty($localidad) && 
                           empty($provincia) && 
                           empty($direccion) && 
                           empty($telefono) && 
                           empty($email) && 
                           empty($web) && 
                           empty($descripcion);
                
                // Verificar si es un registro generado automáticamente
                $is_generated = strpos($object_id, '_dashboard_generated_id') !== false;
                
                // Verificar si tiene datos mínimos válidos
                $has_minimal_data = !empty($nombre) || !empty($localidad) || !empty($provincia);
                
                if ($is_generated) {
                    $generated_records[] = $hit;
                } elseif ($is_empty) {
                    $empty_records[] = $hit;
                } elseif ($has_minimal_data) {
                    $valid_records[] = $hit;
                }
            }
            
            echo '<h3>2. Análisis de Registros (solo informativo):</h3>';
            echo '<ul>';
            echo '<li>✅ <strong>Registros válidos:</strong> <span style="color: green; font-weight: bold;">' . count($valid_records) . '</span></li>';
            echo '<li>❌ <strong>Registros vacíos:</strong> <span style="color: red;">' . count($empty_records) . '</span></li>';
            echo '<li>⚠️ <strong>Registros generados automáticamente:</strong> <span style="color: orange;">' . count($generated_records) . '</span></li>';
            echo '</ul>';
            echo '<p><strong>🎯 IMPORTANTE:</strong> Se migrarán TODOS los ' . $total_algolia . ' registros, sin importar su estado.</p>';

            // Obtener estadísticas de WordPress
            echo '<h3>3. Estado actual de WordPress:</h3>';
            $wp_despachos = get_posts(array(
                'post_type' => 'despacho',
                'post_status' => 'any',
                'numberposts' => -1,
                'fields' => 'ids'
            ));
            $total_wp = count($wp_despachos);
            echo '<p>Despachos actuales en WordPress: <strong>' . $total_wp . '</strong></p>';

            // Mostrar ejemplos de registros
            echo '<h3>4. Ejemplos de registros que se migrarán:</h3>';
            echo '<div style="max-height: 300px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px; background: #f9f9f9;">';
            for ($i = 0; $i < min(5, count($all_hits)); $i++) {
                $record = $all_hits[$i];
                $is_generated = strpos($record['objectID'] ?? '', '_dashboard_generated_id') !== false;
                $border_color = $is_generated ? 'orange' : (empty($record['nombre']) ? 'red' : 'green');
                
                echo '<div style="margin-bottom: 10px; padding: 10px; background: white; border-left: 4px solid ' . $border_color . ';">';
                echo '<strong>ID:</strong> ' . ($record['objectID'] ?? 'N/A') . '<br>';
                echo '<strong>Nombre:</strong> ' . ($record['nombre'] ?? 'N/A') . '<br>';
                echo '<strong>Localidad:</strong> ' . ($record['localidad'] ?? 'N/A') . '<br>';
                echo '<strong>Provincia:</strong> ' . ($record['provincia'] ?? 'N/A') . '<br>';
                echo '<strong>Teléfono:</strong> ' . ($record['telefono'] ?? 'N/A') . '<br>';
                echo '</div>';
            }
            echo '</div>';

            // Formulario de migración
            echo '<h2>🚀 Iniciar Migración COMPLETA</h2>';
            echo '<p><strong>Configuración de la migración:</strong></p>';
            echo '<ul>';
            echo '<li>📦 <strong>Tamaño de bloque:</strong> 50 registros por lote</li>';
            echo '<li>⏱️ <strong>Pausa entre bloques:</strong> 2 segundos</li>';
            echo '<li>🔄 <strong>Migración:</strong> TODOS los registros sin filtrado</li>';
            echo '<li>📝 <strong>Log detallado:</strong> Se muestra el progreso en tiempo real</li>';
            echo '</ul>';
            
            echo '<form method="post" style="margin-top: 20px;">';
            echo '<input type="hidden" name="action" value="start_migration">';
            echo '<button type="submit" class="button button-primary" style="background: #d63638; color: white; padding: 15px 30px; border: none; cursor: pointer; font-size: 16px; font-weight: bold;">';
            echo '🚀 MIGRAR TODOS LOS ' . $total_algolia . ' REGISTROS DE ALGOLIA';
            echo '</button>';
            echo '</form>';

        } catch (Exception $e) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>Error:</strong> ' . $e->getMessage() . '</p>';
            echo '</div>';
        }
    }

    private function execute_migration_from_algolia() {
        echo '<h2>🚀 Ejecutando Migración COMPLETA</h2>';
        
        try {
            require_once('class-lexhoy-algolia-client.php');
            $client = new LexhoyAlgoliaClient(
                get_option('lexhoy_despachos_algolia_app_id'),
                get_option('lexhoy_despachos_algolia_admin_api_key'),
                get_option('lexhoy_despachos_algolia_search_api_key'),
                get_option('lexhoy_despachos_algolia_index_name')
            );

            // Obtener TODOS los registros de Algolia
            echo '<h3>1. Obteniendo TODOS los registros de Algolia...</h3>';
            $result = $client->browse_all_unfiltered();
            
            if (!$result['success']) {
                throw new Exception('Error al obtener registros: ' . $result['message']);
            }

            $all_hits = $result['hits'];
            $total_records = count($all_hits);
            
            echo '<p>Total de registros a migrar: <strong>' . $total_records . '</strong></p>';

            if ($total_records === 0) {
                echo '<p style="color: orange;">⚠️ No hay registros para migrar.</p>';
                return;
            }

            // Configuración de la migración
            $block_size = 50;
            $total_blocks = ceil($total_records / $block_size);
            $total_created = 0;
            $total_errors = 0;
            $total_skipped = 0;

            echo '<h3>2. Iniciando migración por bloques...</h3>';
            echo '<p>Total de bloques a procesar: <strong>' . $total_blocks . '</strong></p>';
            echo '<p>Tamaño de cada bloque: <strong>' . $block_size . '</strong> registros</p>';

            // Procesar por bloques
            for ($block = 0; $block < $total_blocks; $block++) {
                $start_index = $block * $block_size;
                $end_index = min($start_index + $block_size, $total_records);
                $block_records = array_slice($all_hits, $start_index, $block_size);
                
                echo '<h4>📦 Procesando Bloque ' . ($block + 1) . ' de ' . $total_blocks . '</h4>';
                echo '<p>Registros en este bloque: <strong>' . count($block_records) . '</strong></p>';
                
                $block_created = 0;
                $block_errors = 0;
                $block_skipped = 0;
                
                foreach ($block_records as $index => $record) {
                    $record_number = $start_index + $index + 1;
                    $object_id = $record['objectID'] ?? '';
                    $nombre = trim($record['nombre'] ?? '');
                    
                    // Determinar el color del borde según el tipo de registro
                    $is_generated = strpos($object_id, '_dashboard_generated_id') !== false;
                    $border_color = $is_generated ? '#ff8c00' : (empty($nombre) ? '#dc3545' : '#28a745');
                    
                    echo '<div style="margin: 5px 0; padding: 5px; background: #f0f0f0; border-left: 3px solid ' . $border_color . ';">';
                    echo '<strong>Registro ' . $record_number . '/' . $total_records . ':</strong> ' . ($nombre ?: 'Sin nombre') . ' (ID: ' . $object_id . ')';
                    
                    try {
                        // Verificar si ya existe en WordPress
                        $existing_posts = get_posts(array(
                            'post_type' => 'despacho',
                            'meta_query' => array(
                                array(
                                    'key' => 'algolia_object_id',
                                    'value' => $object_id,
                                    'compare' => '='
                                )
                            ),
                            'post_status' => 'any',
                            'numberposts' => 1
                        ));
                        
                        if (!empty($existing_posts)) {
                            echo ' → <span style="color: orange;">⚠️ Ya existe en WordPress (se omite)</span>';
                            $block_skipped++;
                            $total_skipped++;
                        } else {
                            // Crear el despacho en WordPress
                            $post_data = array(
                                'post_title' => $nombre ?: 'Despacho sin nombre',
                                'post_content' => $record['descripcion'] ?? '',
                                'post_status' => 'publish',
                                'post_type' => 'despacho'
                            );
                            
                            $post_id = wp_insert_post($post_data);
                            
                            if ($post_id && !is_wp_error($post_id)) {
                                // Guardar metadatos
                                $meta_fields = array(
                                    'localidad' => trim($record['localidad'] ?? ''),
                                    'provincia' => trim($record['provincia'] ?? ''),
                                    'codigo_postal' => trim($record['codigo_postal'] ?? ''),
                                    'direccion' => trim($record['direccion'] ?? ''),
                                    'telefono' => trim($record['telefono'] ?? ''),
                                    'email' => trim($record['email'] ?? ''),
                                    'web' => trim($record['web'] ?? ''),
                                    'estado_verificacion' => trim($record['estado_verificacion'] ?? ''),
                                    'isVerified' => trim($record['isVerified'] ?? ''),
                                    'experiencia' => trim($record['experiencia'] ?? ''),
                                    'tamaño_despacho' => trim($record['tamaño_despacho'] ?? ''),
                                    'año_fundacion' => trim($record['año_fundacion'] ?? ''),
                                    'estado_registro' => trim($record['estado_registro'] ?? ''),
                                    'ultima_actualizacion' => trim($record['ultima_actualizacion'] ?? ''),
                                    'algolia_object_id' => $object_id,
                                    'algolia_slug' => trim($record['slug'] ?? '')
                                );
                                
                                foreach ($meta_fields as $key => $value) {
                                    update_post_meta($post_id, $key, $value);
                                }

                                // Guardar arrays como JSON
                                if (!empty($record['areas_practica'])) {
                                    update_post_meta($post_id, 'areas_practica', json_encode($record['areas_practica']));
                                }
                                if (!empty($record['especialidades'])) {
                                    update_post_meta($post_id, 'especialidades', json_encode($record['especialidades']));
                                }
                                if (!empty($record['horario'])) {
                                    update_post_meta($post_id, 'horario', json_encode($record['horario']));
                                }
                                if (!empty($record['redes_sociales'])) {
                                    update_post_meta($post_id, 'redes_sociales', json_encode($record['redes_sociales']));
                                }
                                
                                echo ' → <span style="color: green;">✅ Creado exitosamente (ID: ' . $post_id . ')</span>';
                                $block_created++;
                                $total_created++;
                            } else {
                                echo ' → <span style="color: red;">❌ Error al crear: ' . (is_wp_error($post_id) ? $post_id->get_error_message() : 'Error desconocido') . '</span>';
                                $block_errors++;
                                $total_errors++;
                            }
                        }
                    } catch (Exception $e) {
                        echo ' → <span style="color: red;">❌ Error: ' . $e->getMessage() . '</span>';
                        $block_errors++;
                        $total_errors++;
                    }
                    
                    echo '</div>';
                    
                    // Flush output para mostrar progreso en tiempo real
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                }
                
                echo '<p><strong>Resumen del bloque ' . ($block + 1) . ':</strong></p>';
                echo '<ul>';
                echo '<li>✅ Creados: <strong>' . $block_created . '</strong></li>';
                echo '<li>⚠️ Omitidos: <strong>' . $block_skipped . '</strong></li>';
                echo '<li>❌ Errores: <strong>' . $block_errors . '</strong></li>';
                echo '</ul>';
                
                // Pausa entre bloques (excepto el último)
                if ($block < $total_blocks - 1) {
                    echo '<p>⏱️ Pausa de 2 segundos antes del siguiente bloque...</p>';
                    sleep(2);
                }
            }
            
            // Resumen final
            echo '<h3>3. Resumen Final de la Migración COMPLETA</h3>';
            echo '<div style="background: #f0f8ff; padding: 20px; border-left: 4px solid #0073aa;">';
            echo '<h4>📊 Estadísticas Totales:</h4>';
            echo '<ul>';
            echo '<li>✅ <strong>Despachos creados:</strong> <span style="color: green; font-size: 18px;">' . $total_created . '</span></li>';
            echo '<li>⚠️ <strong>Despachos omitidos (ya existían):</strong> <span style="color: orange;">' . $total_skipped . '</span></li>';
            echo '<li>❌ <strong>Errores:</strong> <span style="color: red;">' . $total_errors . '</span></li>';
            echo '<li>📦 <strong>Bloques procesados:</strong> ' . $total_blocks . '</li>';
            echo '<li>📈 <strong>Total procesados:</strong> ' . $total_records . '</li>';
            echo '</ul>';
            echo '</div>';
            
            // Verificar estado final
            $final_wp_count = get_posts(array(
                'post_type' => 'despacho',
                'post_status' => 'any',
                'numberposts' => -1,
                'fields' => 'ids'
            ));
            $final_count = count($final_wp_count);
            
            echo '<p><strong>Estado final de WordPress:</strong> <span style="color: green; font-size: 18px;">' . $final_count . ' despachos</span></p>';
            
            if ($total_created > 0) {
                echo '<p style="color: green; font-size: 18px;">🎉 ¡Migración COMPLETA completada exitosamente!</p>';
            }

        } catch (Exception $e) {
            echo '<h3>Error durante la migración:</h3>';
            echo '<p style="color: red;">' . $e->getMessage() . '</p>';
        }
    }

    /**
     * Registrar submenú para importación masiva desde Algolia
     */
    public function register_import_submenu() {
        add_submenu_page(
            'edit.php?post_type=despacho',
            'Importación Masiva desde Algolia',
            'Importación Masiva',
            'manage_options',
            'lexhoy-bulk-import',
            array($this, 'render_bulk_import_page')
        );
        
        add_submenu_page(
            'edit.php?post_type=despacho',
            'Limpieza de Registros sin Nombre',
            'Limpiar sin Nombre',
            'manage_options',
            'lexhoy-clean-without-name',
            array($this, 'render_clean_without_name_page')
        );
        
        add_submenu_page(
            'edit.php?post_type=despacho',
            'Añadir Fotos de Perfil',
            'Añadir Fotos',
            'manage_options',
            'lexhoy-add-photos',
            array($this, 'render_add_photos_page')
        );
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
            
            // Intentar primero con el método simple
            $total_algolia = $algolia_client->get_total_count_simple();
            
            // Si falla, intentar con el método sin filtrar
            if ($total_algolia === 0) {
                $result = $algolia_client->browse_all_unfiltered();
                $total_algolia = $result['success'] ? count($result['hits']) : 0;
            }
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
            <p>La importación se realizará en <strong>bloques de 1000 registros</strong> para optimizar el rendimiento y evitar timeouts.</p>
            <p><strong>📋 Proceso:</strong></p>
            <ul>
                <li>Cada bloque procesa hasta 1000 registros de Algolia</li>
                <li>Se filtran automáticamente los registros vacíos</li>
                <li>La sincronización con Algolia se deshabilita durante la importación</li>
                <li>Progreso en tiempo real con estadísticas detalladas</li>
            </ul>
            
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

            logMessage('🚀 Iniciando importación masiva por bloques de 1000...');
            
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
                        totalBlocks = Math.ceil(totalRecords / 1000);
                        logMessage(`📊 Total de registros en Algolia: ${totalRecords.toLocaleString()}`);
                        logMessage(`📦 Se procesarán ${totalBlocks} bloques de 1000 registros`);
                        logMessage(`📋 Cada bloque procesará hasta 1000 registros de Algolia`);
                        
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
            const startRecord = (currentBlock - 1) * 1000 + 1;
            const endRecord = Math.min(currentBlock * 1000, totalRecords);
            
            logMessage(`\n🔄 Procesando bloque ${currentBlock}/${totalBlocks} (registros ${startRecord.toLocaleString()}-${endRecord.toLocaleString()})`);
            
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
                        
                        logMessage(`✅ Bloque ${currentBlock}/${data.total_blocks || totalBlocks} completado:`);
                        logMessage(`   • Procesados: ${data.processed.toLocaleString()}`);
                        logMessage(`   • Creados: ${data.created.toLocaleString()}`);
                        logMessage(`   • Actualizados: ${data.updated.toLocaleString()}`);
                        logMessage(`   • Saltados: ${(data.skipped || 0).toLocaleString()}`);
                        logMessage(`   • Errores: ${data.errors}`);
                        
                        if (data.error_details && data.error_details.length > 0) {
                            data.error_details.forEach(error => {
                                logMessage(`   ⚠️ ${error}`);
                            });
                        }
                        
                        // Actualizar barra de progreso con información más precisa
                        const progress = data.finished ? 100 : (data.processed_so_far / data.total_records) * 100;
                        document.getElementById('progress-bar').style.width = progress + '%';
                        document.getElementById('progress-text').textContent = 
                            `Progreso: ${data.processed_so_far.toLocaleString()}/${data.total_records.toLocaleString()} (${Math.round(progress)}%) - Bloque ${data.current_block}/${data.total_blocks}`;
                        
                        // Verificar si la importación ha terminado
                        if (data.finished) {
                            logMessage('✅ ¡Importación completada!');
                            logMessage(`📊 Resumen final:`);
                            logMessage(`   • Total de registros procesados: ${data.processed_so_far.toLocaleString()}`);
                            logMessage(`   • Último bloque procesado: ${data.current_block}/${data.total_blocks}`);
                            logMessage(`   • Tamaño de bloque: ${data.block_size} registros`);
                            finishImport();
                            return;
                        }
                        
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

    /**
     * Renderizar página de limpieza de registros generados automáticamente
     */
    public function render_clean_generated_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.'));
        }

        echo '<div class="wrap">';
        echo '<h1>🧹 Limpieza de Registros Generados Automáticamente</h1>';
        echo '<p><strong>⚠️ ATENCIÓN:</strong> Este proceso eliminará registros generados automáticamente sin datos de Algolia.</p>';
        
        // Verificar configuración de Algolia
        $app_id = get_option('lexhoy_despachos_algolia_app_id');
        $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
        $index_name = get_option('lexhoy_despachos_algolia_index_name');

        if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>❌ Configuración de Algolia incompleta.</strong></p>';
            echo '<p>Completa la configuración en <a href="' . admin_url('edit.php?post_type=despacho&page=lexhoy-despachos-algolia') . '">Configuración de Algolia</a> antes de continuar.</p>';
            echo '</div>';
            echo '</div>';
            return;
        }

        // Verificar si se está ejecutando la limpieza
        if (isset($_POST['action']) && $_POST['action'] === 'clean_generated') {
            $this->execute_clean_generated_records();
        } else {
            $this->show_clean_generated_form();
        }

        echo '</div>';
    }

    private function show_clean_generated_form() {
        try {
            require_once(plugin_dir_path(__FILE__) . 'class-lexhoy-algolia-client.php');
            $client = new LexhoyAlgoliaClient(
                get_option('lexhoy_despachos_algolia_app_id'),
                get_option('lexhoy_despachos_algolia_admin_api_key'),
                get_option('lexhoy_despachos_algolia_search_api_key'),
                get_option('lexhoy_despachos_algolia_index_name')
            );

            echo '<h2>📊 Análisis Preliminar</h2>';
            
            // Obtener conteo total
            $total_count = $client->get_total_count_simple();
            echo '<p><strong>Total de registros en Algolia:</strong> ' . number_format($total_count) . '</p>';

            // Obtener algunos registros para análisis
            $sample_records = $client->browse_page_unfiltered(0, 100);
            
            if (empty($sample_records)) {
                echo '<p>No se encontraron registros para analizar.</p>';
                return;
            }

            $generated_count = 0;
            $valid_count = 0;
            
            foreach ($sample_records as $record) {
                $object_id = $record['objectID'] ?? '';
                $nombre = trim($record['nombre'] ?? '');
                $localidad = trim($record['localidad'] ?? '');
                $provincia = trim($record['provincia'] ?? '');
                
                $is_generated = strpos($object_id, '_dashboard_generated_id') !== false;
                $has_data = !empty($nombre) || !empty($localidad) || !empty($provincia);
                
                if ($is_generated && !$has_data) {
                    $generated_count++;
                } else {
                    $valid_count++;
                }
            }

            // Estimar totales basado en la muestra
            $estimated_generated = round(($generated_count / count($sample_records)) * $total_count);
            $estimated_valid = $total_count - $estimated_generated;

            echo '<div class="notice notice-info">';
            echo '<p><strong>📋 Análisis de muestra (primeros 100 registros):</strong></p>';
            echo '<ul>';
            echo '<li>Registros válidos: ' . $valid_count . '</li>';
            echo '<li>Registros generados automáticamente sin datos: ' . $generated_count . '</li>';
            echo '</ul>';
            echo '<p><strong>📊 Estimación total:</strong></p>';
            echo '<ul>';
            echo '<li>Registros válidos estimados: ~' . number_format($estimated_valid) . '</li>';
            echo '<li>Registros generados automáticamente estimados: ~' . number_format($estimated_generated) . '</li>';
            echo '</ul>';
            echo '</div>';

            if ($estimated_generated > 0) {
                echo '<form method="post" style="margin-top: 20px;">';
                echo '<input type="hidden" name="action" value="clean_generated">';
                echo '<p><strong>¿Deseas proceder con la limpieza?</strong></p>';
                echo '<p>Esto eliminará aproximadamente ' . number_format($estimated_generated) . ' registros generados automáticamente sin datos.</p>';
                echo '<input type="submit" class="button button-primary" value="🧹 Ejecutar Limpieza" onclick="return confirm(\'¿Estás seguro? Esta acción no se puede deshacer.\')">';
                echo '</form>';
            } else {
                echo '<div class="notice notice-success">';
                echo '<p>✅ No se detectaron registros generados automáticamente para limpiar.</p>';
                echo '</div>';
            }

        } catch (Exception $e) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>❌ Error al conectar con Algolia:</strong> ' . $e->getMessage() . '</p>';
            echo '</div>';
        }
    }

    private function execute_clean_generated_records() {
        try {
            require_once(plugin_dir_path(__FILE__) . 'class-lexhoy-algolia-client.php');
            $client = new LexhoyAlgoliaClient(
                get_option('lexhoy_despachos_algolia_app_id'),
                get_option('lexhoy_despachos_algolia_admin_api_key'),
                get_option('lexhoy_despachos_algolia_search_api_key'),
                get_option('lexhoy_despachos_algolia_index_name')
            );

            echo '<h2>🧹 Ejecutando Limpieza</h2>';
            echo '<p>Eliminando registros generados automáticamente sin datos...</p>';

            // Obtener todos los registros
            $result = $client->browse_all_unfiltered();
            
            if (!$result['success']) {
                echo '<div class="notice notice-error">';
                echo '<p><strong>❌ Error al obtener registros:</strong> ' . $result['message'] . '</p>';
                echo '</div>';
                return;
            }
            
            $all_records = $result['hits'];
            
            if (empty($all_records)) {
                echo '<p>No se encontraron registros para procesar.</p>';
                return;
            }
            
            echo '<p>Total de registros encontrados: ' . count($all_records) . '</p>';
            
            $generated_records = array();
            $valid_records = array();
            
            // Filtrar registros generados automáticamente
            foreach ($all_records as $record) {
                $object_id = $record['objectID'] ?? '';
                $nombre = trim($record['nombre'] ?? '');
                $localidad = trim($record['localidad'] ?? '');
                $provincia = trim($record['provincia'] ?? '');
                
                $is_generated = strpos($object_id, '_dashboard_generated_id') !== false;
                $has_data = !empty($nombre) || !empty($localidad) || !empty($provincia);
                
                if ($is_generated && !$has_data) {
                    $generated_records[] = $object_id;
                } else {
                    $valid_records[] = $record;
                }
            }
            
            echo '<p>Registros generados automáticamente sin datos: ' . count($generated_records) . '</p>';
            echo '<p>Registros válidos: ' . count($valid_records) . '</p>';
            
            if (empty($generated_records)) {
                echo '<div class="notice notice-success">';
                echo '<p>✅ No hay registros generados automáticamente para eliminar.</p>';
                echo '</div>';
                return;
            }
            
            // Eliminar registros generados automáticamente
            echo '<p>Eliminando registros generados automáticamente...</p>';
            
            $deleted_count = 0;
            $error_count = 0;
            
            foreach ($generated_records as $object_id) {
                try {
                    $result = $client->delete_object($client->get_index_name(), $object_id);
                    if ($result) {
                        $deleted_count++;
                        echo '<p>✅ Eliminado: ' . $object_id . '</p>';
                    } else {
                        $error_count++;
                        echo '<p>❌ Error al eliminar: ' . $object_id . '</p>';
                    }
                } catch (Exception $e) {
                    $error_count++;
                    echo '<p>❌ Excepción al eliminar ' . $object_id . ': ' . $e->getMessage() . '</p>';
                }
            }
            
            echo '<h3>📊 Resumen de Limpieza</h3>';
            echo '<p>✅ Registros eliminados: ' . $deleted_count . '</p>';
            echo '<p>❌ Errores: ' . $error_count . '</p>';
            echo '<p>📋 Registros válidos restantes: ' . count($valid_records) . '</p>';
            
            if ($deleted_count > 0) {
                echo '<div class="notice notice-success">';
                echo '<p>🎉 Limpieza completada exitosamente.</p>';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>❌ Error general:</strong> ' . $e->getMessage() . '</p>';
            echo '</div>';
        }
    }

    /**
     * Renderizar página de limpieza de registros sin nombre
     */
    public function render_clean_without_name_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.'));
        }

        echo '<div class="wrap">';
        echo '<h1>🧹 Limpieza de Registros sin Nombre en Algolia</h1>';
        echo '<p>Esta herramienta eliminará todos los registros de Algolia que no tengan el campo "nombre" con datos.</p>';

        // Verificar configuración de Algolia
        $app_id = get_option('lexhoy_despachos_algolia_app_id');
        $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
        $index_name = get_option('lexhoy_despachos_algolia_index_name');

        if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
            echo '<div class="notice notice-error">';
            echo '<p>⚠️ <strong>Configuración de Algolia incompleta.</strong> Completa la configuración antes de continuar.</p>';
            echo '</div>';
            echo '</div>';
            return;
        }

        // Procesar acción si se envió el formulario
        if (isset($_POST['action']) && $_POST['action'] === 'clean_without_name') {
            $this->execute_clean_without_name_records();
        } else {
            $this->show_clean_without_name_form();
        }

        echo '</div>';
    }

    private function show_clean_without_name_form() {
        try {
            require_once(plugin_dir_path(__FILE__) . 'class-lexhoy-algolia-client.php');
            $client = new LexhoyAlgoliaClient(
                get_option('lexhoy_despachos_algolia_app_id'),
                get_option('lexhoy_despachos_algolia_admin_api_key'),
                get_option('lexhoy_despachos_algolia_search_api_key'),
                get_option('lexhoy_despachos_algolia_index_name')
            );

            echo '<h2>🔍 Analizando registros en Algolia...</h2>';
            
            // Obtener todos los registros
            $result = $client->browse_all_unfiltered();
            
            if (!$result['success']) {
                throw new Exception('Error al obtener registros: ' . $result['message']);
            }

            $all_hits = $result['hits'];
            $total_records = count($all_hits);
            
            echo '<p>📊 <strong>Total de registros encontrados:</strong> ' . number_format($total_records) . '</p>';

            // Clasificar registros
            $records_without_name = [];
            $records_with_name = [];
            
            foreach ($all_hits as $hit) {
                $nombre = trim($hit['nombre'] ?? '');
                
                if (empty($nombre)) {
                    $records_without_name[] = $hit;
                } else {
                    $records_with_name[] = $hit;
                }
            }
            
            echo '<div class="notice notice-info">';
            echo '<h3>📈 Resultados del análisis:</h3>';
            echo '<ul>';
            echo '<li>✅ <strong>Registros CON nombre:</strong> ' . number_format(count($records_with_name)) . '</li>';
            echo '<li>❌ <strong>Registros SIN nombre:</strong> ' . number_format(count($records_without_name)) . '</li>';
            echo '</ul>';
            echo '</div>';
            
            if (empty($records_without_name)) {
                echo '<div class="notice notice-success">';
                echo '<h3>🎉 ¡Excelente!</h3>';
                echo '<p>✅ No se encontraron registros sin nombre para eliminar. Todos los registros tienen el campo "nombre" con datos.</p>';
                echo '</div>';
            } else {
                echo '<h3>⚠️ Registros sin nombre encontrados (primeros 10):</h3>';
                echo '<table class="widefat">';
                echo '<thead>';
                echo '<tr>';
                echo '<th>Object ID</th>';
                echo '<th>Localidad</th>';
                echo '<th>Provincia</th>';
                echo '<th>Teléfono</th>';
                echo '<th>Estado</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                for ($i = 0; $i < min(10, count($records_without_name)); $i++) {
                    $record = $records_without_name[$i];
                    $object_id = $record['objectID'] ?? 'N/A';
                    $localidad = $record['localidad'] ?? 'N/A';
                    $provincia = $record['provincia'] ?? 'N/A';
                    $telefono = $record['telefono'] ?? 'N/A';
                    
                    // Determinar estado
                    $is_generated = strpos($object_id, '_dashboard_generated_id') !== false;
                    $has_other_data = !empty($localidad) || !empty($provincia) || !empty($telefono);
                    
                    if ($is_generated) {
                        $status = '<span style="color: orange;">🔧 Generado</span>';
                    } elseif ($has_other_data) {
                        $status = '<span style="color: blue;">📝 Con otros datos</span>';
                    } else {
                        $status = '<span style="color: red;">❌ Completamente vacío</span>';
                    }
                    
                    echo '<tr>';
                    echo '<td>' . esc_html($object_id) . '</td>';
                    echo '<td>' . esc_html($localidad) . '</td>';
                    echo '<td>' . esc_html($provincia) . '</td>';
                    echo '<td>' . esc_html($telefono) . '</td>';
                    echo '<td>' . $status . '</td>';
                    echo '</tr>';
                }
                
                if (count($records_without_name) > 10) {
                    echo '<tr><td colspan="5" style="text-align: center; background: #fff3cd;">';
                    echo '... y ' . number_format(count($records_without_name) - 10) . ' registros más sin nombre';
                    echo '</td></tr>';
                }
                echo '</tbody>';
                echo '</table>';
                
                echo '<div class="notice notice-warning">';
                echo '<h4>🚨 ¡ATENCIÓN!</h4>';
                echo '<p>Estás a punto de eliminar <strong>' . number_format(count($records_without_name)) . '</strong> registros de Algolia que no tienen el campo "nombre" con datos.</p>';
                echo '<p><strong>Esta acción NO se puede deshacer.</strong></p>';
                echo '</div>';
                
                echo '<form method="post" style="margin-top: 20px;">';
                echo '<input type="hidden" name="action" value="clean_without_name">';
                echo '<p>';
                echo '<input type="submit" class="button button-primary" value="🗑️ ELIMINAR ' . number_format(count($records_without_name)) . ' REGISTROS SIN NOMBRE" onclick="return confirm(\'¿Estás completamente seguro? Esta acción eliminará ' . count($records_without_name) . ' registros y NO se puede deshacer.\')">';
                echo '</p>';
                echo '</form>';
            }

        } catch (Exception $e) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>❌ Error:</strong> ' . esc_html($e->getMessage()) . '</p>';
            echo '</div>';
        }
    }

    private function execute_clean_without_name_records() {
        try {
            require_once(plugin_dir_path(__FILE__) . 'class-lexhoy-algolia-client.php');
            $client = new LexhoyAlgoliaClient(
                get_option('lexhoy_despachos_algolia_app_id'),
                get_option('lexhoy_despachos_algolia_admin_api_key'),
                get_option('lexhoy_despachos_algolia_search_api_key'),
                get_option('lexhoy_despachos_algolia_index_name')
            );

            echo '<h2>🗑️ Eliminando registros sin nombre...</h2>';
            echo '<div class="notice notice-info">';
            echo '<p>⚠️ <strong>Proceso en curso...</strong> Por favor no cierres esta página.</p>';
            echo '</div>';
            
            // Obtener todos los registros
            $result = $client->browse_all_unfiltered();
            
            if (!$result['success']) {
                throw new Exception('Error al obtener registros: ' . $result['message']);
            }

            $all_hits = $result['hits'];
            $records_without_name = [];
            
            // Filtrar registros sin nombre
            foreach ($all_hits as $hit) {
                $nombre = trim($hit['nombre'] ?? '');
                if (empty($nombre)) {
                    $records_without_name[] = $hit;
                }
            }
            
            if (empty($records_without_name)) {
                echo '<div class="notice notice-success">';
                echo '<p>✅ No hay registros sin nombre para eliminar.</p>';
                echo '</div>';
                return;
            }
            
            $deleted_count = 0;
            $error_count = 0;
            $batch_size = 10;
            $total_to_delete = count($records_without_name);
            
            echo '<p>Eliminando ' . number_format($total_to_delete) . ' registros en lotes de ' . $batch_size . '...</p>';
            
            for ($i = 0; $i < $total_to_delete; $i += $batch_size) {
                $batch = array_slice($records_without_name, $i, $batch_size);
                
                echo '<h4>🔄 Procesando lote ' . (floor($i / $batch_size) + 1) . ' de ' . ceil($total_to_delete / $batch_size) . '...</h4>';
                
                foreach ($batch as $record) {
                    try {
                        $object_id = $record['objectID'];
                        $result = $client->delete_object(get_option('lexhoy_despachos_algolia_index_name'), $object_id);
                        
                        if ($result) {
                            $deleted_count++;
                            echo '<span style="color: green;">✅ Eliminado: ' . esc_html($object_id) . '</span><br>';
                        } else {
                            $error_count++;
                            echo '<span style="color: red;">❌ Error eliminando: ' . esc_html($object_id) . '</span><br>';
                        }
                        
                        // Flush output
                        if (ob_get_level()) {
                            ob_flush();
                        }
                        flush();
                        
                        // Pausa pequeña
                        usleep(100000); // 100ms
                        
                    } catch (Exception $e) {
                        $error_count++;
                        echo '<span style="color: red;">❌ Error eliminando ' . esc_html($object_id) . ': ' . esc_html($e->getMessage()) . '</span><br>';
                    }
                }
                
                // Pausa entre lotes
                if ($i + $batch_size < $total_to_delete) {
                    echo '<p>⏱️ Pausa de 1 segundo...</p>';
                    sleep(1);
                }
            }
            
            echo '<h3>📊 Resumen final:</h3>';
            echo '<div class="notice ' . ($error_count > 0 ? 'notice-warning' : 'notice-success') . '">';
            echo '<ul>';
            echo '<li>✅ <strong>Registros eliminados:</strong> ' . number_format($deleted_count) . '</li>';
            echo '<li>❌ <strong>Errores:</strong> ' . number_format($error_count) . '</li>';
            echo '<li>📈 <strong>Tasa de éxito:</strong> ' . ($total_to_delete > 0 ? round(($deleted_count / $total_to_delete) * 100, 2) : 0) . '%</li>';
            echo '</ul>';
            echo '</div>';
            
            if ($deleted_count > 0) {
                echo '<div class="notice notice-success">';
                echo '<h4>🎉 ¡Limpieza completada!</h4>';
                echo '<p>Se eliminaron <strong>' . number_format($deleted_count) . '</strong> registros sin nombre de Algolia.</p>';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>❌ Error:</strong> ' . esc_html($e->getMessage()) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Modificar título de páginas de despachos individuales
     */
    public function modify_despacho_page_title($title) {
        if (is_singular('despacho')) {
            global $post;
            if ($post) {
                $nombre = get_post_meta($post->ID, '_despacho_nombre', true);
                $localidad = get_post_meta($post->ID, '_despacho_localidad', true);
                $provincia = get_post_meta($post->ID, '_despacho_provincia', true);
                
                // Usar el nombre del meta o el título del post
                $despacho_name = $nombre ?: $post->post_title;
                
                // Crear título simplificado
                $title['title'] = $despacho_name . ' - LexHoy';
            }
        }
        return $title;
    }

    /**
     * Modificar título wp_title para despachos individuales
     */
    public function modify_despacho_wp_title($title, $sep) {
        if (is_singular('despacho')) {
            global $post;
            if ($post) {
                $nombre = get_post_meta($post->ID, '_despacho_nombre', true);
                $localidad = get_post_meta($post->ID, '_despacho_localidad', true);
                $provincia = get_post_meta($post->ID, '_despacho_provincia', true);
                
                $despacho_name = $nombre ?: $post->post_title;
                
                return $despacho_name . ' - LexHoy';
            }
        }
        return $title;
    }

    /**
     * Añadir metadatos a páginas de despachos individuales
     */
    public function add_despacho_page_meta() {
        if (is_singular('despacho')) {
            global $post;
            if ($post) {
                $nombre = get_post_meta($post->ID, '_despacho_nombre', true);
                $localidad = get_post_meta($post->ID, '_despacho_localidad', true);
                $provincia = get_post_meta($post->ID, '_despacho_provincia', true);
                $descripcion = get_post_meta($post->ID, '_despacho_descripcion', true);
                
                $despacho_name = $nombre ?: $post->post_title;
                $location_parts = array_filter(array($localidad, $provincia));
                
                // Meta description
                if (!empty($descripcion)) {
                    $meta_description = wp_trim_words($descripcion, 25, '...');
                } else {
                    $meta_description = "Información de contacto y servicios del despacho de abogados " . $despacho_name;
                    if (!empty($location_parts)) {
                        $meta_description .= " en " . implode(', ', $location_parts);
                    }
                    $meta_description .= ". Encuentra abogados verificados en LexHoy.";
                }
                
                echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
                
                // Meta keywords
                $keywords = ['despacho abogados'];
                if (!empty($nombre)) $keywords[] = $nombre;
                if (!empty($localidad)) $keywords[] = 'abogados ' . $localidad;
                if (!empty($provincia)) $keywords[] = 'abogados ' . $provincia;
                $keywords[] = 'LexHoy';
                
                echo '<meta name="keywords" content="' . esc_attr(implode(', ', $keywords)) . '">' . "\n";
                
                // Open Graph tags
                echo '<meta property="og:title" content="' . esc_attr($despacho_name . (!empty($location_parts) ? ' - ' . implode(', ', $location_parts) : '') . ' - LexHoy') . '">' . "\n";
                echo '<meta property="og:description" content="' . esc_attr($meta_description) . '">' . "\n";
                echo '<meta property="og:type" content="business.business">' . "\n";
                echo '<meta property="og:url" content="' . esc_url(get_permalink($post->ID)) . '">' . "\n";
            }
        }
    }

    /**
     * Cargar plantilla personalizada para despachos individuales
     */
    public function load_single_despacho_template($template) {
        if (is_singular('despacho')) {
            return LEXHOY_DESPACHOS_PLUGIN_DIR . 'templates/single-despacho.php';
        }
        return $template;
    }
}

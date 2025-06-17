<?php
/**
 * Clase para manejar el Custom Post Type de Despachos y su sincronización con Algolia
 */
class LexhoyDespachosCPT {
    private $algolia_client;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        
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
        
        // Forzar limpieza de reglas de reescritura en la activación
        add_action('admin_init', array($this, 'maybe_flush_rewrite_rules'));
        
        // Agregar acción para limpiar reglas de reescritura manualmente
        add_action('admin_post_flush_rewrite_rules', array($this, 'manual_flush_rewrite_rules'));
        
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
            'rewrite'           => array('slug' => 'abogado', 'with_front' => false),
            'capability_type'   => 'post',
            'has_archive'       => true,
            'hierarchical'      => false,
            'menu_position'     => 5,
            'menu_icon'         => 'dashicons-building',
            'supports'          => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest'      => true,
        );

        register_post_type('despacho', $args);
        
        // Forzar limpieza de reglas de reescritura
        $this->force_flush_rewrite_rules();
    }

    /**
     * Forzar limpieza de reglas de reescritura
     */
    public function force_flush_rewrite_rules() {
        // Marcar que se necesita limpiar las reglas
        update_option('lexhoy_despachos_need_rewrite_flush', 'yes');
        
        // Limpiar las reglas inmediatamente si es posible
        if (function_exists('flush_rewrite_rules')) {
            // Limpiar reglas de reescritura
            flush_rewrite_rules();
            
            // Limpiar caché de transients relacionados con rewrite
            delete_transient('rewrite_rules');
            
            // Limpiar caché de opciones relacionadas
            delete_option('rewrite_rules');
            
            // Forzar regeneración de reglas
            global $wp_rewrite;
            if ($wp_rewrite) {
                $wp_rewrite->flush_rules();
            }
            
            error_log('Reglas de reescritura limpiadas agresivamente para despachos');
        }
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
        $ultima_actualizacion = get_post_meta($post->ID, '_despacho_ultima_actualizacion', true);
        $slug = get_post_meta($post->ID, '_despacho_slug', true);
        $especialidades = get_post_meta($post->ID, '_despacho_especialidades', true);
        $horario = get_post_meta($post->ID, '_despacho_horario', true);
        $redes_sociales = get_post_meta($post->ID, '_despacho_redes_sociales', true);
        $experiencia = get_post_meta($post->ID, '_despacho_experiencia', true);
        $tamaño_despacho = get_post_meta($post->ID, '_despacho_tamaño', true);
        $año_fundacion = get_post_meta($post->ID, '_despacho_año_fundacion', true);
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
            <p>
                <label for="despacho_ultima_actualizacion">Última Actualización:</label><br>
                <input type="text" id="despacho_ultima_actualizacion" name="despacho_ultima_actualizacion" 
                       value="<?php echo esc_attr($ultima_actualizacion); ?>" class="widefat" readonly>
                <span class="description">Se actualiza automáticamente</span>
            </p>
            <p>
                <label for="despacho_slug">Slug:</label><br>
                <input type="text" id="despacho_slug" name="despacho_slug" 
                       value="<?php echo esc_attr($slug); ?>" class="widefat">
                <span class="description">URL amigable (se genera automáticamente si está vacío)</span>
            </p>
            <p>
                <label for="despacho_especialidades">Especialidades:</label><br>
                <input type="text" id="despacho_especialidades" name="despacho_especialidades" 
                       value="<?php echo esc_attr($especialidades); ?>" class="widefat">
                <span class="description">Separar por comas (opcional)</span>
            </p>
            
            <h4>Horario</h4>
            <?php
            $dias = array('lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo');
            foreach ($dias as $dia) {
                $valor = isset($horario[$dia]) ? $horario[$dia] : '';
                ?>
                <p>
                    <label for="despacho_horario_<?php echo $dia; ?>"><?php echo ucfirst($dia); ?>:</label><br>
                    <input type="text" id="despacho_horario_<?php echo $dia; ?>" 
                           name="despacho_horario[<?php echo $dia; ?>]" 
                           value="<?php echo esc_attr($valor); ?>" class="widefat">
                    <span class="description">Ej: 9:00-18:00 (opcional)</span>
                </p>
                <?php
            }
            ?>

            <h4>Redes Sociales</h4>
            <?php
            $redes = array('facebook', 'twitter', 'linkedin', 'instagram');
            foreach ($redes as $red) {
                $valor = isset($redes_sociales[$red]) ? $redes_sociales[$red] : '';
                ?>
                <p>
                    <label for="despacho_redes_<?php echo $red; ?>"><?php echo ucfirst($red); ?>:</label><br>
                    <input type="url" id="despacho_redes_<?php echo $red; ?>" 
                           name="despacho_redes_sociales[<?php echo $red; ?>]" 
                           value="<?php echo esc_attr($valor); ?>" class="widefat">
                    <span class="description">URL del perfil (opcional)</span>
                </p>
                <?php
            }
            ?>

            <p>
                <label for="despacho_experiencia">Experiencia:</label><br>
                <textarea id="despacho_experiencia" name="despacho_experiencia" 
                          class="widefat" rows="3"><?php echo esc_textarea($experiencia); ?></textarea>
                <span class="description">Años de experiencia o información adicional (opcional)</span>
            </p>
            <p>
                <label for="despacho_tamaño">Tamaño del Despacho:</label><br>
                <input type="text" id="despacho_tamaño" name="despacho_tamaño" 
                       value="<?php echo esc_attr($tamaño_despacho); ?>" class="widefat">
                <span class="description">Ej: 5 abogados, pequeño, mediano, grande (opcional)</span>
            </p>
            <p>
                <label for="despacho_año_fundacion">Año de Fundación:</label><br>
                <input type="number" id="despacho_año_fundacion" name="despacho_año_fundacion" 
                       value="<?php echo esc_attr($año_fundacion); ?>" class="widefat" min="1800" max="<?php echo date('Y'); ?>">
                <span class="description">Año en que se fundó el despacho (opcional)</span>
            </p>
            <p>
                <label for="despacho_estado_registro">Estado del Registro:</label><br>
                <select id="despacho_estado_registro" name="despacho_estado_registro" class="widefat">
                    <option value="activo" <?php selected($estado_registro, 'activo'); ?>>Activo</option>
                    <option value="inactivo" <?php selected($estado_registro, 'inactivo'); ?>>Inactivo</option>
                    <option value="suspendido" <?php selected($estado_registro, 'suspendido'); ?>>Suspendido</option>
                </select>
            </p>
        </div>
        <?php
    }

    /**
     * Guardar meta box
     */
    public function save_meta_boxes($post_id) {
        // Verificar nonce
        if (!isset($_POST['despacho_meta_box_nonce'])) {
            return;
        }
        if (!wp_verify_nonce($_POST['despacho_meta_box_nonce'], 'despacho_meta_box')) {
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

        // Verificar que es un despacho
        if (get_post_type($post_id) !== 'despacho') {
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
            // Agregar mensaje de error
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
            
            // No guardar el post si hay errores
            return;
        }

        // Validar formato de email
        if (!empty($_POST['despacho_email']) && !is_email($_POST['despacho_email'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>Error:</strong> El formato del email no es válido.</p>';
                echo '</div>';
            });
            return;
        }

        // Validar formato de URL web
        if (!empty($_POST['despacho_web']) && !filter_var($_POST['despacho_web'], FILTER_VALIDATE_URL)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>Error:</strong> El formato de la URL web no es válido.</p>';
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
            'despacho_estado_verificacion' => '_despacho_estado_verificacion',
            'despacho_ultima_actualizacion' => '_despacho_ultima_actualizacion',
            'despacho_slug' => '_despacho_slug',
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
                } elseif ($post_field === 'despacho_descripcion' || $post_field === 'despacho_experiencia') {
                    $value = sanitize_textarea_field($_POST[$post_field]);
                }
                
                update_post_meta($post_id, $meta_field, $value);
            }
        }

        // Guardar checkbox de verificado
        $is_verified = isset($_POST['despacho_is_verified']) ? '1' : '0';
        update_post_meta($post_id, '_despacho_is_verified', $is_verified);

        // Guardar horario
        if (isset($_POST['despacho_horario']) && is_array($_POST['despacho_horario'])) {
            $horario = array();
            foreach ($_POST['despacho_horario'] as $dia => $valor) {
                $horario[$dia] = sanitize_text_field($valor);
            }
            update_post_meta($post_id, '_despacho_horario', $horario);
        }

        // Guardar redes sociales
        if (isset($_POST['despacho_redes_sociales']) && is_array($_POST['despacho_redes_sociales'])) {
            $redes_sociales = array();
            foreach ($_POST['despacho_redes_sociales'] as $red => $valor) {
                $redes_sociales[$red] = esc_url_raw($valor);
            }
            update_post_meta($post_id, '_despacho_redes_sociales', $redes_sociales);
        }

        // Actualizar última actualización automáticamente
        update_post_meta($post_id, '_despacho_ultima_actualizacion', date('d-m-Y'));

        // Generar slug automáticamente si está vacío
        $slug = get_post_meta($post_id, '_despacho_slug', true);
        if (empty($slug)) {
            $nombre = get_post_meta($post_id, '_despacho_nombre', true);
            if (!empty($nombre)) {
                $slug = sanitize_title($nombre);
                update_post_meta($post_id, '_despacho_slug', $slug);
            }
        }

        // Generar Object ID automáticamente si no existe
        $object_id = get_post_meta($post_id, '_despacho_object_id', true);
        if (empty($object_id)) {
            $object_id = 'despacho_' . $post_id . '_' . time();
            update_post_meta($post_id, '_despacho_object_id', $object_id);
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
                // En lugar de lanzar una excepción, solo registrar un error y continuar
                error_log('Configuración incompleta de Algolia. El despacho se guardó localmente pero no se sincronizó con Algolia.');
                return; // Salir sin hacer nada más
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
                'slug' => $post->post_name,
                'especialidades' => isset($meta_data['_despacho_especialidades'][0]) ? unserialize($meta_data['_despacho_especialidades'][0]) : array(),
                'horario' => isset($meta_data['_despacho_horario'][0]) ? unserialize($meta_data['_despacho_horario'][0]) : array(
                    'lunes' => '', 'martes' => '', 'miercoles' => '', 'jueves' => '', 
                    'viernes' => '', 'sabado' => '', 'domingo' => ''
                ),
                'redes_sociales' => isset($meta_data['_despacho_redes_sociales'][0]) ? unserialize($meta_data['_despacho_redes_sociales'][0]) : array(
                    'facebook' => '', 'twitter' => '', 'linkedin' => '', 'instagram' => ''
                ),
                'experiencia' => isset($meta_data['_despacho_experiencia'][0]) ? $meta_data['_despacho_experiencia'][0] : '',
                'tamaño_despacho' => isset($meta_data['_despacho_tamaño'][0]) ? $meta_data['_despacho_tamaño'][0] : '',
                'año_fundacion' => isset($meta_data['_despacho_año_fundacion'][0]) ? intval($meta_data['_despacho_año_fundacion'][0]) : 0,
                'estado_registro' => isset($meta_data['_despacho_estado_registro'][0]) ? $meta_data['_despacho_estado_registro'][0] : 'activo'
            );

            // Guardar en Algolia
            $client->save_object($index_name, $record);

        } catch (Exception $e) {
            error_log('Error al sincronizar con Algolia: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            // No lanzar la excepción para evitar que falle la creación del post
            // throw $e;
        }
    }

    /**
     * Eliminar de Algolia
     */
    public function delete_from_algolia($post_id) {
        // Verificar si es un despacho
        if (get_post_type($post_id) !== 'despacho') {
            return;
        }

        try {
            // Obtener configuración de Algolia
            $app_id = get_option('lexhoy_despachos_algolia_app_id');
            $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
            $index_name = get_option('lexhoy_despachos_algolia_index_name');

            if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
                error_log('Configuración incompleta de Algolia. No se puede eliminar el despacho de Algolia.');
                return;
            }

            // Obtener el object_id del despacho
            $object_id = get_post_meta($post_id, '_despacho_object_id', true);
            if (!$object_id) {
                // Si no hay object_id, usar el post_id como fallback
                $object_id = $post_id;
            }

            // Crear cliente de Algolia temporal
            $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, '', $index_name);

            // Eliminar de Algolia usando el método del cliente
            $result = $client->delete_object($index_name, $object_id);
            
            if ($result) {
                error_log('Despacho eliminado exitosamente de Algolia - Post ID: ' . $post_id . ', Object ID: ' . $object_id);
            } else {
                error_log('Error al eliminar despacho de Algolia - Post ID: ' . $post_id . ', Object ID: ' . $object_id);
            }

        } catch (Exception $e) {
            error_log('Error al eliminar de Algolia: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Restaurar desde la papelera - volver a sincronizar con Algolia
     */
    public function restore_from_trash($post_id) {
        // Verificar si es un despacho
        if (get_post_type($post_id) !== 'despacho') {
            return;
        }

        // Obtener el post
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        // Sincronizar con Algolia
        $this->sync_to_algolia($post_id, $post, true);
        
        error_log('Despacho restaurado y sincronizado con Algolia - Post ID: ' . $post_id);
    }

    /**
     * Sincronizar un objeto desde Algolia a WordPress
     */
    public function sync_from_algolia($object_id) {
        try {
            if (!$this->algolia_client) {
                error_log('Error de sincronización: Cliente de Algolia no inicializado');
                throw new Exception('Cliente de Algolia no inicializado');
            }

            error_log('Iniciando sincronización del objeto: ' . $object_id);

            // Obtener el objeto desde Algolia
            $object = $this->algolia_client->get_object($this->algolia_client->get_index_name(), $object_id);
            if (!$object) {
                error_log('Error de sincronización: Objeto no encontrado en Algolia - ID: ' . $object_id);
                throw new Exception('Objeto no encontrado en Algolia');
            }

            error_log('Objeto obtenido de Algolia: ' . print_r($object, true));

            // Buscar si ya existe un post con este object_id
            $existing_posts = get_posts(array(
                'post_type' => 'despacho',
                'meta_key' => '_despacho_object_id',
                'meta_value' => $object_id,
                'posts_per_page' => 1
            ));

            $post_data = array(
                'post_title' => $object['nombre'],
                'post_type' => 'despacho',
                'post_status' => 'publish'
            );

            if (!empty($existing_posts)) {
                error_log('Actualizando post existente - ID: ' . $existing_posts[0]->ID);
                // Actualizar post existente
                $post_data['ID'] = $existing_posts[0]->ID;
                $post_id = wp_update_post($post_data);
            } else {
                error_log('Creando nuevo post para objeto: ' . $object_id);
                // Crear nuevo post
                $post_id = wp_insert_post($post_data);
            }

            if (is_wp_error($post_id)) {
                error_log('Error al crear/actualizar post: ' . $post_id->get_error_message());
                throw new Exception('Error al crear/actualizar post: ' . $post_id->get_error_message());
            }

            // Actualizar meta datos
            $meta_fields = array(
                '_despacho_object_id' => $object['objectID'],
                '_despacho_nombre' => $object['nombre'],
                '_despacho_localidad' => $object['localidad'] ?? '',
                '_despacho_provincia' => $object['provincia'] ?? '',
                '_despacho_codigo_postal' => $object['codigo_postal'] ?? '',
                '_despacho_direccion' => $object['direccion'] ?? '',
                '_despacho_telefono' => $object['telefono'] ?? '',
                '_despacho_email' => $object['email'] ?? '',
                '_despacho_web' => $object['web'] ?? '',
                '_despacho_descripcion' => $object['descripcion'] ?? '',
                '_despacho_estado_verificacion' => $object['estado_verificacion'] ?? 'pendiente',
                '_despacho_is_verified' => $object['isVerified'] ?? false,
                '_despacho_ultima_actualizacion' => $object['ultima_actualizacion'] ?? date('d-m-Y'),
                '_despacho_slug' => $object['slug'] ?? '',
                '_despacho_especialidades' => $object['especialidades'] ?? array(),
                '_despacho_horario' => $object['horario'] ?? array(),
                '_despacho_redes_sociales' => $object['redes_sociales'] ?? array(),
                '_despacho_experiencia' => $object['experiencia'] ?? '',
                '_despacho_tamaño' => $object['tamaño_despacho'] ?? '',
                '_despacho_año_fundacion' => $object['año_fundacion'] ?? 0,
                '_despacho_estado_registro' => $object['estado_registro'] ?? 'activo'
            );

            foreach ($meta_fields as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }

            // Actualizar áreas de práctica como taxonomía
            if (isset($object['areas_practica']) && is_array($object['areas_practica'])) {
                $areas_practica = array();
                foreach ($object['areas_practica'] as $area) {
                    // Buscar si existe el término
                    $term = term_exists($area, 'area_practica');
                    if (!$term) {
                        // Crear el término si no existe
                        $term = wp_insert_term($area, 'area_practica');
                    }
                    if (!is_wp_error($term)) {
                        $areas_practica[] = $term['term_id'];
                    }
                }
                // Asignar las áreas al post
                wp_set_object_terms($post_id, $areas_practica, 'area_practica');
            }

            error_log('Sincronización completada exitosamente para el objeto: ' . $object_id);
            return true;
        } catch (Exception $e) {
            error_log('Error en sync_from_algolia: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Sincronizar todos los objetos desde Algolia
     */
    public function sync_all_from_algolia() {
        error_log('=== INICIO DE SYNC_ALL_FROM_ALGOLIA ===');
        
        // Verificar credenciales de Algolia
        $algolia_client = new LexhoyAlgoliaClient();
        if (!$algolia_client->verify_credentials()) {
            error_log('Error: Credenciales de Algolia no configuradas');
            return [
                'success' => false,
                'message' => 'Credenciales de Algolia no configuradas'
            ];
        }

        try {
            // Obtener un objeto de prueba de Algolia
            $result = $algolia_client->browse_all();
            
            if (!$result['success']) {
                error_log('Error al obtener objeto de prueba: ' . $result['message']);
                return [
                    'success' => false,
                    'message' => 'Error en sincronización de prueba: ' . $result['message']
                ];
            }

            $object = $result['object'];
            $total_records = $result['total_records'];

            error_log('Objeto obtenido de Algolia: ' . print_r($object, true));
            error_log('Total de registros disponibles: ' . $total_records);

            // Verificar que el objeto tenga los campos necesarios
            if (empty($object['objectID']) || empty($object['nombre'])) {
                error_log('Error: Objeto de Algolia incompleto');
                return [
                    'success' => false,
                    'message' => 'El registro de Algolia no contiene los campos necesarios'
                ];
            }

            // Buscar si ya existe un post con este objectID
            $existing_posts = get_posts([
                'post_type' => 'despacho',
                'meta_key' => '_algolia_object_id',
                'meta_value' => $object['objectID'],
                'posts_per_page' => 1
            ]);

            $post_data = [
                'post_title' => $object['nombre'],
                'post_type' => 'despacho',
                'post_status' => 'publish'
            ];

            if (!empty($existing_posts)) {
                $post_data['ID'] = $existing_posts[0]->ID;
                $post_id = wp_update_post($post_data);
                $action = 'actualizado';
            } else {
                $post_id = wp_insert_post($post_data);
                $action = 'creado';
            }

            if (is_wp_error($post_id)) {
                error_log('Error al crear/actualizar post: ' . $post_id->get_error_message());
                return [
                    'success' => false,
                    'message' => 'Error al crear/actualizar el despacho: ' . $post_id->get_error_message()
                ];
            }

            // Guardar metadatos
            $meta_fields = [
                '_algolia_object_id' => $object['objectID'],
                '_localidad' => $object['localidad'] ?? '',
                '_provincia' => $object['provincia'] ?? '',
                '_areas_practica' => $object['areas_practica'] ?? [],
                '_codigo_postal' => $object['codigo_postal'] ?? '',
                '_direccion' => $object['direccion'] ?? '',
                '_telefono' => $object['telefono'] ?? '',
                '_email' => $object['email'] ?? '',
                '_web' => $object['web'] ?? '',
                '_estado' => $object['estado'] ?? '',
                '_ultima_actualizacion' => $object['ultima_actualizacion'] ?? '',
                '_slug' => $object['slug'] ?? '',
                '_horario' => $object['horario'] ?? [],
                '_redes_sociales' => $object['redes_sociales'] ?? []
            ];

            foreach ($meta_fields as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }

            error_log('Post ' . $action . ' exitosamente. ID: ' . $post_id);

            return [
                'success' => true,
                'message' => 'Sincronización completada exitosamente',
                'post_id' => $post_id,
                'action' => $action,
                'object' => $object,
                'total_records' => $total_records
            ];

        } catch (Exception $e) {
            error_log('Error en sync_all_from_algolia: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Error en sincronización de prueba: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Programar sincronización automática
     */
    public function schedule_sync() {
        if (!wp_next_scheduled('lexhoy_despachos_sync_from_algolia')) {
            wp_schedule_event(time(), 'hourly', 'lexhoy_despachos_sync_from_algolia');
        }
    }

    /**
     * Desprogramar sincronización automática
     */
    public function unschedule_sync() {
        wp_clear_scheduled_hook('lexhoy_despachos_sync_from_algolia');
    }

    /**
     * Obtener los meta datos del post para Algolia
     */
    private function get_post_meta_for_algolia($post_id) {
        try {
            error_log('Obteniendo meta datos para post ID: ' . $post_id);
            
            $object_id = get_post_meta($post_id, '_despacho_object_id', true);
            if (empty($object_id)) {
                error_log('Error: No se encontró object_id para el post ID: ' . $post_id);
                return null;
            }

            $meta_data = array(
                'object_id' => $object_id,
                'nombre' => get_post_meta($post_id, '_despacho_nombre', true),
                'localidad' => get_post_meta($post_id, '_despacho_localidad', true),
                'provincia' => get_post_meta($post_id, '_despacho_provincia', true),
                'areas_practica' => explode(',', get_post_meta($post_id, '_despacho_areas_practica', true)),
                'codigo_postal' => get_post_meta($post_id, '_despacho_codigo_postal', true),
                'direccion' => get_post_meta($post_id, '_despacho_direccion', true),
                'telefono' => get_post_meta($post_id, '_despacho_telefono', true),
                'email' => get_post_meta($post_id, '_despacho_email', true),
                'web' => get_post_meta($post_id, '_despacho_web', true),
                'descripcion' => get_post_meta($post_id, '_despacho_descripcion', true),
                'estado_verificacion' => get_post_meta($post_id, '_despacho_estado_verificacion', true),
                'isVerified' => get_post_meta($post_id, '_despacho_is_verified', true) ? true : false,
                'ultima_actualizacion' => get_post_meta($post_id, '_despacho_ultima_actualizacion', true),
                'slug' => get_post_meta($post_id, '_despacho_slug', true),
                'especialidades' => explode(',', get_post_meta($post_id, '_despacho_especialidades', true)),
                'horario' => get_post_meta($post_id, '_despacho_horario', true),
                'redes_sociales' => get_post_meta($post_id, '_despacho_redes_sociales', true),
                'experiencia' => get_post_meta($post_id, '_despacho_experiencia', true),
                'tamaño_despacho' => get_post_meta($post_id, '_despacho_tamaño', true),
                'año_fundacion' => get_post_meta($post_id, '_despacho_año_fundacion', true),
                'estado_registro' => get_post_meta($post_id, '_despacho_estado_registro', true)
            );

            error_log('Meta datos obtenidos: ' . print_r($meta_data, true));
            return $meta_data;
        } catch (Exception $e) {
            error_log('Error al obtener meta datos: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Borrar todos los registros del CPT
     */
    public function delete_all_posts() {
        try {
            error_log('Iniciando borrado de todos los registros del CPT Despacho');
            
            $args = array(
                'post_type' => 'despacho',
                'posts_per_page' => -1,
                'fields' => 'ids'
            );
            
            $posts = get_posts($args);
            $total = count($posts);
            error_log('Encontrados ' . $total . ' posts para borrar');
            
            $deleted = 0;
            $errors = array();
            
            foreach ($posts as $post_id) {
                try {
                    // Borrar meta datos primero
                    $meta_keys = array(
                        '_despacho_object_id',
                        '_despacho_nombre',
                        '_despacho_localidad',
                        '_despacho_provincia',
                        '_despacho_areas_practica',
                        '_despacho_codigo_postal',
                        '_despacho_direccion',
                        '_despacho_telefono',
                        '_despacho_email',
                        '_despacho_web',
                        '_despacho_descripcion',
                        '_despacho_estado_verificacion',
                        '_despacho_is_verified',
                        '_despacho_ultima_actualizacion',
                        '_despacho_slug',
                        '_despacho_especialidades',
                        '_despacho_horario',
                        '_despacho_redes_sociales',
                        '_despacho_experiencia',
                        '_despacho_tamaño',
                        '_despacho_año_fundacion',
                        '_despacho_estado_registro'
                    );
                    
                    foreach ($meta_keys as $key) {
                        delete_post_meta($post_id, $key);
                    }
                    
                    // Borrar el post
                    $result = wp_delete_post($post_id, true);
                    if ($result) {
                        $deleted++;
                        error_log('Post borrado exitosamente - ID: ' . $post_id);
                    } else {
                        throw new Exception('Error al borrar el post');
                    }
                } catch (Exception $e) {
                    error_log('Error al borrar post ' . $post_id . ': ' . $e->getMessage());
                    $errors[] = array(
                        'post_id' => $post_id,
                        'error' => $e->getMessage()
                    );
                }
            }
            
            error_log('Borrado completado. Total borrados: ' . $deleted . ', Errores: ' . count($errors));
            if (!empty($errors)) {
                error_log('Errores detallados: ' . print_r($errors, true));
            }
            
            return array(
                'success' => true,
                'total' => $total,
                'deleted' => $deleted,
                'errors' => $errors
            );
            
        } catch (Exception $e) {
            error_log('Error en delete_all_posts: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Registrar taxonomías
     */
    public function register_taxonomies() {
        $labels = array(
            'name'              => 'Áreas de Práctica',
            'singular_name'     => 'Área de Práctica',
            'search_items'      => 'Buscar Áreas de Práctica',
            'all_items'         => 'Todas las Áreas de Práctica',
            'parent_item'       => 'Área de Práctica Padre',
            'parent_item_colon' => 'Área de Práctica Padre:',
            'edit_item'         => 'Editar Área de Práctica',
            'update_item'       => 'Actualizar Área de Práctica',
            'add_new_item'      => 'Añadir Nueva Área de Práctica',
            'new_item_name'     => 'Nueva Área de Práctica',
            'menu_name'         => 'Áreas de Práctica'
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'          => true,
            'show_admin_column' => true,
            'query_var'        => true,
            'rewrite'          => array('slug' => 'area-practica'),
            'show_in_rest'     => true
        );

        register_taxonomy('area_practica', array('despacho'), $args);
    }

    /**
     * Mostrar notificación si no hay configuración de Algolia
     */
    public function show_algolia_config_notice() {
        // Solo mostrar en páginas relacionadas con despachos
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('despacho', 'edit-despacho'))) {
            return;
        }

        // Mostrar mensaje de éxito si se limpiaron las reglas de reescritura
        if (isset($_GET['rewrite_flushed']) && $_GET['rewrite_flushed'] === '1') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong>✅ Reglas de reescritura actualizadas</strong><br>
                    Las URLs de los despachos ahora deberían funcionar correctamente sin el prefijo "/despacho/".
                </p>
            </div>
            <?php
        }

        $app_id = get_option('lexhoy_despachos_algolia_app_id');
        $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
        $index_name = get_option('lexhoy_despachos_algolia_index_name');

        if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong>⚠️ Configuración de Algolia incompleta</strong><br>
                    Para sincronizar los despachos con Algolia, necesitas configurar las credenciales en 
                    <a href="<?php echo admin_url('edit.php?post_type=despacho&page=lexhoy-despachos-algolia'); ?>">Configuración de Algolia</a>.
                    Los despachos se guardarán localmente pero no se sincronizarán con Algolia.
                </p>
            </div>
            <?php
        }

        // Mostrar botón para limpiar reglas de reescritura si las URLs no funcionan correctamente
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong>ℹ️ URLs de Despachos</strong><br>
                Si las URLs de los despachos siguen mostrando "/despacho/" en lugar de ser directamente "/nombre-despacho", 
                puedes limpiar las reglas de reescritura haciendo clic en el botón de abajo.
            </p>
            <p>
                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=flush_rewrite_rules'), 'flush_rewrite_rules'); ?>" 
                   class="button button-primary">
                    Limpiar Reglas de Reescritura
                </a>
            </p>
        </div>
        <?php
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
     * Forzar limpieza de reglas de reescritura si es necesario
     */
    public function maybe_flush_rewrite_rules() {
        if (get_option('lexhoy_despachos_need_rewrite_flush') === 'yes') {
            flush_rewrite_rules();
            delete_option('lexhoy_despachos_need_rewrite_flush');
        }
    }

    /**
     * Limpiar reglas de reescritura manualmente
     */
    public function manual_flush_rewrite_rules() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        // Verificar nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'flush_rewrite_rules')) {
            wp_die('Verificación de seguridad fallida.');
        }

        // Limpiar las reglas de reescritura
        flush_rewrite_rules();
        
        // Redirigir de vuelta con mensaje de éxito
        wp_redirect(admin_url('edit.php?post_type=despacho&rewrite_flushed=1'));
        exit;
    }
} 
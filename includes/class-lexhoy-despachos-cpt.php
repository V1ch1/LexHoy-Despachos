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
            'rewrite'           => array('slug' => 'despachos'),
            'capability_type'   => 'post',
            'has_archive'       => true,
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
        $object_id = get_post_meta($post->ID, '_despacho_object_id', true);
        $nombre = get_post_meta($post->ID, '_despacho_nombre', true);
        $localidad = get_post_meta($post->ID, '_despacho_localidad', true);
        $provincia = get_post_meta($post->ID, '_despacho_provincia', true);
        $areas_practica = get_post_meta($post->ID, '_despacho_areas_practica', true);
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
                <label for="despacho_object_id">Object ID:</label><br>
                <input type="text" id="despacho_object_id" name="despacho_object_id" 
                       value="<?php echo esc_attr($object_id); ?>" class="widefat">
            </p>
            <p>
                <label for="despacho_nombre">Nombre:</label><br>
                <input type="text" id="despacho_nombre" name="despacho_nombre" 
                       value="<?php echo esc_attr($nombre); ?>" class="widefat">
            </p>
            <p>
                <label for="despacho_localidad">Localidad:</label><br>
                <input type="text" id="despacho_localidad" name="despacho_localidad" 
                       value="<?php echo esc_attr($localidad); ?>" class="widefat">
            </p>
            <p>
                <label for="despacho_provincia">Provincia:</label><br>
                <input type="text" id="despacho_provincia" name="despacho_provincia" 
                       value="<?php echo esc_attr($provincia); ?>" class="widefat">
            </p>
            <p>
                <label for="despacho_areas_practica">Áreas de Práctica:</label><br>
                <input type="text" id="despacho_areas_practica" name="despacho_areas_practica" 
                       value="<?php echo esc_attr($areas_practica); ?>" class="widefat">
                <span class="description">Separar por comas</span>
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
                <label for="despacho_telefono">Teléfono:</label><br>
                <input type="tel" id="despacho_telefono" name="despacho_telefono" 
                       value="<?php echo esc_attr($telefono); ?>" class="widefat">
            </p>
            <p>
                <label for="despacho_email">Email:</label><br>
                <input type="email" id="despacho_email" name="despacho_email" 
                       value="<?php echo esc_attr($email); ?>" class="widefat">
            </p>
            <p>
                <label for="despacho_web">Web:</label><br>
                <input type="url" id="despacho_web" name="despacho_web" 
                       value="<?php echo esc_attr($web); ?>" class="widefat">
            </p>
            <p>
                <label for="despacho_descripcion">Descripción:</label><br>
                <textarea id="despacho_descripcion" name="despacho_descripcion" 
                          class="widefat" rows="3"><?php echo esc_textarea($descripcion); ?></textarea>
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
                       value="<?php echo esc_attr($ultima_actualizacion); ?>" class="widefat">
            </p>
            <p>
                <label for="despacho_slug">Slug:</label><br>
                <input type="text" id="despacho_slug" name="despacho_slug" 
                       value="<?php echo esc_attr($slug); ?>" class="widefat">
            </p>
            <p>
                <label for="despacho_especialidades">Especialidades:</label><br>
                <input type="text" id="despacho_especialidades" name="despacho_especialidades" 
                       value="<?php echo esc_attr($especialidades); ?>" class="widefat">
                <span class="description">Separar por comas</span>
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
                </p>
                <?php
            }
            ?>

            <p>
                <label for="despacho_experiencia">Experiencia:</label><br>
                <textarea id="despacho_experiencia" name="despacho_experiencia" 
                          class="widefat" rows="3"><?php echo esc_textarea($experiencia); ?></textarea>
            </p>
            <p>
                <label for="despacho_tamaño">Tamaño del Despacho:</label><br>
                <input type="text" id="despacho_tamaño" name="despacho_tamaño" 
                       value="<?php echo esc_attr($tamaño_despacho); ?>" class="widefat">
            </p>
            <p>
                <label for="despacho_año_fundacion">Año de Fundación:</label><br>
                <input type="number" id="despacho_año_fundacion" name="despacho_año_fundacion" 
                       value="<?php echo esc_attr($año_fundacion); ?>" class="widefat">
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

        // Guardar datos
        $fields = array(
            'despacho_object_id' => '_despacho_object_id',
            'despacho_nombre' => '_despacho_nombre',
            'despacho_localidad' => '_despacho_localidad',
            'despacho_provincia' => '_despacho_provincia',
            'despacho_areas_practica' => '_despacho_areas_practica',
            'despacho_codigo_postal' => '_despacho_codigo_postal',
            'despacho_direccion' => '_despacho_direccion',
            'despacho_telefono' => '_despacho_telefono',
            'despacho_email' => '_despacho_email',
            'despacho_web' => '_despacho_web',
            'despacho_descripcion' => '_despacho_descripcion',
            'despacho_estado_verificacion' => '_despacho_estado_verificacion',
            'despacho_is_verified' => '_despacho_is_verified',
            'despacho_ultima_actualizacion' => '_despacho_ultima_actualizacion',
            'despacho_slug' => '_despacho_slug',
            'despacho_especialidades' => '_despacho_especialidades',
            'despacho_experiencia' => '_despacho_experiencia',
            'despacho_tamaño' => '_despacho_tamaño',
            'despacho_año_fundacion' => '_despacho_año_fundacion',
            'despacho_estado_registro' => '_despacho_estado_registro'
        );

        foreach ($fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
            }
        }

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
    }

    /**
     * Sincronizar con Algolia
     */
    public function sync_to_algolia($post_id, $post, $update) {
        // No sincronizar si es una revisión o autoguardado
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        // Verificar si el cliente de Algolia está inicializado
        if (!$this->algolia_client) {
            error_log('Error: Cliente de Algolia no inicializado');
            return;
        }

        // Obtener todos los campos del despacho
        $despacho_data = array(
            'objectID' => get_post_meta($post_id, '_despacho_object_id', true),
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

        // Actualizar en Algolia
        $response = wp_remote_post($this->algolia_client->api_url . '/1/indexes/' . $this->algolia_client->index_name . '/objects', array(
            'headers' => array(
                'X-Algolia-API-Key' => $this->algolia_client->admin_api_key,
                'X-Algolia-Application-Id' => $this->algolia_client->app_id,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array($despacho_data))
        ));

        if (is_wp_error($response)) {
            error_log('Error al sincronizar con Algolia: ' . $response->get_error_message());
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

        // Verificar si el cliente de Algolia está inicializado
        if (!$this->algolia_client) {
            error_log('Error: Cliente de Algolia no inicializado');
            return;
        }

        $object_id = get_post_meta($post_id, '_despacho_object_id', true);
        if (!$object_id) {
            return;
        }

        // Eliminar de Algolia
        $response = wp_remote_post($this->algolia_client->api_url . '/1/indexes/' . $this->algolia_client->index_name . '/objects/delete', array(
            'headers' => array(
                'X-Algolia-API-Key' => $this->algolia_client->admin_api_key,
                'X-Algolia-Application-Id' => $this->algolia_client->app_id,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array($object_id))
        ));

        if (is_wp_error($response)) {
            error_log('Error al eliminar de Algolia: ' . $response->get_error_message());
        }
    }
} 
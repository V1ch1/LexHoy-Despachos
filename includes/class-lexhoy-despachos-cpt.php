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
        
        // Acción para sincronización programada
        add_action('lexhoy_despachos_sync_from_algolia', array($this, 'sync_all_from_algolia'));
        
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
                throw new Exception('Configuración incompleta de Algolia');
            }

            // Inicializar cliente Algolia
            $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, '', $index_name);

            // Obtener meta datos
            $meta_data = get_post_meta($post_id);
            
            // Preparar datos para Algolia
            $record = array(
                'objectID' => $post_id,
                'nombre' => get_the_title($post_id),
                'localidad' => isset($meta_data['_despacho_localidad'][0]) ? $meta_data['_despacho_localidad'][0] : '',
                'provincia' => isset($meta_data['_despacho_provincia'][0]) ? $meta_data['_despacho_provincia'][0] : '',
                'areas_practica' => isset($meta_data['_despacho_areas_practica'][0]) ? unserialize($meta_data['_despacho_areas_practica'][0]) : array(),
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
            throw $e;
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
            $this->update_post_meta_from_algolia($post_id, $object);

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
        try {
            error_log('Iniciando sincronización de prueba con un solo despacho');
            
            // Obtener las credenciales de Algolia
            $app_id = get_option('lexhoy_despachos_algolia_app_id');
            $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
            $index_name = get_option('lexhoy_despachos_algolia_index_name');

            error_log('Credenciales de Algolia:');
            error_log('App ID: ' . $app_id);
            error_log('Admin API Key: ' . substr($admin_api_key, 0, 4) . '...');
            error_log('Index Name: ' . $index_name);
            
            // Inicializar el cliente de Algolia con las credenciales
            $this->algolia_client = new LexhoyAlgoliaClient($app_id, $admin_api_key);
            
            // Obtener un solo objeto de Algolia
            $objects = $this->algolia_client->browse_all($index_name);
            
            if (empty($objects)) {
                error_log('No se encontraron objetos en Algolia');
                return array(
                    'success' => false,
                    'message' => 'No se encontraron objetos en Algolia'
                );
            }

            // Tomar solo el primer objeto para la prueba
            $test_object = $objects[0];
            error_log('Objeto de prueba obtenido: ' . print_r($test_object, true));
            
            // Verificar si el objeto tiene los campos necesarios
            if (!isset($test_object['objectID'])) {
                error_log('El objeto no tiene objectID: ' . print_r($test_object, true));
                return array(
                    'success' => false,
                    'message' => 'El objeto de Algolia no tiene objectID'
                );
            }

            // Verificar si ya existe un post con este objectID
            $existing_posts = get_posts(array(
                'post_type' => 'despacho',
                'meta_key' => 'despacho_object_id',
                'meta_value' => $test_object['objectID'],
                'posts_per_page' => 1
            ));

            if (!empty($existing_posts)) {
                error_log('El despacho ya existe con ID: ' . $existing_posts[0]->ID);
                return array(
                    'success' => true,
                    'message' => 'El despacho ya existe',
                    'post_id' => $existing_posts[0]->ID,
                    'object' => $test_object
                );
            }
            
            // Intentar crear el post
            $post_data = array(
                'post_title'    => $test_object['caratulado'] ?? $test_object['nombre'] ?? 'Sin título',
                'post_content'  => $test_object['descripcion'] ?? '',
                'post_status'   => 'publish',
                'post_type'     => 'despacho'
            );

            error_log('Intentando crear post con datos: ' . print_r($post_data, true));

            // Insertar el post
            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                error_log('Error al crear el post: ' . $post_id->get_error_message());
                return array(
                    'success' => false,
                    'message' => 'Error al crear el post: ' . $post_id->get_error_message()
                );
            }

            error_log('Post creado con ID: ' . $post_id);

            // Guardar los metadatos
            $meta_fields = array(
                'despacho_object_id' => 'objectID',
                'despacho_id' => 'id',
                'despacho_fecha' => 'fecha',
                'despacho_tipo' => 'tipo',
                'despacho_rol' => 'rol',
                'despacho_caratulado' => 'caratulado',
                'despacho_tribunal' => 'tribunal',
                'despacho_ministerio' => 'ministerio',
                'despacho_url' => 'url',
                'despacho_estado' => 'estado',
                'despacho_etiquetas' => 'etiquetas',
                'despacho_metadata' => 'metadata',
                'despacho_descripcion' => 'descripcion',
                'despacho_estado_verificacion' => 'estado_verificacion',
                'despacho_is_verified' => 'isVerified',
                'despacho_ultima_actualizacion' => 'ultima_actualizacion',
                'despacho_slug' => 'slug'
            );

            $saved_meta = array();
            foreach ($meta_fields as $meta_key => $algolia_key) {
                if (isset($test_object[$algolia_key])) {
                    $value = $test_object[$algolia_key];
                    error_log("Guardando meta {$meta_key}: " . print_r($value, true));
                    update_post_meta($post_id, $meta_key, $value);
                    $saved_meta[$meta_key] = $value;
                } else {
                    error_log("Campo {$algolia_key} no encontrado en el objeto de Algolia");
                }
            }

            // Guardar el objeto completo como metadato para referencia
            update_post_meta($post_id, 'despacho_algolia_object', $test_object);

            if (empty($saved_meta)) {
                error_log('No se guardaron metadatos para el post');
                error_log('Objeto completo de Algolia: ' . print_r($test_object, true));
                return array(
                    'success' => false,
                    'message' => 'No se encontraron meta datos para el post',
                    'object' => $test_object
                );
            }

            error_log('Sincronización de prueba completada exitosamente');
            return array(
                'success' => true,
                'message' => 'Sincronización de prueba completada exitosamente',
                'post_id' => $post_id,
                'post_data' => $post_data,
                'saved_meta' => $saved_meta,
                'object' => $test_object
            );

        } catch (Exception $e) {
            error_log('Error en sincronización de prueba: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return array(
                'success' => false,
                'message' => 'Error en sincronización de prueba: ' . $e->getMessage()
            );
        }
    }

    /**
     * Actualizar meta datos del post desde objeto de Algolia
     */
    private function update_post_meta_from_algolia($post_id, $object) {
        $meta_fields = array(
            'object_id' => $object['objectID'],
            'nombre' => $object['nombre'],
            'localidad' => $object['localidad'],
            'provincia' => $object['provincia'],
            'areas_practica' => $object['areas_practica'],
            'codigo_postal' => $object['codigo_postal'],
            'direccion' => $object['direccion'],
            'telefono' => $object['telefono'],
            'email' => $object['email'],
            'web' => $object['web'],
            'descripcion' => $object['descripcion'],
            'estado_verificacion' => $object['estado_verificacion'],
            'isVerified' => $object['isVerified'],
            'ultima_actualizacion' => $object['ultima_actualizacion'],
            'slug' => $object['slug'],
            'especialidades' => $object['especialidades'],
            'horario' => $object['horario'],
            'redes_sociales' => $object['redes_sociales'],
            'experiencia' => $object['experiencia'],
            'tamaño_despacho' => $object['tamaño_despacho'],
            'año_fundacion' => $object['año_fundacion'],
            'estado_registro' => $object['estado_registro']
        );

        foreach ($meta_fields as $field => $value) {
            update_post_meta($post_id, '_despacho_' . $field, $value);
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
} 
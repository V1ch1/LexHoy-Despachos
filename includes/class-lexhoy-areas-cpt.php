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
} 
<?php
// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Registrar opciones
function lexhoy_despachos_register_algolia_options() {
    register_setting(
        'lexhoy_despachos_settings',
        'lexhoy_despachos_settings',
        array(
            'sanitize_callback' => 'lexhoy_despachos_sanitize_settings',
            'default' => array()
        )
    );

    // Registrar secciones y campos
    add_settings_section(
        'lexhoy_despachos_main_section',
        '', // Título vacío para evitar duplicación
        'lexhoy_despachos_settings_section_callback',
        'lexhoy_despachos_settings'
    );

    add_settings_field(
        'algolia_app_id',
        'Application ID',
        'lexhoy_despachos_algolia_app_id_callback',
        'lexhoy_despachos_settings',
        'lexhoy_despachos_main_section'
    );

    add_settings_field(
        'algolia_search_api_key',
        'Search API Key',
        'lexhoy_despachos_algolia_search_api_key_callback',
        'lexhoy_despachos_settings',
        'lexhoy_despachos_main_section'
    );

    add_settings_field(
        'algolia_write_api_key',
        'Write API Key',
        'lexhoy_despachos_algolia_write_api_key_callback',
        'lexhoy_despachos_settings',
        'lexhoy_despachos_main_section'
    );

    add_settings_field(
        'algolia_admin_api_key',
        'Admin API Key',
        'lexhoy_despachos_algolia_admin_api_key_callback',
        'lexhoy_despachos_settings',
        'lexhoy_despachos_main_section'
    );

    add_settings_field(
        'algolia_usage_api_key',
        'Usage API Key',
        'lexhoy_despachos_algolia_usage_api_key_callback',
        'lexhoy_despachos_settings',
        'lexhoy_despachos_main_section'
    );
}
add_action('admin_init', 'lexhoy_despachos_register_algolia_options');

// Callbacks para las secciones y campos
function lexhoy_despachos_settings_section_callback() {
    echo '<p>Ingresa tus credenciales de Algolia para habilitar la búsqueda.</p>';
}

function lexhoy_despachos_algolia_app_id_callback() {
    $options = get_option('lexhoy_despachos_settings');
    $value = isset($options['algolia_app_id']) ? $options['algolia_app_id'] : '';
    echo '<input type="text" name="lexhoy_despachos_settings[algolia_app_id]" value="' . esc_attr($value) . '" class="regular-text">';
    echo '<p class="description">This is your unique application identifier. It\'s used to identify your application when using Algolia\'s API.</p>';
}

function lexhoy_despachos_algolia_search_api_key_callback() {
    $options = get_option('lexhoy_despachos_settings');
    $value = isset($options['algolia_search_api_key']) ? $options['algolia_search_api_key'] : '';
    echo '<input type="password" name="lexhoy_despachos_settings[algolia_search_api_key]" value="' . esc_attr($value) . '" class="regular-text">';
    echo '<p class="description">This is the public API key which can be safely used in your frontend code. This key is usable for search queries and it\'s also able to list the indices you\'ve got access to.</p>';
}

function lexhoy_despachos_algolia_write_api_key_callback() {
    $options = get_option('lexhoy_despachos_settings');
    $value = isset($options['algolia_write_api_key']) ? $options['algolia_write_api_key'] : '';
    echo '<input type="password" name="lexhoy_despachos_settings[algolia_write_api_key]" value="' . esc_attr($value) . '" class="regular-text">';
    echo '<p class="description">This is a private API key. Please keep it secret and use it ONLY from your backend: this key is used to create, update and DELETE your indices. You CANNOT use this key to manage your API keys.</p>';
}

function lexhoy_despachos_algolia_admin_api_key_callback() {
    $options = get_option('lexhoy_despachos_settings');
    $value = isset($options['algolia_admin_api_key']) ? $options['algolia_admin_api_key'] : '';
    echo '<input type="password" name="lexhoy_despachos_settings[algolia_admin_api_key]" value="' . esc_attr($value) . '" class="regular-text">';
    echo '<p class="description">This is the ADMIN API key. Please keep it secret and use it ONLY from your backend: this key is used to create, update and DELETE your indices. You can also use it to manage your API keys.</p>';
}

function lexhoy_despachos_algolia_usage_api_key_callback() {
    $options = get_option('lexhoy_despachos_settings');
    $value = isset($options['algolia_usage_api_key']) ? $options['algolia_usage_api_key'] : '';
    echo '<input type="password" name="lexhoy_despachos_settings[algolia_usage_api_key]" value="' . esc_attr($value) . '" class="regular-text">';
    echo '<p class="description">This key is used to access the Usage API and Logs endpoint.</p>';
}

// Función para sanitizar los campos
function lexhoy_despachos_sanitize_settings($input) {
    $sanitized = array();
    
    if (isset($input['algolia_app_id'])) {
        $sanitized['algolia_app_id'] = sanitize_text_field($input['algolia_app_id']);
    }
    
    if (isset($input['algolia_search_api_key'])) {
        $sanitized['algolia_search_api_key'] = sanitize_text_field($input['algolia_search_api_key']);
    }
    
    if (isset($input['algolia_write_api_key'])) {
        $sanitized['algolia_write_api_key'] = sanitize_text_field($input['algolia_write_api_key']);
    }
    
    if (isset($input['algolia_admin_api_key'])) {
        $sanitized['algolia_admin_api_key'] = sanitize_text_field($input['algolia_admin_api_key']);
    }
    
    if (isset($input['algolia_usage_api_key'])) {
        $sanitized['algolia_usage_api_key'] = sanitize_text_field($input['algolia_usage_api_key']);
    }
    
    return $sanitized;
}

// Agregar página de configuración
function lexhoy_despachos_add_algolia_page() {
    add_submenu_page(
        'edit.php?post_type=despacho',
        'Configuración de Algolia',
        'Algolia',
        'manage_options',
        'lexhoy-despachos-algolia',
        'lexhoy_despachos_algolia_page'
    );
}
add_action('admin_menu', 'lexhoy_despachos_add_algolia_page');

// Página de configuración
function lexhoy_despachos_algolia_page() {
    // Verificar credenciales si se envió el formulario
    if (isset($_POST['verify_credentials'])) {
        require_once LEXHOY_DESPACHOS_PLUGIN_DIR . 'includes/class-lexhoy-algolia-client.php';
        
        $options = get_option('lexhoy_despachos_settings');
        $app_id = isset($options['algolia_app_id']) ? $options['algolia_app_id'] : '';
        $admin_api_key = isset($options['algolia_admin_api_key']) ? $options['algolia_admin_api_key'] : '';

        $client = new LexhoyAlgoliaClient($app_id, $admin_api_key);
        $is_valid = $client->verify_credentials();

        if ($is_valid) {
            echo '<div class="notice notice-success"><p>Conexión exitosa con Algolia.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error al conectar con Algolia. Verifica tus credenciales.</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Configuración de Algolia</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('lexhoy_despachos_settings');
            do_settings_sections('lexhoy_despachos_settings');
            submit_button('Guardar configuración');
            ?>
        </form>

        <form method="post">
            <?php submit_button('Verificar conexión', 'secondary', 'verify_credentials'); ?>
        </form>
    </div>
    <?php
} 
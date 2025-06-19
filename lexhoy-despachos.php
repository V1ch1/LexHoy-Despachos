<?php
/**
 * Plugin Name: LexHoy Despachos
 * Plugin URI: https://lexhoy.com
 * Description: Plugin para gestionar despachos de LexHoy
 * Version: 1.0.0
 * Author: LexHoy
 * Author URI: https://lexhoy.com
 * Text Domain: lexhoy-despachos
 * Domain Path: /languages
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('LEXHOY_DESPACHOS_VERSION', '1.0.0');
define('LEXHOY_DESPACHOS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LEXHOY_DESPACHOS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Incluir archivos necesarios
require_once LEXHOY_DESPACHOS_PLUGIN_DIR . 'includes/class-lexhoy-algolia-client.php';
require_once LEXHOY_DESPACHOS_PLUGIN_DIR . 'includes/class-lexhoy-despachos-cpt.php';
require_once LEXHOY_DESPACHOS_PLUGIN_DIR . 'includes/class-lexhoy-despachos-shortcode.php';
require_once LEXHOY_DESPACHOS_PLUGIN_DIR . 'includes/class-lexhoy-areas-cpt.php';
require_once LEXHOY_DESPACHOS_PLUGIN_DIR . 'admin/algolia-page.php';
require_once LEXHOY_DESPACHOS_PLUGIN_DIR . 'admin/shortcode-page.php';

// Inicializar el plugin
function lexhoy_despachos_init() {
    // Inicializar clases principales
    
    if (class_exists('LexhoyDespachosCPT')) {
        new LexhoyDespachosCPT();
    }
    
    if (class_exists('LexhoyDespachosShortcode')) {
        new LexhoyDespachosShortcode();
    }

    if (class_exists('LexhoyAreasCPT')) {
        new LexhoyAreasCPT();
    }
}
add_action('plugins_loaded', 'lexhoy_despachos_init');

// Activar el plugin
function lexhoy_despachos_activate() {
    // Verificar que las clases necesarias existen
    if (!class_exists('LexhoyAlgoliaClient') || !class_exists('LexhoyDespachosCPT')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('El plugin requiere que todas las clases estén disponibles. Por favor, verifica que todos los archivos están presentes.');
    }

    // Registrar CPT y limpiar las reglas de reescritura
    new LexhoyDespachosCPT();
    flush_rewrite_rules();
    
    // Marcar que se necesita limpiar las reglas de reescritura
    update_option('lexhoy_despachos_need_rewrite_flush', 'yes');
}
register_activation_hook(__FILE__, 'lexhoy_despachos_activate');

// Desactivar el plugin
function lexhoy_despachos_deactivate() {
    // Limpiar las reglas de reescritura
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'lexhoy_despachos_deactivate');

// Desinstalar el plugin
function lexhoy_despachos_uninstall() {
    // Limpiar las opciones de Algolia
    delete_option('lexhoy_despachos_algolia_app_id');
    delete_option('lexhoy_despachos_algolia_admin_api_key');
    delete_option('lexhoy_despachos_algolia_search_api_key');
    delete_option('lexhoy_despachos_algolia_index_name');
}
register_uninstall_hook(__FILE__, 'lexhoy_despachos_uninstall'); 
<?php
/**
 * Plugin Name: LexHoy Despachos
 * Plugin URI: https://lexhoy.com
 * Description: Plugin para gestionar despachos de LexHoy
 * Version: 1.0.26
 * Author: LexHoy
 * Author URI: https://lexhoy.com
 * Text Domain: lexhoy-despachos
 * Domain Path: /languages
 * 
 * Sistema de deploy directo configurado - Test funcional
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('LEXHOY_DESPACHOS_VERSION', '1.0.26');
define('LEXHOY_DESPACHOS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LEXHOY_DESPACHOS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Incluir archivos necesarios
require_once LEXHOY_DESPACHOS_PLUGIN_DIR . 'includes/class-lexhoy-algolia-client.php';
require_once LEXHOY_DESPACHOS_PLUGIN_DIR . 'includes/class-lexhoy-despachos-cpt.php';
require_once LEXHOY_DESPACHOS_PLUGIN_DIR . 'includes/class-lexhoy-despachos-shortcode.php';
require_once LEXHOY_DESPACHOS_PLUGIN_DIR . 'includes/class-lexhoy-areas-cpt.php';
require_once LEXHOY_DESPACHOS_PLUGIN_DIR . 'admin/algolia-page.php';
require_once LEXHOY_DESPACHOS_PLUGIN_DIR . 'admin/shortcode-page.php';
require_once LEXHOY_DESPACHOS_PLUGIN_DIR . 'auto-update.php';
require_once LEXHOY_DESPACHOS_PLUGIN_DIR . 'auto-deploy.php';

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

// Sistema de actualizaciones automáticas desde GitHub
add_filter('pre_set_site_transient_update_plugins', 'lexhoy_check_github_updates');

function lexhoy_check_github_updates($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_slug = basename(dirname(__FILE__)) . '/' . basename(__FILE__);
    
    // Verificar cada 1 hora para detectar actualizaciones más rápido
    $last_check = get_option('lexhoy_last_update_check', 0);
    if (time() - $last_check < 3600) {
        return $transient;
    }
    
    update_option('lexhoy_last_update_check', time());
    
    // URL específica de tu repositorio
    $github_url = 'https://api.github.com/repos/V1ch1/LexHoy-Despachos/releases/latest';
    
    $response = wp_remote_get($github_url, array(
        'timeout' => 15,
        'headers' => array(
            'User-Agent' => 'WordPress/' . get_bloginfo('version')
        )
    ));
    
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $release = json_decode(wp_remote_retrieve_body($response));
        
        if ($release && isset($release->tag_name)) {
            // Comparar versiones
            $current_version = defined('LEXHOY_DESPACHOS_VERSION') ? LEXHOY_DESPACHOS_VERSION : '1.0.0';
            
            if (version_compare($current_version, $release->tag_name, '<')) {
                // Buscar el ZIP del plugin en los assets
                $download_url = '';
                if (isset($release->assets) && is_array($release->assets)) {
                    foreach ($release->assets as $asset) {
                        if (strpos($asset->name, 'lexhoy-despachos.zip') !== false) {
                            $download_url = $asset->browser_download_url;
                            break;
                        }
                    }
                }
                
                // Si no encontramos el ZIP específico, usar el primer asset
                if (empty($download_url) && isset($release->assets[0])) {
                    $download_url = $release->assets[0]->browser_download_url;
                }
                
                if (!empty($download_url)) {
                    $transient->response[$plugin_slug] = (object) array(
                        'slug' => basename(dirname(__FILE__)),
                        'new_version' => $release->tag_name,
                        'url' => 'https://github.com/V1ch1/LexHoy-Despachos',
                        'package' => $download_url,
                        'requires' => '5.0',
                        'tested' => '6.4',
                        'last_updated' => $release->published_at
                    );
                }
            }
        }
    }
    
    return $transient;
} 
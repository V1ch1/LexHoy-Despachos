<?php
/**
 * Archivo de configuración de ejemplo para LexHoy Despachos
 * 
 * Este archivo muestra cómo configurar las opciones de Algolia programáticamente.
 * Puedes usar este código en tu archivo functions.php o en un plugin personalizado.
 */

// Configurar credenciales de Algolia programáticamente
function configurar_algolia_ejemplo() {
    // Solo ejecutar si las opciones no están ya configuradas
    if (!get_option('lexhoy_despachos_algolia_app_id')) {
        // Reemplaza estos valores con tus credenciales reales de Algolia
        update_option('lexhoy_despachos_algolia_app_id', 'TU_APP_ID_AQUI');
        update_option('lexhoy_despachos_algolia_admin_api_key', 'TU_ADMIN_API_KEY_AQUI');
        update_option('lexhoy_despachos_algolia_search_api_key', 'TU_SEARCH_API_KEY_AQUI');
        update_option('lexhoy_despachos_algolia_index_name', 'despachos');
        
        // Opciones adicionales (opcionales)
        update_option('lexhoy_despachos_algolia_write_api_key', 'TU_WRITE_API_KEY_AQUI');
        update_option('lexhoy_despachos_algolia_usage_api_key', 'TU_USAGE_API_KEY_AQUI');
        update_option('lexhoy_despachos_algolia_monitoring_api_key', 'TU_MONITORING_API_KEY_AQUI');
    }
}

// Descomenta la línea siguiente para ejecutar la configuración automática
// add_action('init', 'configurar_algolia_ejemplo');

/**
 * Función para verificar si Algolia está configurado correctamente
 */
function verificar_configuracion_algolia() {
    $app_id = get_option('lexhoy_despachos_algolia_app_id');
    $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
    $index_name = get_option('lexhoy_despachos_algolia_index_name');
    
    if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
        return false;
    }
    
    return true;
}

/**
 * Función para obtener el estado de la configuración
 */
function obtener_estado_configuracion_algolia() {
    $configurado = verificar_configuracion_algolia();
    
    if ($configurado) {
        return array(
            'status' => 'configurado',
            'message' => 'Algolia está configurado correctamente',
            'app_id' => get_option('lexhoy_despachos_algolia_app_id'),
            'index_name' => get_option('lexhoy_despachos_algolia_index_name')
        );
    } else {
        return array(
            'status' => 'no_configurado',
            'message' => 'Algolia no está configurado. Ve a Despachos > Configuración de Algolia',
            'missing_fields' => array()
        );
    }
}

/**
 * Ejemplo de uso en el frontend
 */
function mostrar_estado_algolia() {
    $estado = obtener_estado_configuracion_algolia();
    
    if ($estado['status'] === 'configurado') {
        echo '<div class="notice notice-success">';
        echo '<p>✅ ' . esc_html($estado['message']) . '</p>';
        echo '</div>';
    } else {
        echo '<div class="notice notice-warning">';
        echo '<p>⚠️ ' . esc_html($estado['message']) . '</p>';
        echo '</div>';
    }
}

// Descomenta para mostrar el estado en el admin
// add_action('admin_notices', 'mostrar_estado_algolia'); 
<?php
// Asegurarnos de que estamos en WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Registrar opciones
register_setting('lexhoy_despachos_algolia', 'lexhoy_despachos_algolia_app_id');
register_setting('lexhoy_despachos_algolia', 'lexhoy_despachos_algolia_admin_api_key');
register_setting('lexhoy_despachos_algolia', 'lexhoy_despachos_algolia_write_api_key');
register_setting('lexhoy_despachos_algolia', 'lexhoy_despachos_algolia_search_api_key');
register_setting('lexhoy_despachos_algolia', 'lexhoy_despachos_algolia_usage_api_key');
register_setting('lexhoy_despachos_algolia', 'lexhoy_despachos_algolia_monitoring_api_key');
register_setting('lexhoy_despachos_algolia', 'lexhoy_despachos_algolia_index_name');

// Agregar página de configuración
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=despacho',
        'Configuración de Algolia',
        'Configuración de Algolia',
        'manage_options',
        'lexhoy-despachos-algolia',
        'lexhoy_despachos_algolia_page'
    );
});

// Función para verificar credenciales
function lexhoy_despachos_verify_credentials() {
    try {
        $app_id = get_option('lexhoy_despachos_algolia_app_id');
        $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
        $index_name = get_option('lexhoy_despachos_algolia_index_name');

        error_log('Verificando credenciales de Algolia:');
        error_log('App ID: ' . $app_id);
        error_log('Admin API Key: ' . substr($admin_api_key, 0, 4) . '...');
        error_log('Index Name: ' . $index_name);

        $url = "https://{$app_id}.algolia.net/1/indexes/{$index_name}/settings";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-Algolia-API-Key: ' . $admin_api_key,
            'X-Algolia-Application-Id: ' . $app_id
        ));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Error de conexión: ' . curl_error($ch));
        }
        
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('Error de Algolia (HTTP ' . $http_code . '): ' . $response);
        }

        return true;
    } catch (Exception $e) {
        error_log('Error al verificar credenciales: ' . $e->getMessage());
        return false;
    }
}

// Página de configuración
function lexhoy_despachos_algolia_page() {
    // Verificar si se está realizando una sincronización
    $is_syncing = isset($_POST['sync_from_algolia']) && check_admin_referer('lexhoy_despachos_sync_from_algolia');
    
    if ($is_syncing) {
        try {
            $app_id = get_option('lexhoy_despachos_algolia_app_id');
            $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
            $index_name = get_option('lexhoy_despachos_algolia_index_name');

            if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
                throw new Exception('Configuración incompleta de Algolia');
            }

            $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, '', $index_name);
            
            // Verificar credenciales
            if (!$client->verify_credentials()) {
                throw new Exception('Error al verificar credenciales de Algolia');
            }

            // Obtener posts de Algolia
            $hits = $client->browse_all($index_name);
            
            if (empty($hits)) {
                throw new Exception('No se encontraron registros en Algolia');
            }

            $message = '<div class="notice notice-success"><p>';
            $message .= '<strong>Sincronización completada exitosamente</strong><br>';
            $message .= 'Total de registros encontrados: ' . count($hits) . '<br><br>';
            
            // Mostrar los primeros 5 registros como ejemplo
            $message .= '<strong>Ejemplo de registros encontrados:</strong><br>';
            $count = 0;
            foreach ($hits as $hit) {
                if ($count >= 5) break;
                
                $message .= '<br><strong>Registro #' . ($count + 1) . ':</strong><br>';
                $message .= 'ID: ' . (isset($hit['objectID']) ? esc_html($hit['objectID']) : 'N/A') . '<br>';
                $message .= 'Nombre: ' . (isset($hit['nombre']) ? esc_html($hit['nombre']) : 'N/A') . '<br>';
                $message .= 'Localidad: ' . (isset($hit['localidad']) ? esc_html($hit['localidad']) : 'N/A') . '<br>';
                $message .= 'Provincia: ' . (isset($hit['provincia']) ? esc_html($hit['provincia']) : 'N/A') . '<br>';
                
                if (isset($hit['areas_practica']) && is_array($hit['areas_practica'])) {
                    $message .= 'Áreas de práctica: ' . esc_html(implode(', ', $hit['areas_practica'])) . '<br>';
                }
                
                $message .= 'Código Postal: ' . (isset($hit['codigo_postal']) ? esc_html($hit['codigo_postal']) : 'N/A') . '<br>';
                $message .= 'Dirección: ' . (isset($hit['direccion']) ? esc_html($hit['direccion']) : 'N/A') . '<br>';
                $message .= 'Teléfono: ' . (isset($hit['telefono']) ? esc_html($hit['telefono']) : 'N/A') . '<br>';
                $message .= 'Email: ' . (isset($hit['email']) ? esc_html($hit['email']) : 'N/A') . '<br>';
                $message .= 'Web: ' . (isset($hit['web']) ? esc_html($hit['web']) : 'N/A') . '<br>';
                $message .= 'Estado: ' . (isset($hit['estado_verificacion']) ? esc_html($hit['estado_verificacion']) : 'N/A') . '<br>';
                $message .= 'Última actualización: ' . (isset($hit['ultima_actualizacion']) ? esc_html($hit['ultima_actualizacion']) : 'N/A') . '<br>';
                $message .= 'Slug: ' . (isset($hit['slug']) ? esc_html($hit['slug']) : 'N/A') . '<br>';
                
                if (isset($hit['horario']) && is_array($hit['horario'])) {
                    $message .= 'Horario:<br>';
                    foreach ($hit['horario'] as $dia => $horas) {
                        $message .= '- ' . ucfirst($dia) . ': ' . esc_html($horas) . '<br>';
                    }
                }
                
                if (isset($hit['redes_sociales']) && is_array($hit['redes_sociales'])) {
                    $message .= 'Redes Sociales:<br>';
                    foreach ($hit['redes_sociales'] as $red => $url) {
                        if (!empty($url)) {
                            $message .= '- ' . ucfirst($red) . ': ' . esc_html($url) . '<br>';
                        }
                    }
                }
                
                $count++;
            }
            
            $message .= '</p></div>';
            echo $message;

        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Error durante la sincronización: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    // Verificar si se está realizando un borrado
    $is_deleting = isset($_POST['delete_all_posts']) && check_admin_referer('lexhoy_despachos_delete_all');
    
    if ($is_deleting) {
        try {
            $cpt = new LexhoyDespachosCPT();
            $result = $cpt->delete_all_posts();
            
            if ($result['success']) {
                $message = sprintf(
                    'Borrado completado. Total: %d, Borrados: %d, Errores: %d',
                    $result['total'],
                    $result['deleted'],
                    count($result['errors'])
                );
                if (!empty($result['errors'])) {
                    $message .= '<br>Errores detallados:<br>';
                    foreach ($result['errors'] as $error) {
                        $message .= sprintf('- Post %s: %s<br>', $error['post_id'], $error['error']);
                    }
                }
                echo '<div class="notice notice-success"><p>' . $message . '</p></div>';
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Error durante el borrado: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Configuración de Algolia</h1>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('lexhoy_despachos_algolia');
            do_settings_sections('lexhoy_despachos_algolia');
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">App ID</th>
                    <td>
                        <input type="text" name="lexhoy_despachos_algolia_app_id" value="<?php echo esc_attr(get_option('lexhoy_despachos_algolia_app_id')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Admin API Key</th>
                    <td>
                        <input type="password" name="lexhoy_despachos_algolia_admin_api_key" value="<?php echo esc_attr(get_option('lexhoy_despachos_algolia_admin_api_key')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Write API Key</th>
                    <td>
                        <input type="password" name="lexhoy_despachos_algolia_write_api_key" value="<?php echo esc_attr(get_option('lexhoy_despachos_algolia_write_api_key')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Search API Key</th>
                    <td>
                        <input type="password" name="lexhoy_despachos_algolia_search_api_key" value="<?php echo esc_attr(get_option('lexhoy_despachos_algolia_search_api_key')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Usage API Key</th>
                    <td>
                        <input type="password" name="lexhoy_despachos_algolia_usage_api_key" value="<?php echo esc_attr(get_option('lexhoy_despachos_algolia_usage_api_key')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Monitoring API Key</th>
                    <td>
                        <input type="password" name="lexhoy_despachos_algolia_monitoring_api_key" value="<?php echo esc_attr(get_option('lexhoy_despachos_algolia_monitoring_api_key')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Index Name</th>
                    <td>
                        <input type="text" name="lexhoy_despachos_algolia_index_name" value="<?php echo esc_attr(get_option('lexhoy_despachos_algolia_index_name')); ?>" class="regular-text">
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Guardar configuración'); ?>
        </form>

        <hr>

        <h2>Sincronización</h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('lexhoy_despachos_sync_from_algolia'); ?>
            <p>
                <button type="submit" name="sync_from_algolia" class="button button-primary" onclick="return confirm('¿Estás seguro de que deseas sincronizar todos los despachos desde Algolia?');">
                    Sincronizar desde Algolia
                </button>
            </p>
        </form>

        <hr>

        <h2>Gestión de Datos</h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('lexhoy_despachos_delete_all'); ?>
            <p>
                <button type="submit" name="delete_all_posts" class="button button-danger" onclick="return confirm('¿Estás seguro de que deseas borrar TODOS los despachos? Esta acción no se puede deshacer.');">
                    Borrar Todos los Despachos
                </button>
            </p>
        </form>

        <div id="sync-progress" style="display: none;">
            <div class="progress-bar">
                <div class="progress-bar-fill"></div>
            </div>
            <p class="progress-text">Procesando... <span class="progress-percentage">0%</span></p>
        </div>

        <style>
            .progress-bar {
                width: 100%;
                height: 20px;
                background-color: #f0f0f0;
                border-radius: 10px;
                overflow: hidden;
                margin: 10px 0;
            }
            .progress-bar-fill {
                height: 100%;
                background-color: #2271b1;
                width: 0%;
                transition: width 0.3s ease-in-out;
            }
            .progress-text {
                text-align: center;
                margin: 5px 0;
            }
            .button-danger {
                background-color: #dc3545;
                border-color: #dc3545;
                color: white;
            }
            .button-danger:hover {
                background-color: #c82333;
                border-color: #bd2130;
                color: white;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('form').on('submit', function() {
                if ($(this).find('[name="sync_from_algolia"]').length || $(this).find('[name="delete_all_posts"]').length) {
                    $('#sync-progress').show();
                    $('.progress-bar-fill').css('width', '0%');
                    $('.progress-percentage').text('0%');
                    
                    // Simular progreso
                    var progress = 0;
                    var interval = setInterval(function() {
                        progress += 5;
                        if (progress > 90) {
                            clearInterval(interval);
                        }
                        $('.progress-bar-fill').css('width', progress + '%');
                        $('.progress-percentage').text(progress + '%');
                    }, 1000);
                }
            });
        });
        </script>
    </div>
    <?php
} 
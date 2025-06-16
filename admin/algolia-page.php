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
    
    // Verificar si se está guardando la configuración
    $settings_updated = isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true';
    
    if ($settings_updated) {
        try {
            $app_id = get_option('lexhoy_despachos_algolia_app_id');
            $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
            $index_name = get_option('lexhoy_despachos_algolia_index_name');

            if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
                throw new Exception('Configuración incompleta de Algolia');
            }

            $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, '', $index_name);
            
            // Verificar credenciales
            if ($client->verify_credentials()) {
                add_settings_error(
                    'lexhoy_despachos_algolia',
                    'settings_updated',
                    '✅ Configuración guardada correctamente y conexión verificada con Algolia.',
                    'success'
                );
            } else {
                throw new Exception('No se pudo verificar la conexión con Algolia');
            }
        } catch (Exception $e) {
            add_settings_error(
                'lexhoy_despachos_algolia',
                'settings_error',
                '❌ Error al verificar la configuración: ' . $e->getMessage(),
                'error'
            );
        }
    }
    
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
            
            // Mostrar solo el primer registro válido
            $valid_hits = array_filter($hits, function($hit) {
                return !empty($hit['objectID']) && !empty($hit['nombre']);
            });

            if (!empty($valid_hits)) {
                $first_hit = reset($valid_hits);
                $message .= '<strong>Registro sincronizado:</strong><br>';
                $message .= 'ID: ' . esc_html($first_hit['objectID']) . '<br>';
                $message .= 'Nombre: ' . esc_html($first_hit['nombre']) . '<br>';
                $message .= 'Localidad: ' . esc_html($first_hit['localidad'] ?? 'N/A') . '<br>';
                $message .= 'Provincia: ' . esc_html($first_hit['provincia'] ?? 'N/A') . '<br>';
                
                if (isset($first_hit['areas_practica']) && is_array($first_hit['areas_practica'])) {
                    $message .= 'Áreas de práctica: ' . esc_html(implode(', ', $first_hit['areas_practica'])) . '<br>';
                }
                
                $message .= 'Código Postal: ' . esc_html($first_hit['codigo_postal'] ?? 'N/A') . '<br>';
                $message .= 'Dirección: ' . esc_html($first_hit['direccion'] ?? 'N/A') . '<br>';
                $message .= 'Teléfono: ' . esc_html($first_hit['telefono'] ?? 'N/A') . '<br>';
                $message .= 'Email: ' . esc_html($first_hit['email'] ?? 'N/A') . '<br>';
                $message .= 'Web: ' . esc_html($first_hit['web'] ?? 'N/A') . '<br>';
                $message .= 'Estado: ' . esc_html($first_hit['estado'] ?? 'N/A') . '<br>';
                $message .= 'Última actualización: ' . esc_html($first_hit['ultima_actualizacion'] ?? 'N/A') . '<br>';
                $message .= 'Slug: ' . esc_html($first_hit['slug'] ?? 'N/A') . '<br>';
            } else {
                $message .= 'No se encontraron registros válidos para sincronizar.<br>';
            }

            $message .= '</p></div>';
            add_settings_error(
                'lexhoy_despachos_algolia',
                'sync_success',
                $message,
                'success'
            );

        } catch (Exception $e) {
            add_settings_error(
                'lexhoy_despachos_algolia',
                'sync_error',
                '❌ Error en la sincronización: ' . $e->getMessage(),
                'error'
            );
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
        
        <?php settings_errors('lexhoy_despachos_algolia'); ?>
        
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
                <button type="submit" name="sync_from_algolia" class="button button-primary">
                    Probar sincronización con un registro
                </button>
            </p>
        </form>

        <?php if ($is_syncing): ?>
            <?php
            $cpt = new LexhoyDespachosCPT();
            $result = $cpt->sync_all_from_algolia();
            
            if ($result['success']): ?>
                <div class="notice notice-success">
                    <p><strong>✅ <?php echo esc_html($result['message']); ?></strong></p>
                    <p>Total de registros disponibles en Algolia: <?php echo esc_html($result['total_records']); ?></p>
                    <div style="background: #f0f0f1; padding: 15px; margin: 10px 0; border-radius: 4px;">
                        <h3>Registro sincronizado:</h3>
                        <?php if (!empty($result['object'])): ?>
                            <ul style="list-style: none; padding: 0;">
                                <li><strong>ID de Algolia:</strong> <?php echo esc_html($result['object']['objectID']); ?></li>
                                <li><strong>Nombre:</strong> <?php echo esc_html($result['object']['nombre']); ?></li>
                                <li><strong>Localidad:</strong> <?php echo esc_html($result['object']['localidad'] ?? 'N/A'); ?></li>
                                <li><strong>Provincia:</strong> <?php echo esc_html($result['object']['provincia'] ?? 'N/A'); ?></li>
                                <li><strong>Áreas de práctica:</strong> <?php echo esc_html(implode(', ', $result['object']['areas_practica'] ?? [])); ?></li>
                                <li><strong>Código Postal:</strong> <?php echo esc_html($result['object']['codigo_postal'] ?? 'N/A'); ?></li>
                                <li><strong>Dirección:</strong> <?php echo esc_html($result['object']['direccion'] ?? 'N/A'); ?></li>
                                <li><strong>Teléfono:</strong> <?php echo esc_html($result['object']['telefono'] ?? 'N/A'); ?></li>
                                <li><strong>Email:</strong> <?php echo esc_html($result['object']['email'] ?? 'N/A'); ?></li>
                                <li><strong>Web:</strong> <?php echo esc_html($result['object']['web'] ?? 'N/A'); ?></li>
                                <li><strong>Estado:</strong> <?php echo esc_html($result['object']['estado'] ?? 'N/A'); ?></li>
                                <li><strong>Última actualización:</strong> <?php echo esc_html($result['object']['ultima_actualizacion'] ?? 'N/A'); ?></li>
                                <li><strong>Slug:</strong> <?php echo esc_html($result['object']['slug'] ?? 'N/A'); ?></li>
                                <?php if (!empty($result['object']['horario'])): ?>
                                    <li>
                                        <strong>Horario:</strong>
                                        <ul style="list-style: none; margin-left: 20px;">
                                            <?php foreach ($result['object']['horario'] as $dia => $horas): ?>
                                                <li><?php echo esc_html(ucfirst($dia)); ?>: <?php echo esc_html($horas ?: 'N/A'); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </li>
                                <?php endif; ?>
                                <?php if (!empty($result['object']['redes_sociales'])): ?>
                                    <li>
                                        <strong>Redes Sociales:</strong>
                                        <ul style="list-style: none; margin-left: 20px;">
                                            <?php foreach ($result['object']['redes_sociales'] as $red => $url): ?>
                                                <li><?php echo esc_html(ucfirst($red)); ?>: <?php echo esc_html($url ?: 'N/A'); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            <p><strong>ID del post en WordPress:</strong> <?php echo esc_html($result['post_id']); ?></p>
                            <p><strong>Acción realizada:</strong> <?php echo esc_html($result['action']); ?></p>
                            <p>
                                <a href="<?php echo get_edit_post_link($result['post_id']); ?>" class="button button-primary">Ver/Editar el despacho</a>
                                <a href="<?php echo get_permalink($result['post_id']); ?>" class="button button-secondary" target="_blank">Ver en el sitio</a>
                            </p>
                        <?php else: ?>
                            <p>No se encontró ningún registro válido para sincronizar.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="notice notice-error">
                    <p><strong>❌ Error en la sincronización:</strong></p>
                    <p><?php echo esc_html($result['message']); ?></p>
                    <p>Por favor, verifica:</p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li>Que todas las credenciales de Algolia sean correctas</li>
                        <li>Que el índice exista y contenga datos</li>
                        <li>Que los datos en Algolia tengan el formato correcto</li>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

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
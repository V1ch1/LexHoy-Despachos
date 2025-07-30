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

// NUEVO: Registrar handlers AJAX para borrado masivo optimizado
add_action('wp_ajax_lexhoy_get_delete_count', 'lexhoy_ajax_get_delete_count');
add_action('wp_ajax_lexhoy_delete_batch', 'lexhoy_ajax_delete_batch');

// NUEVO: Registrar handler AJAX para reparar áreas de despachos
add_action('wp_ajax_lexhoy_fix_despachos_areas', 'lexhoy_ajax_fix_despachos_areas');

/**
 * AJAX: Obtener conteo de despachos para borrar
 */
function lexhoy_ajax_get_delete_count() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('No tienes permisos suficientes.');
    }
    
    check_ajax_referer('lexhoy_delete_batch', 'nonce');
    
    $despachos = get_posts(array(
        'post_type' => 'despacho',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids'
    ));
    
    wp_send_json_success(array(
        'total' => count($despachos)
    ));
}

/**
 * AJAX: Borrar lote de despachos
 */
function lexhoy_ajax_delete_batch() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('No tienes permisos suficientes.');
    }
    
    check_ajax_referer('lexhoy_delete_batch', 'nonce');
    
    // Configuración SUPER optimizada
    set_time_limit(180); // 3 minutos por lote (más tiempo para estabilidad)
    ini_set('memory_limit', '512M');
    
    $batch_size = 40; // Balance entre velocidad y estabilidad
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    
    // Obtener lote actual
    $despachos = get_posts(array(
        'post_type' => 'despacho',
        'post_status' => 'any',
        'numberposts' => $batch_size,
        'offset' => $offset,
        'fields' => 'ids'
    ));
    
    $deleted = 0;
    $errors = 0;
    $error_details = array();
    
    // Eliminar posts en masa usando SQL directo (MUCHO más rápido)
    if (!empty($despachos)) {
        global $wpdb;
        
        // Convertir IDs a string para SQL
        $post_ids = implode(',', array_map('intval', $despachos));
        
        try {
            // Eliminar metadatos en masa
            $meta_deleted = $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$post_ids})");
            
            // Eliminar posts en masa
            $posts_deleted = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE ID IN ({$post_ids})");
            
            $deleted = $posts_deleted;
            
            if ($deleted != count($despachos)) {
                $errors = count($despachos) - $deleted;
                $error_details[] = "Algunos posts no pudieron ser eliminados";
            }
            
        } catch (Exception $e) {
            $errors = count($despachos);
            $error_details[] = "Error SQL: " . $e->getMessage();
        }
    }
    
    // CORREGIDO: Verificación más precisa de registros restantes
    $remaining_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'despacho'");
    
    wp_send_json_success(array(
        'deleted' => $deleted,
        'errors' => $errors,
        'error_details' => $error_details,
        'processed' => count($despachos),
        'has_more' => $remaining_count > 0,
        'remaining_count' => $remaining_count,
        'next_offset' => $offset + $batch_size,
        'batch_size' => $batch_size
    ));
}

/**
 * AJAX: Reparar áreas de práctica de despachos desde Algolia
 */
function lexhoy_ajax_fix_despachos_areas() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('No tienes permisos suficientes.');
    }
    
    check_ajax_referer('lexhoy_fix_areas', 'nonce');
    
    set_time_limit(300); // 5 minutos
    ini_set('memory_limit', '512M');
    
    try {
        // Obtener configuración de Algolia
        $app_id = get_option('lexhoy_despachos_algolia_app_id');
        $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
        $index_name = get_option('lexhoy_despachos_algolia_index_name');
        
        if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
            wp_send_json_error('Configuración de Algolia incompleta.');
        }
        
        require_once(dirname(__FILE__) . '/../includes/class-lexhoy-algolia-client.php');
        $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, '', $index_name);
        
        // Obtener todos los registros de Algolia
        $result = $client->browse_all_unfiltered();
        
        if (!$result['success']) {
            wp_send_json_error('Error al obtener datos de Algolia: ' . $result['message']);
        }
        
        $algolia_records = $result['hits'];
        $despachos_processed = 0;
        $despachos_fixed = 0;
        $areas_assigned = 0;
        $errors = 0;
        
        // Obtener todos los despachos de WordPress
        $wordpress_despachos = get_posts(array(
            'post_type' => 'despacho',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids'
        ));
        
        foreach ($wordpress_despachos as $post_id) {
            $despachos_processed++;
            
            // Obtener el object_id de Algolia para este despacho
            $algolia_object_id = get_post_meta($post_id, '_algolia_object_id', true);
            
            if (empty($algolia_object_id)) {
                continue; // Saltar si no tiene object_id
            }
            
            // Buscar el registro correspondiente en Algolia
            $algolia_record = null;
            foreach ($algolia_records as $record) {
                if ($record['objectID'] === $algolia_object_id) {
                    $algolia_record = $record;
                    break;
                }
            }
            
            if (!$algolia_record) {
                $errors++;
                continue; // No se encontró en Algolia
            }
            
            $areas_to_assign = array();
            
            // NUEVA ESTRUCTURA: Extraer áreas de las sedes
            if (isset($algolia_record['sedes']) && is_array($algolia_record['sedes'])) {
                foreach ($algolia_record['sedes'] as $sede) {
                    if (isset($sede['areas_practica']) && is_array($sede['areas_practica'])) {
                        $areas_to_assign = array_merge($areas_to_assign, $sede['areas_practica']);
                    }
                }
            }
            // ESTRUCTURA ANTIGUA: Áreas directas
            elseif (isset($algolia_record['areas_practica']) && is_array($algolia_record['areas_practica'])) {
                $areas_to_assign = $algolia_record['areas_practica'];
            }
            
            // Eliminar duplicados
            $areas_to_assign = array_unique($areas_to_assign);
            
            if (!empty($areas_to_assign)) {
                // Crear términos si no existen y obtener IDs
                $term_ids = array();
                foreach ($areas_to_assign as $area_name) {
                    if (empty($area_name)) continue;
                    
                    $term = term_exists($area_name, 'area_practica');
                    if (!$term) {
                        $term = wp_insert_term($area_name, 'area_practica');
                    }
                    
                    if (!is_wp_error($term)) {
                        $term_ids[] = intval($term['term_id']);
                        $areas_assigned++;
                    }
                }
                
                // Asignar las áreas al despacho
                if (!empty($term_ids)) {
                    $result = wp_set_post_terms($post_id, $term_ids, 'area_practica', false);
                    if (!is_wp_error($result)) {
                        $despachos_fixed++;
                    } else {
                        $errors++;
                    }
                }
            }
        }
        
        wp_send_json_success(array(
            'despachos_processed' => $despachos_processed,
            'despachos_fixed' => $despachos_fixed,
            'areas_assigned' => $areas_assigned,
            'errors' => $errors
        ));
        
    } catch (Exception $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}

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
                        <?php if (empty(get_option('lexhoy_despachos_algolia_app_id'))): ?>
                            <p class="description" style="color: #dc3545;">⚠️ Campo vacío - Requerido</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Admin API Key</th>
                    <td>
                        <input type="password" name="lexhoy_despachos_algolia_admin_api_key" value="<?php echo esc_attr(get_option('lexhoy_despachos_algolia_admin_api_key')); ?>" class="regular-text">
                        <?php if (empty(get_option('lexhoy_despachos_algolia_admin_api_key'))): ?>
                            <p class="description" style="color: #dc3545;">⚠️ Campo vacío - Requerido</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Write API Key</th>
                    <td>
                        <input type="password" name="lexhoy_despachos_algolia_write_api_key" value="<?php echo esc_attr(get_option('lexhoy_despachos_algolia_write_api_key')); ?>" class="regular-text">
                        <?php if (empty(get_option('lexhoy_despachos_algolia_write_api_key'))): ?>
                            <p class="description" style="color: #666;">Opcional</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Search API Key</th>
                    <td>
                        <input type="password" name="lexhoy_despachos_algolia_search_api_key" value="<?php echo esc_attr(get_option('lexhoy_despachos_algolia_search_api_key')); ?>" class="regular-text">
                        <?php if (empty(get_option('lexhoy_despachos_algolia_search_api_key'))): ?>
                            <p class="description" style="color: #666;">Opcional</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Usage API Key</th>
                    <td>
                        <input type="password" name="lexhoy_despachos_algolia_usage_api_key" value="<?php echo esc_attr(get_option('lexhoy_despachos_algolia_usage_api_key')); ?>" class="regular-text">
                        <?php if (empty(get_option('lexhoy_despachos_algolia_usage_api_key'))): ?>
                            <p class="description" style="color: #666;">Opcional</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Monitoring API Key</th>
                    <td>
                        <input type="password" name="lexhoy_despachos_algolia_monitoring_api_key" value="<?php echo esc_attr(get_option('lexhoy_despachos_algolia_monitoring_api_key')); ?>" class="regular-text">
                        <?php if (empty(get_option('lexhoy_despachos_algolia_monitoring_api_key'))): ?>
                            <p class="description" style="color: #666;">Opcional</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Index Name</th>
                    <td>
                        <input type="text" name="lexhoy_despachos_algolia_index_name" value="<?php echo esc_attr(get_option('lexhoy_despachos_algolia_index_name')); ?>" class="regular-text">
                        <?php if (empty(get_option('lexhoy_despachos_algolia_index_name'))): ?>
                            <p class="description" style="color: #dc3545;">⚠️ Campo vacío - Requerido</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Guardar configuración'); ?>
        </form>

        <hr>

        <h2>Sincronización</h2>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
            <div>
                <h3>🔄 Sincronización de Prueba</h3>
                <p>Prueba la conexión con Algolia sincronizando un solo registro:</p>
                <form method="post" action="">
                    <?php wp_nonce_field('lexhoy_despachos_sync_from_algolia'); ?>
                    <p>
                        <button type="submit" name="sync_from_algolia" class="button button-primary">
                            Probar sincronización con un registro
                        </button>
                    </p>
                </form>
            </div>
            
            <div>
                <h3>⚖️ Reparar Áreas de Despachos</h3>
                <p>Asigna las áreas de práctica a los despachos existentes desde Algolia:</p>
                <button id="btn-fix-areas" class="button button-secondary" onclick="fixDespachosAreas()">
                    🔧 Reparar Áreas de Despachos
                </button>
                <div id="areas-fix-result" style="margin-top: 10px;"></div>
            </div>
            
            <div>
                <h3>🧹 Limpiar Duplicados</h3>
                <p>Elimina despachos duplicados que puedan haberse creado por timeouts en importaciones:</p>
                <button id="btn-clean-duplicates" class="button button-secondary" onclick="cleanDuplicates()">
                    🧹 Limpiar Duplicados
                </button>
                <div id="clean-duplicates-result" style="margin-top: 10px;"></div>
            </div>
        </div>

        <script>
        function fixDespachosAreas() {
            const btn = document.getElementById('btn-fix-areas');
            const result = document.getElementById('areas-fix-result');
            
            btn.disabled = true;
            btn.textContent = '🔄 Reparando...';
            result.innerHTML = '<p style="color: #0073aa;">Leyendo despachos desde Algolia y asignando áreas...</p>';
            
            jQuery.post(ajaxurl, {
                action: 'lexhoy_fix_despachos_areas',
                nonce: '<?php echo wp_create_nonce('lexhoy_fix_areas'); ?>'
            }, function(response) {
                btn.disabled = false;
                btn.textContent = '🔧 Reparar Áreas de Despachos';
                
                if (response.success) {
                    result.innerHTML = `
                        <div style="background: #d1eddd; border: 1px solid #00a32a; padding: 10px; border-radius: 4px; margin-top: 10px;">
                            <p><strong>✅ Reparación completada:</strong></p>
                            <ul>
                                <li>📊 Despachos procesados: ${response.data.despachos_processed}</li>
                                <li>🔧 Despachos con áreas asignadas: ${response.data.despachos_fixed}</li>
                                <li>⚖️ Total de áreas asignadas: ${response.data.areas_assigned}</li>
                                <li>❌ Errores: ${response.data.errors}</li>
                            </ul>
                            <p><strong>Estado:</strong> Ahora las áreas aparecerán en el listado de despachos y en el frontend.</p>
                        </div>
                    `;
                } else {
                    result.innerHTML = `
                        <div style="background: #ffeaa7; border: 1px solid #f39c12; padding: 10px; border-radius: 4px; margin-top: 10px;">
                            <p><strong>❌ Error:</strong> ${response.data}</p>
                        </div>
                    `;
                }
            }).fail(function() {
                btn.disabled = false;
                btn.textContent = '🔧 Reparar Áreas de Despachos';
                result.innerHTML = `
                    <div style="background: #fdcfcf; border: 1px solid #e74c3c; padding: 10px; border-radius: 4px; margin-top: 10px;">
                        <p><strong>❌ Error de conexión</strong></p>
                    </div>
                `;
            });
        }
        
        function cleanDuplicates() {
            const btn = document.getElementById('btn-clean-duplicates');
            const result = document.getElementById('clean-duplicates-result');
            
            btn.disabled = true;
            btn.textContent = '🔄 Limpiando...';
            result.innerHTML = '<p style="color: #0073aa;">Buscando y eliminando duplicados...</p>';
            
            jQuery.post(ajaxurl, {
                action: 'lexhoy_clean_duplicates',
                nonce: '<?php echo wp_create_nonce('lexhoy_clean_duplicates'); ?>'
            }, function(response) {
                btn.disabled = false;
                btn.textContent = '🧹 Limpiar Duplicados';
                
                if (response.success) {
                    result.innerHTML = `
                        <div style="background: #d1eddd; border: 1px solid #00a32a; padding: 10px; border-radius: 4px; margin-top: 10px;">
                            <p><strong>✅ Limpieza completada:</strong></p>
                            <ul>
                                <li>🧹 Duplicados eliminados: ${response.data.cleaned}</li>
                                <li>📊 Estado: ${response.data.message}</li>
                            </ul>
                            <p><strong>Nota:</strong> Los duplicados se han movido a la papelera, no se han eliminado permanentemente.</p>
                        </div>
                    `;
                } else {
                    result.innerHTML = `
                        <div style="background: #ffeaa7; border: 1px solid #f39c12; padding: 10px; border-radius: 4px; margin-top: 10px;">
                            <p><strong>❌ Error:</strong> ${response.data}</p>
                        </div>
                    `;
                }
            }).fail(function() {
                btn.disabled = false;
                btn.textContent = '🧹 Limpiar Duplicados';
                result.innerHTML = `
                    <div style="background: #fdcfcf; border: 1px solid #e74c3c; padding: 10px; border-radius: 4px; margin-top: 10px;">
                        <p><strong>❌ Error de conexión</strong></p>
                    </div>
                `;
            });
        }
        </script>

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

        <h2>🗑️ Gestión de Datos - Borrado Masivo Optimizado</h2>
        
        <div id="delete-section">
            <p><strong>⚠️ Atención:</strong> Este proceso eliminará TODOS los despachos de WordPress. Esta acción no se puede deshacer.</p>
            
            <p><strong>🔧 Proceso optimizado:</strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>✅ Procesamiento por lotes de 40 despachos (optimizado para velocidad)</li>
                <li>✅ Timeout de 60 segundos por lote - estable y confiable</li>
                <li>✅ Progreso en tiempo real</li>
                <li>✅ Manejo de errores mejorado</li>
                <li>✅ Limpieza de metadatos optimizada</li>
            </ul>
            
            <button id="btn-delete-all" class="button button-danger" onclick="startOptimizedDelete()">
                🗑️ Iniciar Borrado Masivo Optimizado
            </button>
            
            <div id="delete-progress" style="display: none; margin-top: 20px;">
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="progress-stats">
                        <span id="progress-text">Iniciando...</span>
                        <span id="progress-percentage">0%</span>
                    </div>
                </div>
                
                <div id="delete-log" style="background: #f9f9f9; border: 1px solid #ddd; padding: 10px; height: 200px; overflow-y: auto; margin: 10px 0; font-family: monospace; font-size: 12px;">
                    Preparando borrado masivo...
                </div>
                
                <div id="delete-summary" style="display: none;">
                    <h3>📊 Resumen Final</h3>
                    <div id="summary-content"></div>
                </div>
            </div>
        </div>

        <style>
            .progress-container {
                background: white;
                border: 1px solid #ddd;
                border-radius: 6px;
                padding: 15px;
                margin: 10px 0;
            }
            .progress-bar {
                width: 100%;
                height: 25px;
                background-color: #f0f0f0;
                border-radius: 12px;
                overflow: hidden;
                margin: 10px 0;
                box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);
            }
            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #0073aa 0%, #005177 100%);
                transition: width 0.3s ease-in-out;
                border-radius: 12px;
            }
            .progress-stats {
                display: flex;
                justify-content: space-between;
                font-weight: bold;
                margin: 5px 0;
            }
            .button-danger {
                background-color: #dc3545;
                border-color: #dc3545;
                color: white;
                padding: 10px 20px;
                font-size: 14px;
                font-weight: bold;
            }
            .button-danger:hover {
                background-color: #c82333;
                border-color: #bd2130;
                color: white;
            }
            .button-danger:disabled {
                background-color: #6c757d;
                border-color: #6c757d;
                cursor: not-allowed;
            }
        </style>

        <script>
        let deleteInProgress = false;
        let totalToDelete = 0;
        let deletedCount = 0;
        let errorCount = 0;
        let currentOffset = 0;
        let consecutiveErrors = 0;

        function startOptimizedDelete() {
            if (deleteInProgress) return;
            
            if (!confirm('¿Estás seguro de que deseas borrar TODOS los despachos? Esta acción no se puede deshacer.')) {
                return;
            }
            
            deleteInProgress = true;
            deletedCount = 0;
            errorCount = 0;
            currentOffset = 0;
            consecutiveErrors = 0; // Contador de errores consecutivos
            
            // UI updates
            document.getElementById('btn-delete-all').disabled = true;
            document.getElementById('btn-delete-all').textContent = '🔄 Borrando...';
            document.getElementById('delete-progress').style.display = 'block';
            
            logMessage('🚀 Iniciando borrado masivo optimizado...');
            
            // Get total count first
            jQuery.post(ajaxurl, {
                action: 'lexhoy_get_delete_count',
                nonce: '<?php echo wp_create_nonce('lexhoy_delete_batch'); ?>'
            }, function(response) {
                if (response.success) {
                    totalToDelete = response.data.total;
                    logMessage(`📊 Total de despachos a eliminar: ${totalToDelete.toLocaleString()}`);
                    
                    if (totalToDelete === 0) {
                        logMessage('✅ No hay despachos para eliminar.');
                        finishDelete();
                        return;
                    }
                    
                    // Start batch processing
                    processBatch();
                } else {
                    logMessage('❌ Error al obtener conteo: ' + response.data);
                    finishDelete();
                }
            });
        }

                 function processBatch() {
             const batchNum = Math.floor(currentOffset / 40) + 1;
             const totalBatches = Math.ceil(totalToDelete / 40);
             
             logMessage(`🚀 Procesando lote ${batchNum} de ${totalBatches} (40 despachos por lote)...`);
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                timeout: 60000, // 60 segundos timeout (CORREGIDO)
                data: {
                    action: 'lexhoy_delete_batch',
                    nonce: '<?php echo wp_create_nonce('lexhoy_delete_batch'); ?>',
                    offset: currentOffset
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        deletedCount += data.deleted;
                        errorCount += data.errors;
                        
                        // Resetear errores consecutivos en caso de éxito
                        consecutiveErrors = 0;
                        
                        logMessage(`   ✅ Eliminados: ${data.deleted}, ❌ Errores: ${data.errors}`);
                        logMessage(`   📊 Restantes en BD: ${data.remaining_count || 'calculando...'}`);
                        
                        if (data.error_details.length > 0) {
                            data.error_details.forEach(error => {
                                logMessage(`      ⚠️ ${error}`);
                            });
                        }
                        
                        // Update progress
                        const processed = deletedCount + errorCount;
                        const percentage = Math.round((processed / totalToDelete) * 100);
                        
                        document.querySelector('.progress-fill').style.width = percentage + '%';
                        document.getElementById('progress-text').textContent = 
                            `Eliminados: ${deletedCount.toLocaleString()} | Errores: ${errorCount} | Total: ${processed.toLocaleString()}/${totalToDelete.toLocaleString()}`;
                        document.getElementById('progress-percentage').textContent = percentage + '%';
                        
                        // CORREGIDO: Verificar múltiples condiciones para parar
                        if (data.has_more && data.remaining_count > 0 && data.deleted > 0) {
                            currentOffset = data.next_offset;
                            // Continue with next batch after smaller delay
                            setTimeout(processBatch, 800); // Pausa optimizada para velocidad
                        } else {
                            // Finished! (por cualquiera de estas razones)
                            if (data.remaining_count === 0) {
                                logMessage('✅ ¡Borrado masivo completado! No quedan registros en la BD.');
                            } else if (data.deleted === 0) {
                                logMessage('⚠️ Proceso detenido: No se eliminaron registros en este lote.');
                            } else {
                                logMessage('✅ ¡Borrado masivo completado!');
                            }
                            showSummary();
                            finishDelete();
                        }
                    } else {
                        consecutiveErrors++;
                        logMessage(`❌ Error en lote ${batchNum}: ${response.data}`);
                        logMessage(`⚠️ Errores consecutivos: ${consecutiveErrors}`);
                        
                        // Si hay demasiados errores consecutivos, detenerse
                        if (consecutiveErrors >= 5) {
                            logMessage('🛑 Deteniendo proceso: Demasiados errores consecutivos (5+)');
                            logMessage('💡 Sugerencia: El servidor puede estar sobrecargado. Inténtalo más tarde.');
                            showSummary();
                            finishDelete();
                            return;
                        }
                        
                        // Continue with next batch despite error
                        currentOffset += 40;
                        if (currentOffset < totalToDelete) {
                            logMessage('⏭️ Continuando con el siguiente lote...');
                            setTimeout(processBatch, 2000); // Pausa más larga tras error
                        } else {
                            logMessage('⚠️ Se alcanzó el final tras errores');
                            finishDelete();
                        }
                    }
                },
                error: function(xhr, status, error) {
                    consecutiveErrors++;
                    logMessage(`❌ Error de conexión en lote ${batchNum}: ${status} - ${error}`);
                    logMessage(`⚠️ Errores consecutivos: ${consecutiveErrors}`);
                    
                    if (status === 'timeout') {
                        logMessage('⏰ Timeout - el lote tardó demasiado');
                    }
                    
                    // Si hay demasiados errores consecutivos, detenerse
                    if (consecutiveErrors >= 5) {
                        logMessage('🛑 Deteniendo proceso: Demasiados errores de conexión consecutivos (5+)');
                        logMessage('💡 Sugerencia: El servidor puede estar sobrecargado. Inténtalo más tarde.');
                        showSummary();
                        finishDelete();
                        return;
                    }
                    
                    currentOffset += 40;
                    if (currentOffset < totalToDelete) {
                        logMessage('⏭️ Reintentando con el siguiente lote en 3 segundos...');
                        setTimeout(processBatch, 3000);
                    } else {
                        logMessage('⚠️ Se alcanzó el final tras errores de conexión');
                        finishDelete();
                    }
                }
            });
        }

        function logMessage(message) {
            const log = document.getElementById('delete-log');
            const timestamp = new Date().toLocaleTimeString();
            log.textContent += `[${timestamp}] ${message}\n`;
            log.scrollTop = log.scrollHeight;
        }

        function showSummary() {
            const summary = document.getElementById('delete-summary');
            const content = document.getElementById('summary-content');
            
            const successRate = totalToDelete > 0 ? Math.round((deletedCount / totalToDelete) * 100) : 0;
            
            content.innerHTML = `
                <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div>
                            <strong>📊 Total procesados:</strong><br>
                            <span style="font-size: 18px; color: #0073aa;">${totalToDelete.toLocaleString()}</span>
                        </div>
                        <div>
                            <strong>✅ Eliminados exitosamente:</strong><br>
                            <span style="font-size: 18px; color: #46b450;">${deletedCount.toLocaleString()}</span>
                        </div>
                        <div>
                            <strong>❌ Errores:</strong><br>
                            <span style="font-size: 18px; color: #dc3545;">${errorCount.toLocaleString()}</span>
                        </div>
                        <div>
                            <strong>📈 Tasa de éxito:</strong><br>
                            <span style="font-size: 18px; color: ${successRate >= 95 ? '#46b450' : successRate >= 80 ? '#ffb900' : '#dc3545'};">${successRate}%</span>
                        </div>
                    </div>
                    ${errorCount === 0 ? 
                        '<p style="color: #46b450; font-weight: bold; margin-top: 15px;">🎉 ¡Borrado completado sin errores! WordPress ahora tiene 0 despachos.</p>' :
                        '<p style="color: #856404; margin-top: 15px;">⚠️ Algunos despachos no pudieron ser eliminados. Revisa el log para más detalles.</p>'
                    }
                </div>
            `;
            
            summary.style.display = 'block';
        }

        function finishDelete() {
            deleteInProgress = false;
            document.getElementById('btn-delete-all').disabled = false;
            document.getElementById('btn-delete-all').textContent = '🗑️ Iniciar Borrado Masivo Optimizado';
            
            logMessage('🏁 Proceso de borrado finalizado.');
        }
        </script>
    </div>
    <?php
} 
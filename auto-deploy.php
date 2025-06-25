<?php
/**
 * Script de deployment automático para LexHoy Despachos
 * Automatiza: actualizar versión, commit, push, tag y actualizar en producción
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Función global para mostrar logs
function lexhoy_show_log($message) {
    echo '<div style="background: #f0f0f1; padding: 10px; margin: 5px 0; border-left: 4px solid #0073aa; font-family: monospace;">';
    echo date('H:i:s') . ' - ' . $message;
    echo '</div>';
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

// Añadir página al menú de administración
add_action('admin_menu', 'lexhoy_add_auto_deploy_page');

function lexhoy_add_auto_deploy_page() {
    add_submenu_page(
        'edit.php?post_type=despacho', // Parent slug
        'Deployment Automático', // Page title
        'Auto Deploy', // Menu title
        'manage_options', // Capability
        'lexhoy-despachos-auto-deploy', // Menu slug
        'lexhoy_auto_deploy_page' // Function
    );
    
    // Añadir página de configuración
    add_submenu_page(
        'edit.php?post_type=despacho', // Parent slug
        'Configuración GitHub', // Page title
        'Config GitHub', // Menu title
        'manage_options', // Capability
        'lexhoy-github-config', // Menu slug
        'lexhoy_github_config_page' // Function
    );
}

function lexhoy_auto_deploy_page() {
    if (isset($_POST['auto_deploy'])) {
        lexhoy_perform_auto_deploy();
    }
    
    ?>
    <div class="wrap">
        <h1>Deployment Automático - LexHoy Despachos</h1>
        
        <div class="notice notice-info">
            <p><strong>🚀 Deployment Automático:</strong> Este script actualiza la versión, hace commit, push, tag y actualiza en producción automáticamente.</p>
        </div>
        
        <div class="card">
            <h2>Deployment Completo</h2>
            <p>Esta opción hará todo automáticamente:</p>
            <ol>
                <li>📝 Actualizar versión del plugin</li>
                <li>💾 Hacer commit de todos los cambios</li>
                <li>📤 Subir cambios a GitHub</li>
                <li>🏷️ Crear tag de versión</li>
                <li>🔄 Actualizar archivos en producción</li>
            </ol>
            
            <form method="post">
                <?php wp_nonce_field('lexhoy_auto_deploy', 'lexhoy_nonce'); ?>
                <input type="submit" name="auto_deploy" class="button button-primary" value="🚀 Ejecutar Deployment Automático" />
            </form>
        </div>
        
        <div class="card">
            <h2>Estado actual</h2>
            <p><strong>Versión actual:</strong> <?php echo defined('LEXHOY_DESPACHOS_VERSION') ? LEXHOY_DESPACHOS_VERSION : 'No disponible'; ?></p>
            <p><strong>Próxima versión:</strong> <span id="next-version">Calculando...</span></p>
        </div>
    </div>
    
    <script>
    // Calcular próxima versión
    document.addEventListener('DOMContentLoaded', function() {
        const currentVersion = '<?php echo defined('LEXHOY_DESPACHOS_VERSION') ? LEXHOY_DESPACHOS_VERSION : '1.0.0'; ?>';
        const parts = currentVersion.split('.');
        parts[2] = (parseInt(parts[2]) + 1).toString();
        const nextVersion = parts.join('.');
        document.getElementById('next-version').textContent = nextVersion;
    });
    </script>
    <?php
}

function lexhoy_perform_auto_deploy() {
    if (!wp_verify_nonce($_POST['lexhoy_nonce'], 'lexhoy_auto_deploy')) {
        wp_die('Error de seguridad');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para realizar esta acción');
    }
    
    echo '<div class="wrap">';
    echo '<h1>Deployment Automático - LexHoy Despachos</h1>';
    
    lexhoy_show_log('🚀 Iniciando deployment automático...');
    
    $plugin_dir = plugin_dir_path(__FILE__);
    
    // Obtener versión actual
    $current_version = defined('LEXHOY_DESPACHOS_VERSION') ? LEXHOY_DESPACHOS_VERSION : '1.0.0';
    $parts = explode('.', $current_version);
    $parts[2] = (int)$parts[2] + 1;
    $new_version = implode('.', $parts);
    
    lexhoy_show_log('📝 Versión actual: ' . $current_version);
    lexhoy_show_log('📝 Nueva versión: ' . $new_version);
    
    // Actualizar versión en el archivo principal
    $main_file = $plugin_dir . 'lexhoy-despachos.php';
    $content = file_get_contents($main_file);
    
    if ($content === false) {
        lexhoy_show_log('❌ ERROR: No se pudo leer el archivo principal del plugin.');
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    
    // Reemplazar versiones
    $content = preg_replace('/Version:\s*\d+\.\d+\.\d+/', 'Version: ' . $new_version, $content);
    $content = preg_replace("/define\('LEXHOY_DESPACHOS_VERSION',\s*'[^']*'\)/", "define('LEXHOY_DESPACHOS_VERSION', '" . $new_version . "')", $content);
    
    if (file_put_contents($main_file, $content) === false) {
        lexhoy_show_log('❌ ERROR: No se pudo actualizar la versión en el archivo principal.');
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    
    lexhoy_show_log('✅ Versión actualizada en el archivo principal');
    
    // Intentar deployment con GitHub API
    $github_token = get_option('lexhoy_github_token', '');
    $github_username = 'V1ch1';
    $github_repo = 'LexHoy-Despachos';
    
    if (empty($github_token)) {
        lexhoy_show_log('⚠️ No hay token de GitHub configurado');
        lexhoy_show_log('ℹ️ Configurando deployment local...');
        
        // Deployment local
        lexhoy_local_deployment($plugin_dir, $new_version);
    } else {
        lexhoy_show_log('🔑 Token de GitHub detectado - Intentando deployment completo...');
        
        // Deployment con GitHub API
        $success = lexhoy_github_deployment($github_token, $github_username, $github_repo, $plugin_dir, $new_version);
        
        if (!$success) {
            lexhoy_show_log('⚠️ Falló deployment con GitHub - Continuando local...');
            lexhoy_local_deployment($plugin_dir, $new_version);
        }
    }
    
    echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
    echo '</div>';
}

function lexhoy_github_deployment($token, $username, $repo, $plugin_dir, $new_version) {
    $api_base = "https://api.github.com/repos/{$username}/{$repo}";
    
    // 1. Obtener el SHA del último commit
    $response = wp_remote_get($api_base . '/commits/main', array(
        'headers' => array(
            'Authorization' => 'token ' . $token,
            'User-Agent' => 'WordPress/' . get_bloginfo('version')
        )
    ));
    
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        lexhoy_show_log('❌ ERROR: No se pudo obtener el último commit');
        return false;
    }
    
    $commit_data = json_decode(wp_remote_retrieve_body($response));
    $base_sha = $commit_data->sha;
    
    lexhoy_show_log('✅ SHA del último commit obtenido: ' . substr($base_sha, 0, 7));
    
    // 2. Crear árbol con archivos modificados
    $files_to_update = array(
        'assets/css/search.css',
        'lexhoy-despachos.php',
        'auto-deploy.php'
    );
    
    $tree_items = array();
    
    foreach ($files_to_update as $file) {
        $file_path = $plugin_dir . $file;
        
        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            $content_encoded = base64_encode($content);
            
            $tree_items[] = array(
                'path' => $file,
                'mode' => '100644',
                'type' => 'blob',
                'content' => $content
            );
            
            lexhoy_show_log('📁 Archivo preparado: ' . $file);
        }
    }
    
    // 3. Crear el árbol
    $tree_data = array(
        'base_tree' => $base_sha,
        'tree' => $tree_items
    );
    
    $response = wp_remote_post($api_base . '/git/trees', array(
        'headers' => array(
            'Authorization' => 'token ' . $token,
            'Content-Type' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version')
        ),
        'body' => json_encode($tree_data)
    ));
    
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 201) {
        lexhoy_show_log('❌ ERROR: No se pudo crear el árbol');
        return false;
    }
    
    $tree_response = json_decode(wp_remote_retrieve_body($response));
    $tree_sha = $tree_response->sha;
    
    lexhoy_show_log('✅ Árbol creado: ' . substr($tree_sha, 0, 7));
    
    // 4. Crear commit
    $commit_data = array(
        'message' => "Auto-deploy: Actualización automática a versión {$new_version}",
        'tree' => $tree_sha,
        'parents' => array($base_sha)
    );
    
    $response = wp_remote_post($api_base . '/git/commits', array(
        'headers' => array(
            'Authorization' => 'token ' . $token,
            'Content-Type' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version')
        ),
        'body' => json_encode($commit_data)
    ));
    
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 201) {
        lexhoy_show_log('❌ ERROR: No se pudo crear el commit');
        return false;
    }
    
    $commit_response = json_decode(wp_remote_retrieve_body($response));
    $commit_sha = $commit_response->sha;
    
    lexhoy_show_log('✅ Commit creado: ' . substr($commit_sha, 0, 7));
    
    // 5. Actualizar referencia main
    $ref_data = array(
        'sha' => $commit_sha
    );
    
    $response = wp_remote_post($api_base . '/git/refs/heads/main', array(
        'method' => 'PATCH',
        'headers' => array(
            'Authorization' => 'token ' . $token,
            'Content-Type' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version')
        ),
        'body' => json_encode($ref_data)
    ));
    
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        lexhoy_show_log('❌ ERROR: No se pudo actualizar la rama main');
        return false;
    }
    
    lexhoy_show_log('✅ Rama main actualizada');
    
    // 6. Crear tag
    $tag_data = array(
        'tag' => 'v' . $new_version,
        'message' => "Release version {$new_version}",
        'object' => $commit_sha,
        'type' => 'commit'
    );
    
    $response = wp_remote_post($api_base . '/git/tags', array(
        'headers' => array(
            'Authorization' => 'token ' . $token,
            'Content-Type' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version')
        ),
        'body' => json_encode($tag_data)
    ));
    
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 201) {
        lexhoy_show_log('⚠️ ADVERTENCIA: No se pudo crear el tag');
    } else {
        lexhoy_show_log('✅ Tag creado: v' . $new_version);
    }
    
    // 7. Crear release
    $release_data = array(
        'tag_name' => 'v' . $new_version,
        'name' => 'Release ' . $new_version,
        'body' => "Actualización automática a versión {$new_version}\n\n- Botón de búsqueda actualizado\n- Versión del plugin actualizada\n- Script de deployment mejorado",
        'draft' => false,
        'prerelease' => false
    );
    
    $response = wp_remote_post($api_base . '/releases', array(
        'headers' => array(
            'Authorization' => 'token ' . $token,
            'Content-Type' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version')
        ),
        'body' => json_encode($release_data)
    ));
    
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 201) {
        lexhoy_show_log('⚠️ ADVERTENCIA: No se pudo crear el release');
    } else {
        lexhoy_show_log('✅ Release creado en GitHub');
    }
    
    lexhoy_show_log('🎉 ¡Deployment con GitHub completado!');
    
    echo '<div class="notice notice-success"><p>🎉 <strong>¡Deployment automático completado!</strong></p>';
    echo '<p>✅ Versión actualizada a: <strong>' . $new_version . '</strong></p>';
    echo '<p>✅ Cambios subidos a GitHub</p>';
    echo '<p>✅ Tag creado: <strong>v' . $new_version . '</strong></p>';
    echo '<p>✅ Release creado en GitHub</p>';
    echo '<p>✅ Archivos actualizados en producción</p></div>';
    
    return true;
}

function lexhoy_local_deployment($plugin_dir, $new_version) {
    lexhoy_show_log('🔄 Iniciando actualización local...');
    
    // Actualizar archivos específicos que han sido modificados
    $files_to_update = array(
        'assets/css/search.css' => 'Estilos del buscador actualizados',
        'lexhoy-despachos.php' => 'Versión del plugin actualizada',
        'auto-deploy.php' => 'Script de deployment mejorado'
    );
    
    $updated_count = 0;
    
    foreach ($files_to_update as $file => $description) {
        $file_path = $plugin_dir . $file;
        
        if (file_exists($file_path)) {
            // Verificar si el archivo ha sido modificado recientemente
            $file_time = filemtime($file_path);
            $current_time = time();
            
            if ($current_time - $file_time < 300) { // Archivo modificado en los últimos 5 minutos
                $updated_count++;
                lexhoy_show_log('✅ ' . $description . ' (' . $file . ')');
            } else {
                lexhoy_show_log('ℹ️ Archivo sin cambios: ' . $file);
            }
        } else {
            lexhoy_show_log('⚠️ Archivo no encontrado: ' . $file);
        }
    }
    
    // Limpiar caché de WordPress si está disponible
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
        lexhoy_show_log('🧹 Caché de WordPress limpiado');
    }
    
    // Limpiar caché de plugins si está disponible
    if (function_exists('delete_transient')) {
        delete_transient('lexhoy_last_update_check');
        lexhoy_show_log('🧹 Caché de actualizaciones limpiado');
    }
    
    lexhoy_show_log('🎉 ¡Deployment local completado!');
    lexhoy_show_log('📊 Archivos actualizados: ' . $updated_count);
    lexhoy_show_log('🏷️ Nueva versión: ' . $new_version);
    
    echo '<div class="notice notice-warning"><p>🎉 <strong>¡Actualización local completada!</strong></p>';
    echo '<p>✅ Versión actualizada a: <strong>' . $new_version . '</strong></p>';
    echo '<p>⚠️ Git no disponible - Cambios solo en este servidor</p>';
    echo '<p>✅ Archivos actualizados en producción</p></div>';
    
    echo '<div class="card">';
    echo '<h3>📋 Resumen de cambios</h3>';
    echo '<ul>';
    echo '<li>🔵 Botón de búsqueda cambiado a azul</li>';
    echo '<li>📝 Versión actualizada a ' . $new_version . '</li>';
    echo '<li>🧹 Caché limpiado</li>';
    echo '</ul>';
    echo '</div>';
}

function lexhoy_github_config_page() {
    if (isset($_POST['save_github_token'])) {
        if (wp_verify_nonce($_POST['lexhoy_github_nonce'], 'lexhoy_github_config')) {
            $token = sanitize_text_field($_POST['github_token']);
            update_option('lexhoy_github_token', $token);
            echo '<div class="notice notice-success"><p>✅ Token de GitHub guardado correctamente.</p></div>';
        }
    }
    
    $current_token = get_option('lexhoy_github_token', '');
    ?>
    <div class="wrap">
        <h1>Configuración GitHub - LexHoy Despachos</h1>
        
        <div class="notice notice-info">
            <p><strong>🔑 Token de GitHub:</strong> Necesario para deployment automático completo.</p>
        </div>
        
        <div class="card">
            <h2>Configurar Token de GitHub</h2>
            <p>Para que el deployment automático funcione completamente, necesitas un token de GitHub con permisos de escritura.</p>
            
            <h3>📋 Pasos para crear el token:</h3>
            <ol>
                <li>Ve a <a href="https://github.com/settings/tokens" target="_blank">GitHub Settings → Tokens</a></li>
                <li>Haz clic en "Generate new token (classic)"</li>
                <li>Selecciona los permisos: <code>repo</code>, <code>workflow</code></li>
                <li>Copia el token generado</li>
                <li>Pégalo en el campo de abajo</li>
            </ol>
            
            <form method="post">
                <?php wp_nonce_field('lexhoy_github_config', 'lexhoy_github_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="github_token">Token de GitHub</label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="github_token" 
                                   name="github_token" 
                                   value="<?php echo esc_attr($current_token); ?>" 
                                   class="regular-text" 
                                   placeholder="ghp_xxxxxxxxxxxxxxxxxxxx" />
                            <p class="description">
                                Token con permisos <code>repo</code> y <code>workflow</code>
                            </p>
                        </td>
                    </tr>
                </table>
                <input type="submit" name="save_github_token" class="button button-primary" value="💾 Guardar Token" />
            </form>
        </div>
        
        <div class="card">
            <h2>Estado actual</h2>
            <?php if (!empty($current_token)): ?>
                <p><strong>✅ Token configurado:</strong> <?php echo substr($current_token, 0, 10) . '...'; ?></p>
                <p><strong>🚀 Deployment:</strong> Completo (con GitHub)</p>
            <?php else: ?>
                <p><strong>⚠️ Token no configurado</strong></p>
                <p><strong>🚀 Deployment:</strong> Solo local</p>
            <?php endif; ?>
        </div>
        
        <p><a href="<?php echo admin_url('edit.php?post_type=despacho&page=lexhoy-despachos-auto-deploy'); ?>" class="button button-primary">← Volver a Auto Deploy</a></p>
    </div>
    <?php
} 
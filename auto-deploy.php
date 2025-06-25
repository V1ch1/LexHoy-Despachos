<?php
/**
 * Script de deployment automático para LexHoy Despachos
 * Automatiza: actualizar versión, commit, push, tag y actualizar en producción
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
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
    
    // Función para mostrar logs
    function show_log($message) {
        echo '<div style="background: #f0f0f1; padding: 10px; margin: 5px 0; border-left: 4px solid #0073aa; font-family: monospace;">';
        echo date('H:i:s') . ' - ' . $message;
        echo '</div>';
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
    
    show_log('🚀 Iniciando deployment automático...');
    
    $plugin_dir = plugin_dir_path(__FILE__);
    
    // Verificar que estamos en un repositorio git
    if (!is_dir($plugin_dir . '.git')) {
        show_log('❌ ERROR: No se detectó un repositorio Git en el directorio del plugin.');
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    
    show_log('✅ Repositorio Git detectado');
    
    // Obtener versión actual
    $current_version = defined('LEXHOY_DESPACHOS_VERSION') ? LEXHOY_DESPACHOS_VERSION : '1.0.0';
    $parts = explode('.', $current_version);
    $parts[2] = (int)$parts[2] + 1;
    $new_version = implode('.', $parts);
    
    show_log('📝 Versión actual: ' . $current_version);
    show_log('📝 Nueva versión: ' . $new_version);
    
    // Actualizar versión en el archivo principal
    $main_file = $plugin_dir . 'lexhoy-despachos.php';
    $content = file_get_contents($main_file);
    
    if ($content === false) {
        show_log('❌ ERROR: No se pudo leer el archivo principal del plugin.');
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    
    // Reemplazar versiones
    $content = preg_replace('/Version:\s*\d+\.\d+\.\d+/', 'Version: ' . $new_version, $content);
    $content = preg_replace("/define\('LEXHOY_DESPACHOS_VERSION',\s*'[^']*'\)/", "define('LEXHOY_DESPACHOS_VERSION', '" . $new_version . "')", $content);
    
    if (file_put_contents($main_file, $content) === false) {
        show_log('❌ ERROR: No se pudo actualizar la versión en el archivo principal.');
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    
    show_log('✅ Versión actualizada en el archivo principal');
    
    // Ejecutar comandos Git
    $git_commands = array(
        'add .' => 'Añadiendo archivos al staging...',
        'commit -m "Auto-deploy: Actualización automática a versión ' . $new_version . '"' => 'Haciendo commit...',
        'push' => 'Subiendo cambios a GitHub...',
        'tag v' . $new_version => 'Creando tag de versión...',
        'push origin v' . $new_version => 'Subiendo tag a GitHub...'
    );
    
    foreach ($git_commands as $command => $message) {
        show_log('🔄 ' . $message);
        
        $output = array();
        $return_var = 0;
        
        // Ejecutar comando Git
        $full_command = 'cd "' . $plugin_dir . '" && git ' . $command . ' 2>&1';
        exec($full_command, $output, $return_var);
        
        if ($return_var !== 0) {
            show_log('❌ ERROR en comando Git: ' . $command);
            show_log('   Salida: ' . implode("\n   ", $output));
            echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
            echo '</div>';
            return;
        }
        
        show_log('✅ ' . $message . ' completado');
    }
    
    show_log('🎉 Deployment en GitHub completado');
    show_log('🔄 Iniciando actualización en producción...');
    
    // Ahora actualizar en producción usando el script existente
    $temp_dir = sys_get_temp_dir() . '/lexhoy-update/';
    
    // Crear directorio temporal
    if (!file_exists($temp_dir)) {
        if (!mkdir($temp_dir, 0755, true)) {
            show_log('❌ ERROR: No se pudo crear el directorio temporal.');
            echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
            echo '</div>';
            return;
        }
    }
    
    // Descargar ZIP desde GitHub
    $zip_url = 'https://github.com/V1ch1/LexHoy-Despachos/archive/refs/heads/main.zip';
    $zip_file = $temp_dir . 'lexhoy-despachos.zip';
    
    show_log('📥 Descargando archivos actualizados desde GitHub...');
    
    $response = wp_remote_get($zip_url, array(
        'timeout' => 30,
        'user-agent' => 'WordPress/' . get_bloginfo('version')
    ));
    
    if (is_wp_error($response)) {
        show_log('❌ ERROR al descargar: ' . $response->get_error_message());
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    
    $zip_content = wp_remote_retrieve_body($response);
    $http_code = wp_remote_retrieve_response_code($response);
    
    if ($http_code !== 200 || empty($zip_content)) {
        show_log('❌ ERROR: No se pudo descargar el archivo desde GitHub.');
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    
    if (file_put_contents($zip_file, $zip_content) === false) {
        show_log('❌ ERROR: No se pudo guardar el archivo ZIP.');
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    
    // Extraer y actualizar archivos
    $zip = new ZipArchive;
    if ($zip->open($zip_file) !== TRUE) {
        show_log('❌ ERROR: No se pudo abrir el archivo ZIP.');
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    
    if (!$zip->extractTo($temp_dir)) {
        show_log('❌ ERROR: No se pudo extraer el archivo ZIP.');
        $zip->close();
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    
    $zip->close();
    
    // Archivos a actualizar
    $files_to_update = array(
        'assets/css/search.css',
        'assets/js/search.js',
        'includes/class-lexhoy-despachos-shortcode.php',
        'lexhoy-despachos.php'
    );
    
    $extracted_dir = $temp_dir . 'LexHoy-Despachos-main/';
    $updated_count = 0;
    
    foreach ($files_to_update as $file) {
        $source = $extracted_dir . $file;
        $destination = $plugin_dir . $file;
        
        if (file_exists($source)) {
            $dir = dirname($destination);
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            
            if (copy($source, $destination)) {
                $updated_count++;
                show_log('✅ Actualizado: ' . $file);
            }
        }
    }
    
    // Limpiar archivos temporales
    if (file_exists($temp_dir)) {
        $files = glob($temp_dir . '*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                } elseif (is_dir($file)) {
                    $subfiles = glob($file . '/*');
                    if ($subfiles !== false) {
                        foreach ($subfiles as $subfile) {
                            if (is_file($subfile)) {
                                unlink($subfile);
                            }
                        }
                    }
                    rmdir($file);
                }
            }
        }
        rmdir($temp_dir);
    }
    
    show_log('🎉 ¡Deployment automático completado!');
    show_log('📊 Se actualizaron ' . $updated_count . ' archivos');
    show_log('🏷️ Nueva versión: ' . $new_version);
    
    echo '<div class="notice notice-success"><p>🎉 <strong>¡Deployment automático completado!</strong></p>';
    echo '<p>✅ Versión actualizada a: <strong>' . $new_version . '</strong></p>';
    echo '<p>✅ Cambios subidos a GitHub</p>';
    echo '<p>✅ Tag creado: <strong>v' . $new_version . '</strong></p>';
    echo '<p>✅ Archivos actualizados en producción</p></div>';
    
    echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
    echo '</div>';
} 
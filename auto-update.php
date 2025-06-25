<?php
/**
 * Script de actualización automática para LexHoy Despachos
 * Ejecutar desde: /wp-admin/admin.php?page=lexhoy-despachos-auto-update
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Añadir página al menú de administración
add_action('admin_menu', 'lexhoy_add_auto_update_page');

function lexhoy_add_auto_update_page() {
    add_submenu_page(
        'lexhoy-despachos', // Parent slug
        'Actualización Automática', // Page title
        'Auto Update', // Menu title
        'manage_options', // Capability
        'lexhoy-despachos-auto-update', // Menu slug
        'lexhoy_auto_update_page' // Function
    );
}

function lexhoy_auto_update_page() {
    if (isset($_POST['update_from_github'])) {
        lexhoy_perform_auto_update();
    }
    
    ?>
    <div class="wrap">
        <h1>Actualización Automática - LexHoy Despachos</h1>
        
        <div class="notice notice-info">
            <p><strong>⚠️ Importante:</strong> Este script actualiza los archivos del plugin directamente desde GitHub sin reinstalar el plugin completo.</p>
        </div>
        
        <div class="card">
            <h2>Actualizar desde GitHub</h2>
            <p>Esta opción descargará los archivos más recientes desde GitHub y los actualizará automáticamente.</p>
            
            <form method="post">
                <?php wp_nonce_field('lexhoy_auto_update', 'lexhoy_nonce'); ?>
                <input type="submit" name="update_from_github" class="button button-primary" value="Actualizar desde GitHub" />
            </form>
        </div>
        
        <div class="card">
            <h2>Estado actual</h2>
            <p><strong>Versión instalada:</strong> <?php echo defined('LEXHOY_DESPACHOS_VERSION') ? LEXHOY_DESPACHOS_VERSION : 'No disponible'; ?></p>
            <p><strong>Última versión en GitHub:</strong> <span id="github-version">Verificando...</span></p>
        </div>
    </div>
    
    <script>
    // Verificar versión en GitHub
    fetch('https://api.github.com/repos/V1ch1/LexHoy-Despachos/releases/latest')
        .then(response => response.json())
        .then(data => {
            document.getElementById('github-version').textContent = data.tag_name || 'No disponible';
        })
        .catch(error => {
            document.getElementById('github-version').textContent = 'Error al verificar';
        });
    </script>
    <?php
}

function lexhoy_perform_auto_update() {
    if (!wp_verify_nonce($_POST['lexhoy_nonce'], 'lexhoy_auto_update')) {
        wp_die('Error de seguridad');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para realizar esta acción');
    }
    
    $plugin_dir = plugin_dir_path(__FILE__);
    $temp_dir = get_temp_dir() . 'lexhoy-update/';
    
    // Crear directorio temporal
    if (!file_exists($temp_dir)) {
        mkdir($temp_dir, 0755, true);
    }
    
    // Descargar ZIP desde GitHub
    $zip_url = 'https://github.com/V1ch1/LexHoy-Despachos/archive/refs/heads/main.zip';
    $zip_file = $temp_dir . 'lexhoy-despachos.zip';
    
    $response = wp_remote_get($zip_url);
    if (is_wp_error($response)) {
        echo '<div class="notice notice-error"><p>Error al descargar: ' . $response->get_error_message() . '</p></div>';
        return;
    }
    
    $zip_content = wp_remote_retrieve_body($response);
    file_put_contents($zip_file, $zip_content);
    
    // Extraer ZIP
    $zip = new ZipArchive;
    if ($zip->open($zip_file) === TRUE) {
        $zip->extractTo($temp_dir);
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
                // Crear directorio si no existe
                $dir = dirname($destination);
                if (!file_exists($dir)) {
                    mkdir($dir, 0755, true);
                }
                
                if (copy($source, $destination)) {
                    $updated_count++;
                }
            }
        }
        
        // Limpiar archivos temporales
        lexhoy_cleanup_temp_files($temp_dir);
        
        echo '<div class="notice notice-success"><p>✅ Actualización completada. Se actualizaron ' . $updated_count . ' archivos.</p></div>';
        
    } else {
        echo '<div class="notice notice-error"><p>Error al extraer el archivo ZIP.</p></div>';
    }
}

function lexhoy_cleanup_temp_files($temp_dir) {
    if (file_exists($temp_dir)) {
        $files = glob($temp_dir . '*');
        foreach ($files as $file) {
            if (is_dir($file)) {
                lexhoy_cleanup_temp_files($file);
                rmdir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($temp_dir);
    }
} 
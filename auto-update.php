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
        'edit.php?post_type=despacho', // Parent slug - Menú de Despachos
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
            <h2>Actualización Manual (Alternativa)</h2>
            <p>Si la actualización automática no funciona, puedes actualizar manualmente estos archivos:</p>
            <ul>
                <li><code>assets/css/search.css</code> - CSS corregido para logos</li>
                <li><code>assets/js/search.js</code> - JavaScript del buscador</li>
                <li><code>includes/class-lexhoy-despachos-shortcode.php</code> - PHP del shortcode</li>
                <li><code>lexhoy-despachos.php</code> - Versión del plugin</li>
            </ul>
            <p><strong>Método:</strong> Descarga estos archivos desde GitHub y súbelos vía FTP.</p>
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
    
    echo '<div class="wrap">';
    echo '<h1>Actualización Automática - LexHoy Despachos</h1>';
    
    // Función para mostrar logs
    function show_log($message) {
        echo '<div style="background: #f0f0f1; padding: 10px; margin: 5px 0; border-left: 4px solid #0073aa; font-family: monospace;">';
        echo date('H:i:s') . ' - ' . $message;
        echo '</div>';
        // Forzar salida inmediata
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
    
    show_log('🚀 Iniciando proceso de actualización...');
    
    // Verificar que ZipArchive esté disponible
    if (!class_exists('ZipArchive')) {
        show_log('❌ ERROR: La extensión ZipArchive no está disponible en tu servidor.');
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    show_log('✅ ZipArchive está disponible');
    
    $plugin_dir = plugin_dir_path(__FILE__);
    $temp_dir = sys_get_temp_dir() . '/lexhoy-update/';
    
    show_log('📁 Directorio del plugin: ' . $plugin_dir);
    show_log('📁 Directorio temporal: ' . $temp_dir);
    
    // Crear directorio temporal
    if (!file_exists($temp_dir)) {
        show_log('📁 Creando directorio temporal...');
        if (!mkdir($temp_dir, 0755, true)) {
            show_log('❌ ERROR: No se pudo crear el directorio temporal en: ' . $temp_dir);
            echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
            echo '</div>';
            return;
        }
        show_log('✅ Directorio temporal creado');
    } else {
        show_log('✅ Directorio temporal ya existe');
    }
    
    show_log('📥 Iniciando descarga desde GitHub...');
    
    // Descargar ZIP desde GitHub con timeout más corto
    $zip_url = 'https://github.com/V1ch1/LexHoy-Despachos/archive/refs/heads/main.zip';
    $zip_file = $temp_dir . 'lexhoy-despachos.zip';
    
    show_log('🔗 URL de descarga: ' . $zip_url);
    show_log('📁 Archivo ZIP destino: ' . $zip_file);
    
    // Usar wp_remote_get con timeout más corto
    show_log('⏳ Enviando petición HTTP...');
    $response = wp_remote_get($zip_url, array(
        'timeout' => 30, // Reducido de 60 a 30 segundos
        'user-agent' => 'WordPress/' . get_bloginfo('version')
    ));
    
    if (is_wp_error($response)) {
        show_log('❌ ERROR al descargar: ' . $response->get_error_message());
        echo '<p><strong>Sugerencia:</strong> Usa la actualización manual descargando los archivos desde GitHub.</p>';
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    
    show_log('✅ Petición HTTP completada');
    
    $zip_content = wp_remote_retrieve_body($response);
    $http_code = wp_remote_retrieve_response_code($response);
    
    show_log('📊 Código de respuesta HTTP: ' . $http_code);
    show_log('📊 Tamaño del contenido descargado: ' . strlen($zip_content) . ' bytes');
    
    if ($http_code !== 200) {
        show_log('❌ ERROR HTTP: ' . $http_code . ' - No se pudo descargar desde GitHub.');
        echo '<p><strong>Sugerencia:</strong> Usa la actualización manual descargando los archivos desde GitHub.</p>';
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    
    if (empty($zip_content)) {
        show_log('❌ ERROR: El archivo descargado está vacío.');
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    
    show_log('💾 Guardando archivo ZIP...');
    if (file_put_contents($zip_file, $zip_content) === false) {
        show_log('❌ ERROR: No se pudo guardar el archivo ZIP en: ' . $zip_file);
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    show_log('✅ Archivo ZIP guardado');
    
    show_log('📦 Abriendo archivo ZIP...');
    
    // Extraer ZIP
    $zip = new ZipArchive;
    $zip_result = $zip->open($zip_file);
    
    if ($zip_result !== TRUE) {
        show_log('❌ ERROR: No se pudo abrir el archivo ZIP (código: ' . $zip_result . ').');
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    show_log('✅ Archivo ZIP abierto correctamente');
    
    show_log('📦 Extrayendo contenido del ZIP...');
    if (!$zip->extractTo($temp_dir)) {
        show_log('❌ ERROR: No se pudo extraer el archivo ZIP.');
        $zip->close();
        echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
        echo '</div>';
        return;
    }
    
    $zip->close();
    show_log('✅ Contenido del ZIP extraído');
    
    show_log('📝 Iniciando actualización de archivos...');
    
    // Solo actualizar los archivos más importantes
    $files_to_update = array(
        'assets/css/search.css',
        'assets/js/search.js',
        'includes/class-lexhoy-despachos-shortcode.php',
        'lexhoy-despachos.php'
    );
    
    $extracted_dir = $temp_dir . 'LexHoy-Despachos-main/';
    $updated_count = 0;
    $errors = array();
    
    show_log('📁 Directorio extraído: ' . $extracted_dir);
    
    foreach ($files_to_update as $file) {
        $source = $extracted_dir . $file;
        $destination = $plugin_dir . $file;
        
        show_log('🔄 Procesando: ' . $file);
        show_log('   📂 Origen: ' . $source);
        show_log('   📂 Destino: ' . $destination);
        
        if (file_exists($source)) {
            show_log('   ✅ Archivo origen encontrado');
            
            // Crear directorio si no existe
            $dir = dirname($destination);
            if (!file_exists($dir)) {
                show_log('   📁 Creando directorio: ' . $dir);
                if (!mkdir($dir, 0755, true)) {
                    $error_msg = "No se pudo crear el directorio: $dir";
                    $errors[] = $error_msg;
                    show_log('   ❌ ERROR: ' . $error_msg);
                    continue;
                }
                show_log('   ✅ Directorio creado');
            }
            
            show_log('   📋 Copiando archivo...');
            if (copy($source, $destination)) {
                $updated_count++;
                show_log('   ✅ Archivo copiado exitosamente');
                echo '<div class="notice notice-success"><p>✅ Actualizado: ' . $file . '</p></div>';
            } else {
                $error_msg = "No se pudo copiar: $file";
                $errors[] = $error_msg;
                show_log('   ❌ ERROR: ' . $error_msg);
            }
        } else {
            $error_msg = "Archivo no encontrado en el ZIP: $file";
            $errors[] = $error_msg;
            show_log('   ❌ ERROR: ' . $error_msg);
        }
    }
    
    show_log('🧹 Limpiando archivos temporales...');
    // Limpiar archivos temporales
    lexhoy_cleanup_temp_files($temp_dir);
    show_log('✅ Archivos temporales limpiados');
    
    if ($updated_count > 0) {
        show_log('🎉 ¡Actualización completada! Se actualizaron ' . $updated_count . ' archivos.');
        echo '<div class="notice notice-success"><p>🎉 <strong>¡Actualización completada!</strong> Se actualizaron ' . $updated_count . ' archivos.</p></div>';
        echo '<p><strong>Nota:</strong> Los cambios deberían estar visibles inmediatamente. Si no ves los cambios, limpia la caché de tu sitio.</p>';
    }
    
    if (!empty($errors)) {
        show_log('⚠️ Errores encontrados: ' . count($errors));
        echo '<div class="notice notice-warning"><p>⚠️ <strong>Algunos archivos no se pudieron actualizar:</strong></p><ul>';
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
    }
    
    show_log('🏁 Proceso de actualización finalizado');
    
    echo '<p><a href="' . admin_url('edit.php?post_type=despacho') . '" class="button button-primary">← Volver al menú de Despachos</a></p>';
    echo '</div>';
}

function lexhoy_cleanup_temp_files($temp_dir) {
    if (file_exists($temp_dir)) {
        // Limpiar archivos de forma más simple
        $files = glob($temp_dir . '*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                } elseif (is_dir($file)) {
                    // Limpiar subdirectorios de forma recursiva pero más simple
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
} 
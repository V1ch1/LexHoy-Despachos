<?php
/**
 * Descargador automático desde GitHub
 * 
 * Subir este archivo al servidor una vez y ejecutarlo cada vez que quieras actualizar
 * URL: https://lexhoy.com/wp-content/plugins/LexHoy-Despachos-main/download-from-github.php
 */

// ⚙️ CONFIGURACIÓN
$GITHUB_REPO = 'V1ch1/LexHoy-Despachos';
$PLUGIN_DIR = __DIR__; // Directorio actual del plugin

// 🔐 Seguridad básica (opcional)
$SECRET_KEY = 'lexhoy2024';
if (isset($_GET['key']) && $_GET['key'] !== $SECRET_KEY) {
    die('❌ Acceso denegado');
}

echo "<h2>🚀 Actualizador LexHoy Despachos</h2>\n";
echo "<pre>\n";

function log_message($message) {
    echo date('H:i:s') . " - " . $message . "\n";
    flush();
}

function download_github_zip() {
    global $GITHUB_REPO;
    
    $zip_url = "https://github.com/$GITHUB_REPO/archive/refs/heads/main.zip";
    $temp_zip = sys_get_temp_dir() . '/lexhoy-despachos.zip';
    
    log_message("📥 Descargando desde GitHub...");
    
    // Descargar ZIP
    $context = stream_context_create([
        'http' => [
            'user_agent' => 'LexHoy-Auto-Updater/1.0',
            'timeout' => 30
        ]
    ]);
    
    $zip_data = file_get_contents($zip_url, false, $context);
    
    if ($zip_data === false) {
        throw new Exception("Error descargando ZIP desde GitHub");
    }
    
    file_put_contents($temp_zip, $zip_data);
    log_message("✅ ZIP descargado: " . number_format(filesize($temp_zip) / 1024, 1) . " KB");
    
    return $temp_zip;
}

function extract_and_update($zip_file) {
    global $PLUGIN_DIR;
    
    log_message("📦 Extrayendo archivos...");
    
    $zip = new ZipArchive();
    if ($zip->open($zip_file) !== TRUE) {
        throw new Exception("Error abriendo ZIP");
    }
    
    // Crear backup del archivo principal
    $main_file = $PLUGIN_DIR . '/lexhoy-despachos.php';
    if (file_exists($main_file)) {
        copy($main_file, $main_file . '.backup.' . date('YmdHis'));
        log_message("💾 Backup creado");
    }
    
    $updated_files = 0;
    
    // Archivos importantes a actualizar
    $files_to_update = [
        'lexhoy-despachos.php',
        'assets/css/search.css',
        'assets/js/search.js',
        'includes/class-lexhoy-despachos-cpt.php',
        'includes/class-lexhoy-despachos-shortcode.php',
        'includes/class-lexhoy-algolia-client.php',
        'templates/single-despacho.php'
    ];
    
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $file_info = $zip->statIndex($i);
        $filename = $file_info['name'];
        
        // Saltar el directorio raíz del ZIP (LexHoy-Despachos-main/)
        $relative_path = preg_replace('/^[^\/]+\//', '', $filename);
        
        if (empty($relative_path) || substr($relative_path, -1) === '/') {
            continue; // Saltar directorios
        }
        
        // Solo actualizar archivos importantes
        if (!in_array($relative_path, $files_to_update)) {
            continue;
        }
        
        $target_file = $PLUGIN_DIR . '/' . $relative_path;
        $target_dir = dirname($target_file);
        
        // Crear directorio si no existe
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        // Extraer archivo
        $file_content = $zip->getFromIndex($i);
        if ($file_content !== false) {
            file_put_contents($target_file, $file_content);
            log_message("✅ Actualizado: $relative_path");
            $updated_files++;
        }
    }
    
    $zip->close();
    unlink($zip_file);
    
    log_message("🎯 Actualización completada: $updated_files archivos");
    return $updated_files;
}

try {
    log_message("🔄 Iniciando actualización automática...");
    
    $zip_file = download_github_zip();
    $updated = extract_and_update($zip_file);
    
    echo "\n";
    log_message("✅ ACTUALIZACIÓN EXITOSA");
    echo "</pre>\n";
    echo "<p><strong>✅ Plugin actualizado correctamente</strong></p>\n";
    echo "<p>Archivos actualizados: $updated</p>\n";
    echo "<p><a href='?key=$SECRET_KEY'>🔄 Actualizar de nuevo</a></p>\n";
    
} catch (Exception $e) {
    log_message("❌ ERROR: " . $e->getMessage());
    echo "</pre>\n";
    echo "<p><strong>❌ Error en la actualización</strong></p>\n";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
}
?> 
<?php
/**
 * Script para actualizar manualmente el plugin desde GitHub
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>🔄 Actualización Manual del Plugin</h1>";

// 1. Obtener información del release
$github_url = 'https://api.github.com/repos/V1ch1/LexHoy-Despachos/releases/latest';

echo "<h2>1. Verificando release en GitHub...</h2>";

$response = wp_remote_get($github_url, array(
    'timeout' => 15,
    'headers' => array(
        'User-Agent' => 'WordPress/' . get_bloginfo('version')
    )
));

if (is_wp_error($response)) {
    echo "<p style='color: red;'>❌ Error al conectar con GitHub: " . $response->get_error_message() . "</p>";
    exit;
}

$release = json_decode(wp_remote_retrieve_body($response));

if (!$release || !isset($release->tag_name)) {
    echo "<p style='color: red;'>❌ No se pudo obtener información del release</p>";
    exit;
}

echo "<p>✅ Release encontrado: <strong>" . $release->tag_name . "</strong></p>";
echo "<p>📅 Fecha: " . $release->published_at . "</p>";

// 2. Buscar el ZIP del plugin
$download_url = '';
$zip_name = '';

if (isset($release->assets) && is_array($release->assets)) {
    foreach ($release->assets as $asset) {
        if (strpos($asset->name, 'lexhoy-despachos.zip') !== false) {
            $download_url = $asset->browser_download_url;
            $zip_name = $asset->name;
            break;
        }
    }
}

if (empty($download_url)) {
    echo "<p style='color: red;'>❌ No se encontró el ZIP del plugin</p>";
    exit;
}

echo "<p>✅ ZIP encontrado: " . $zip_name . "</p>";
echo "<p>🔗 URL: " . $download_url . "</p>";

// 3. Descargar el ZIP
echo "<h2>2. Descargando plugin...</h2>";

$zip_response = wp_remote_get($download_url, array(
    'timeout' => 60,
    'stream' => true,
    'filename' => WP_CONTENT_DIR . '/plugins/lexhoy-despachos-temp.zip'
));

if (is_wp_error($zip_response)) {
    echo "<p style='color: red;'>❌ Error al descargar: " . $zip_response->get_error_message() . "</p>";
    exit;
}

$zip_file = WP_CONTENT_DIR . '/plugins/lexhoy-despachos-temp.zip';

if (!file_exists($zip_file)) {
    echo "<p style='color: red;'>❌ No se pudo descargar el archivo</p>";
    exit;
}

echo "<p>✅ Archivo descargado: " . number_format(filesize($zip_file)) . " bytes</p>";

// 4. Crear directorio temporal
$temp_dir = WP_CONTENT_DIR . '/plugins/lexhoy-despachos-temp/';
if (is_dir($temp_dir)) {
    rmdir_recursive($temp_dir);
}
mkdir($temp_dir);

// 5. Extraer ZIP
echo "<h2>3. Extrayendo archivos...</h2>";

$zip = new ZipArchive();
if ($zip->open($zip_file) === TRUE) {
    $zip->extractTo($temp_dir);
    $zip->close();
    echo "<p>✅ Archivos extraídos</p>";
} else {
    echo "<p style='color: red;'>❌ Error al extraer el ZIP</p>";
    exit;
}

// 6. Verificar estructura
$plugin_file = $temp_dir . 'lexhoy-despachos.php';
if (!file_exists($plugin_file)) {
    echo "<p style='color: red;'>❌ No se encontró el archivo principal del plugin</p>";
    exit;
}

echo "<p>✅ Estructura del plugin verificada</p>";

// 7. Hacer backup del plugin actual
$current_plugin_dir = WP_CONTENT_DIR . '/plugins/LexHoy-Despachos/';
$backup_dir = WP_CONTENT_DIR . '/plugins/LexHoy-Despachos-backup-' . date('Y-m-d-H-i-s') . '/';

if (is_dir($current_plugin_dir)) {
    copy_dir($current_plugin_dir, $backup_dir);
    echo "<p>✅ Backup creado: " . basename($backup_dir) . "</p>";
}

// 8. Reemplazar archivos
echo "<h2>4. Actualizando plugin...</h2>";

// Eliminar plugin actual
if (is_dir($current_plugin_dir)) {
    rmdir_recursive($current_plugin_dir);
}

// Mover archivos nuevos
$extracted_dir = $temp_dir;
$files = scandir($extracted_dir);
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        $source = $extracted_dir . $file;
        $dest = $current_plugin_dir . $file;
        
        if (is_dir($source)) {
            copy_dir($source, $dest);
        } else {
            copy($source, $dest);
        }
    }
}

echo "<p>✅ Plugin actualizado</p>";

// 9. Limpiar archivos temporales
unlink($zip_file);
rmdir_recursive($temp_dir);

echo "<p>✅ Archivos temporales eliminados</p>";

// 10. Verificar versión
$plugin_data = get_plugin_data($current_plugin_dir . 'lexhoy-despachos.php');
echo "<h2>5. Verificación final</h2>";
echo "<p><strong>Versión actual:</strong> " . $plugin_data['Version'] . "</p>";
echo "<p><strong>Release de GitHub:</strong> " . $release->tag_name . "</p>";

if ($plugin_data['Version'] == $release->tag_name) {
    echo "<p style='color: green; font-size: 18px;'>🎉 ¡Plugin actualizado exitosamente!</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Las versiones no coinciden</p>";
}

echo "<hr>";
echo "<p><a href='/wp-admin/plugins.php'>🔙 Volver a Plugins</a></p>";
echo "<p><a href='javascript:location.reload()'>🔄 Actualizar</a></p>";

// Función auxiliar para copiar directorios
function copy_dir($src, $dst) {
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    
    $files = scandir($src);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $source = $src . '/' . $file;
            $dest = $dst . '/' . $file;
            
            if (is_dir($source)) {
                copy_dir($source, $dest);
            } else {
                copy($source, $dest);
            }
        }
    }
}

// Función auxiliar para eliminar directorios recursivamente
function rmdir_recursive($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                rmdir_recursive($path);
            } else {
                unlink($path);
            }
        }
    }
    
    rmdir($dir);
}
?> 
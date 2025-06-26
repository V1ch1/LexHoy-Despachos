<?php
/**
 * Script para sincronizar cambios directamente a producción
 * Ejecutar desde terminal: php sync-to-production.php
 * O desde navegador: https://tudominio.com/wp-content/plugins/LexHoy-Despachos/sync-to-production.php
 */

// Configuración
$PRODUCTION_URL = 'https://lexhoy.com'; // Cambiar por tu URL de producción
$PRODUCTION_PATH = '/wp-content/plugins/LexHoy-Despachos/'; // Ruta en producción
$GITHUB_REPO = 'V1ch1/LexHoy-Despachos';

// FTP eliminado - solo método GitHub

// Función para logs
function log_message($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    if (function_exists('error_log')) {
        error_log("LexHoy Deploy: $message");
    }
}

// Función para mostrar ayuda
function show_help() {
    echo "\n🚀 LexHoy Despachos - Deploy Directo\n";
    echo "=====================================\n\n";
    echo "Uso:\n";
    echo "  php sync-to-production.php [comando]\n\n";
    echo "Comandos disponibles:\n";
    echo "  push       - Subir cambios a GitHub\n";
    echo "  deploy     - Descargar desde GitHub a producción\n";
    echo "  full       - Push + Deploy completo\n";
    echo "  custom     - Deploy con mensaje personalizado\n";
    echo "  status     - Ver estado actual\n";
    echo "  help       - Mostrar esta ayuda\n\n";
    echo "Ejemplos:\n";
    echo "  php sync-to-production.php push\n";
    echo "  php sync-to-production.php full\n";
    echo "  php sync-to-production.php custom \"Arreglo scroll paginación\"\n";
    echo "  deploy-custom.bat \"Mejora UX navegación\"\n\n";
    echo "📋 MÉTODO ALTERNATIVO (si FTP no funciona):\n";
    echo "1. Subir download-from-github.php al servidor (una vez)\n";
    echo "2. Visitar: https://lexhoy.com/wp-content/plugins/LexHoy-Despachos-main/download-from-github.php?key=lexhoy2024\n\n";
}

// Función para verificar si estamos en WordPress
function is_wordpress_context() {
    return defined('ABSPATH') || file_exists('../../../wp-config.php');
}

// Función para cargar WordPress si es necesario
function load_wordpress() {
    if (!defined('ABSPATH')) {
        if (file_exists('../../../wp-load.php')) {
            require_once('../../../wp-load.php');
            return true;
        }
        return false;
    }
    return true;
}

// Esta función ya no es necesaria - usando método GitHub
function upload_to_ftp() {
    log_message("⚠️  FTP no disponible - Usar método GitHub:");
    log_message("📋 1. Subir download-from-github.php al servidor");
    log_message("🌐 2. Visitar: https://lexhoy.com/wp-content/plugins/LexHoy-Despachos-main/download-from-github.php?key=lexhoy2024");
    return false;
}

// Función auxiliar eliminada (ya no se usa FTP)

// Función para generar mensaje de commit descriptivo
function generate_commit_message($new_version) {
    // Detectar cambios en archivos para generar mensaje automático
    $changed_files = [];
    $git_status = shell_exec('git status --porcelain 2>/dev/null');
    
    if (!empty($git_status)) {
        $lines = explode("\n", trim($git_status));
        foreach ($lines as $line) {
            if (!empty(trim($line))) {
                $file = trim(substr($line, 2)); // Quitar los primeros 2 caracteres (M, A, etc.)
                $changed_files[] = $file;
            }
        }
    }
    
    // Generar mensaje basado en archivos modificados
    $changes = [];
    
    foreach ($changed_files as $file) {
        if (strpos($file, 'assets/js/') !== false) {
            $changes[] = "JavaScript mejorado";
        } elseif (strpos($file, 'assets/css/') !== false) {
            $changes[] = "Estilos actualizados";
        } elseif (strpos($file, 'includes/') !== false) {
            $changes[] = "Funcionalidad backend";
        } elseif (strpos($file, 'templates/') !== false) {
            $changes[] = "Templates actualizados";
        } elseif (strpos($file, 'admin/') !== false) {
            $changes[] = "Panel admin mejorado";
        } elseif ($file === 'lexhoy-despachos.php') {
            // No agregar nada para el archivo principal (solo versión)
        } elseif (strpos($file, '.php') !== false) {
            $changes[] = "Lógica PHP mejorada";
        } elseif (strpos($file, '.md') !== false) {
            $changes[] = "Documentación";
        } elseif (strpos($file, '.bat') !== false || strpos($file, 'sync-') !== false) {
            $changes[] = "Scripts deploy";
        }
    }
    
    // Eliminar duplicados
    $changes = array_unique($changes);
    
    // Generar mensaje final
    if (empty($changes)) {
        return "v$new_version - Actualización de versión";
    } elseif (count($changes) === 1) {
        return "v$new_version - " . $changes[0];
    } else {
        return "v$new_version - " . implode(", ", array_slice($changes, 0, 3));
    }
}

// Función para hacer push a GitHub
function push_to_github($custom_message = '') {
    log_message("🚀 Iniciando push a GitHub...");
    
    // Verificar que Git esté disponible
    $git_check = shell_exec('git --version 2>&1');
    if (empty($git_check) || strpos($git_check, 'git version') === false) {
        log_message("❌ ERROR: Git no está instalado o no está en el PATH");
        return false;
    }
    
    log_message("✅ Git disponible: " . trim($git_check));
    
    // Verificar que estamos en un repositorio Git
    if (!is_dir('.git')) {
        log_message("❌ ERROR: No estamos en un repositorio Git");
        log_message("💡 Ejecuta: git init && git remote add origin https://github.com/V1ch1/LexHoy-Despachos.git");
        return false;
    }
    
    // Obtener versión actual
    $current_version = '1.0.20'; // Fallback
    if (file_exists('lexhoy-despachos.php')) {
        $content = file_get_contents('lexhoy-despachos.php');
        if (preg_match("/define\('LEXHOY_DESPACHOS_VERSION',\s*'([^']+)'/", $content, $matches)) {
            $current_version = $matches[1];
        }
    }
    
    // Calcular nueva versión
    $parts = explode('.', $current_version);
    $parts[2] = (int)$parts[2] + 1;
    $new_version = implode('.', $parts);
    
    log_message("📝 Versión actual: $current_version");
    log_message("📝 Nueva versión: $new_version");
    
    // Actualizar versión en el archivo principal
    if (file_exists('lexhoy-despachos.php')) {
        $content = file_get_contents('lexhoy-despachos.php');
        $content = preg_replace('/Version:\s*[\d\.]+/', "Version: $new_version", $content);
        $content = preg_replace("/define\('LEXHOY_DESPACHOS_VERSION',\s*'[^']*'\)/", "define('LEXHOY_DESPACHOS_VERSION', '$new_version')", $content);
        file_put_contents('lexhoy-despachos.php', $content);
        log_message("✅ Versión actualizada en lexhoy-despachos.php");
    }
    
    // Generar mensaje de commit (personalizado o automático)
    if (!empty($custom_message)) {
        $commit_message = "v$new_version - $custom_message";
        log_message("📝 Usando mensaje personalizado: $custom_message");
    } else {
        $commit_message = generate_commit_message($new_version);
        log_message("📝 Generando mensaje automático...");
    }
    
    // Git add, commit y push
    $commands = [
        'git add .',
        'git status --porcelain',
        "git commit -m \"$commit_message\"",
        'git push origin main'
    ];
    
    foreach ($commands as $cmd) {
        log_message("🔧 Ejecutando: $cmd");
        $output = shell_exec("$cmd 2>&1");
        
        if ($cmd === 'git status --porcelain') {
            if (empty(trim($output))) {
                log_message("ℹ️  No hay cambios para commit");
                continue;
            } else {
                log_message("📝 Cambios detectados:\n" . trim($output));
            }
        }
        
        if (!empty($output)) {
            log_message("📤 " . trim($output));
        }
    }
    
    log_message("✅ Push a GitHub completado");
    return true;
}

// Función para deploy a producción
function deploy_to_production() {
    log_message("🎯 Iniciando deploy a producción...");
    
    if (!load_wordpress()) {
        log_message("⚠️  WordPress no disponible - Continuando con deploy directo");
    }
    
    // Obtener archivos desde GitHub
    $zip_url = "https://github.com/V1ch1/LexHoy-Despachos/archive/refs/heads/main.zip";
    log_message("📥 Descargando desde: $zip_url");
    
    $temp_file = sys_get_temp_dir() . '/lexhoy-despachos-' . time() . '.zip';
    
    // Descargar usando cURL o file_get_contents
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $zip_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'LexHoy-Deploy-Script');
        $zip_content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200 || empty($zip_content)) {
            log_message("❌ ERROR: No se pudo descargar desde GitHub (HTTP: $http_code)");
            return false;
        }
    } else {
        $zip_content = file_get_contents($zip_url);
        if ($zip_content === false) {
            log_message("❌ ERROR: No se pudo descargar desde GitHub");
            return false;
        }
    }
    
    // Guardar archivo temporal
    if (file_put_contents($temp_file, $zip_content) === false) {
        log_message("❌ ERROR: No se pudo guardar archivo temporal");
        return false;
    }
    
    log_message("✅ Archivo descargado: " . number_format(filesize($temp_file)) . " bytes");
    
    // Extraer ZIP
    if (!class_exists('ZipArchive')) {
        log_message("❌ ERROR: ZipArchive no está disponible");
        unlink($temp_file);
        return false;
    }
    
    $zip = new ZipArchive();
    $temp_dir = sys_get_temp_dir() . '/lexhoy-extract-' . time() . '/';
    
    if ($zip->open($temp_file) === TRUE) {
        mkdir($temp_dir);
        $zip->extractTo($temp_dir);
        $zip->close();
        log_message("✅ Archivos extraídos a: $temp_dir");
    } else {
        log_message("❌ ERROR: No se pudo extraer el ZIP");
        unlink($temp_file);
        return false;
    }
    
    // Encontrar directorio extraído
    $extracted_dir = '';
    $dirs = glob($temp_dir . '*', GLOB_ONLYDIR);
    if (!empty($dirs)) {
        $extracted_dir = $dirs[0] . '/';
    } else {
        $extracted_dir = $temp_dir;
    }
    
    log_message("📁 Directorio extraído: $extracted_dir");
    
    // Archivos críticos para actualizar
    $files_to_update = [
        'lexhoy-despachos.php',
        'assets/css/search.css',
        'assets/css/single-despacho.css', 
        'assets/css/lexhoy-despachos-admin.css',
        'assets/js/search.js',
        'includes/class-lexhoy-despachos-shortcode.php',
        'includes/class-lexhoy-despachos-cpt.php',
        'includes/class-lexhoy-algolia-client.php',
        'templates/single-despacho.php'
    ];
    
    $updated_count = 0;
    $current_dir = __DIR__ . '/';
    
    foreach ($files_to_update as $file) {
        $source = $extracted_dir . $file;
        $destination = $current_dir . $file;
        
        if (file_exists($source)) {
            // Crear directorio si no existe
            $dir = dirname($destination);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            if (copy($source, $destination)) {
                $updated_count++;
                log_message("✅ Actualizado: $file");
            } else {
                log_message("❌ ERROR al copiar: $file");
            }
        } else {
            log_message("⚠️  Archivo no encontrado en descarga: $file");
        }
    }
    
    // Limpiar archivos temporales
    unlink($temp_file);
    
    // Limpiar directorio temporal
    function removeDirectory($dir) {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? removeDirectory($path) : unlink($path);
            }
            rmdir($dir);
        }
    }
    removeDirectory($temp_dir);
    
    log_message("🧹 Archivos temporales limpiados");
    log_message("✅ Deploy completado - Archivos actualizados: $updated_count");
    
    // Limpiar caché si WordPress está disponible
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
        log_message("🧹 Caché de WordPress limpiado");
    }
    
    return true;
}

// Función para mostrar estado
function show_status() {
    log_message("📊 Estado actual del sistema");
    echo "\n";
    
    // Versión actual
    if (file_exists('lexhoy-despachos.php')) {
        $content = file_get_contents('lexhoy-despachos.php');
        if (preg_match("/define\('LEXHOY_DESPACHOS_VERSION',\s*'([^']+)'/", $content, $matches)) {
            echo "📝 Versión actual: " . $matches[1] . "\n";
        }
    }
    
    // Estado de Git
    if (is_dir('.git')) {
        $branch = trim(shell_exec('git branch --show-current 2>/dev/null'));
        $status = shell_exec('git status --porcelain 2>/dev/null');
        $last_commit = trim(shell_exec('git log -1 --format="%h %s" 2>/dev/null'));
        
        echo "🌿 Rama actual: $branch\n";
        echo "📝 Último commit: $last_commit\n";
        echo "📊 Cambios pendientes: " . (empty(trim($status)) ? "Ninguno" : "Sí") . "\n";
        
        if (!empty(trim($status))) {
            echo "   📋 Archivos modificados:\n";
            foreach (explode("\n", trim($status)) as $line) {
                if (!empty($line)) {
                    echo "      $line\n";
                }
            }
        }
    } else {
        echo "⚠️  No es un repositorio Git\n";
    }
    
    // Archivos principales
    $files = ['lexhoy-despachos.php', 'assets/css/search.css', 'assets/js/search.js'];
    echo "\n📁 Archivos principales:\n";
    foreach ($files as $file) {
        $status = file_exists($file) ? "✅" : "❌";
        $size = file_exists($file) ? " (" . number_format(filesize($file)) . " bytes)" : "";
        echo "   $status $file$size\n";
    }
    
    echo "\n";
}

// Script principal
if (php_sapi_name() === 'cli') {
    // Ejecutado desde terminal
    $command = isset($argv[1]) ? $argv[1] : 'help';
    $custom_message = isset($argv[2]) ? $argv[2] : '';
} else {
    // Ejecutado desde navegador
    $command = isset($_GET['action']) ? $_GET['action'] : 'help';
    $custom_message = isset($_GET['message']) ? $_GET['message'] : '';
    echo "<pre>";
}

switch ($command) {
    case 'push':
        push_to_github($custom_message);
        break;
        
    case 'deploy':
        deploy_to_production();
        break;
        
    case 'full':
        log_message("🚀 Iniciando push a GitHub...");
        push_to_github($custom_message);
        log_message("📋 Para actualizar producción:");
        log_message("🌐 Visita: https://lexhoy.com/wp-content/plugins/LexHoy-Despachos-main/download-from-github.php?key=lexhoy2024");
        break;
        
    case 'custom':
        log_message("🚀 Iniciando deploy con mensaje personalizado...");
        if (push_to_github($custom_message)) {
            sleep(2);
            upload_to_ftp();
        }
        break;
        
    case 'status':
        show_status();
        break;
        
    case 'help':
    default:
        show_help();
        break;
}

if (php_sapi_name() !== 'cli') {
    echo "</pre>";
    echo "<hr>";
    echo "<p><strong>Enlaces rápidos:</strong></p>";
    echo "<p><a href='?action=status'>📊 Ver Estado</a> | ";
    echo "<a href='?action=push'>📤 Push a GitHub</a> | ";
    echo "<a href='?action=deploy'>🎯 Deploy a Producción</a> | ";
    echo "<a href='?action=full'>🚀 Deploy Completo</a></p>";
}
?> 
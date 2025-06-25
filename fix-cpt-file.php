<?php
/**
 * Script para limpiar el archivo class-lexhoy-despachos-cpt.php
 * Elimina las funciones duplicadas del sistema de actualizaciones
 */

$file_path = 'includes/class-lexhoy-despachos-cpt.php';

if (!file_exists($file_path)) {
    die("El archivo no existe: $file_path");
}

echo "Leyendo archivo...\n";
$content = file_get_contents($file_path);
$lines = explode("\n", $content);

echo "Total de líneas: " . count($lines) . "\n";

// Buscar el final de la clase
$class_end_line = -1;
for ($i = count($lines) - 1; $i >= 0; $i--) {
    if (trim($lines[$i]) === '}') {
        $class_end_line = $i;
        break;
    }
}

if ($class_end_line === -1) {
    die("No se pudo encontrar el final de la clase");
}

echo "Final de la clase encontrado en línea: " . ($class_end_line + 1) . "\n";

// Buscar el inicio de la función duplicada
$duplicate_start = -1;
for ($i = $class_end_line + 1; $i < count($lines); $i++) {
    if (strpos($lines[$i], '// Sistema de actualizaciones automáticas desde GitHub') !== false) {
        $duplicate_start = $i;
        break;
    }
}

if ($duplicate_start === -1) {
    echo "No se encontró función duplicada\n";
    exit;
}

echo "Función duplicada encontrada en línea: " . ($duplicate_start + 1) . "\n";

// Crear nuevo contenido sin la función duplicada
$new_lines = array_slice($lines, 0, $class_end_line + 1);
$new_content = implode("\n", $new_lines);

// Guardar archivo limpio
file_put_contents($file_path, $new_content);

echo "Archivo limpiado exitosamente\n";
echo "Nuevas líneas: " . count($new_lines) . "\n";

// Verificar sintaxis
$syntax_check = shell_exec("php -l $file_path 2>&1");
echo "Verificación de sintaxis:\n$syntax_check";
?> 
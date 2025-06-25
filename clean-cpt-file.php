<?php
/**
 * Script para limpiar completamente el archivo class-lexhoy-despachos-cpt.php
 */

$file_path = 'includes/class-lexhoy-despachos-cpt.php';

if (!file_exists($file_path)) {
    die("El archivo no existe: $file_path");
}

echo "Leyendo archivo...\n";
$content = file_get_contents($file_path);
$lines = explode("\n", $content);

echo "Total de líneas: " . count($lines) . "\n";

// Buscar el final de la clase (último })
$class_end_line = -1;
for ($i = count($lines) - 1; $i >= 0; $i--) {
    $line = trim($lines[$i]);
    if ($line === '}') {
        // Verificar que es el cierre de la clase, no de una función
        $prev_line = trim($lines[$i-1]);
        if (strpos($prev_line, 'return $template;') !== false) {
            $class_end_line = $i;
            break;
        }
    }
}

if ($class_end_line === -1) {
    die("No se pudo encontrar el final de la clase");
}

echo "Final de la clase encontrado en línea: " . ($class_end_line + 1) . "\n";

// Crear nuevo contenido solo hasta el final de la clase
$new_lines = array_slice($lines, 0, $class_end_line + 1);
$new_content = implode("\n", $new_lines);

// Guardar archivo limpio
file_put_contents($file_path, $new_content);

echo "Archivo limpiado exitosamente\n";
echo "Nuevas líneas: " . count($new_lines) . "\n";

// Verificar sintaxis
$syntax_check = shell_exec("php -l $file_path 2>&1");
echo "Verificación de sintaxis:\n$syntax_check";

// Mostrar las últimas líneas para verificar
echo "\nÚltimas 5 líneas del archivo:\n";
$last_lines = array_slice($new_lines, -5);
foreach ($last_lines as $i => $line) {
    echo (count($new_lines) - 4 + $i) . ": " . $line . "\n";
}
?> 
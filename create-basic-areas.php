<?php
/**
 * Script para crear áreas de práctica básicas automáticamente
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>🏗️ Creando Áreas de Práctica Básicas</h1>\n";

// Lista de áreas de práctica comunes
$basic_areas = array(
    'Derecho Civil',
    'Derecho Penal',
    'Derecho Laboral',
    'Derecho Mercantil',
    'Derecho Administrativo',
    'Derecho Fiscal',
    'Derecho de Familia',
    'Derecho Inmobiliario',
    'Derecho de Seguros',
    'Derecho de Consumo',
    'Derecho de Propiedad Intelectual',
    'Derecho Internacional',
    'Derecho Constitucional',
    'Derecho Procesal',
    'Derecho de Sociedades',
    'Derecho Concursal',
    'Derecho de Transporte',
    'Derecho Marítimo',
    'Derecho Bancario',
    'Derecho de Nuevas Tecnologías'
);

$created_count = 0;
$existing_count = 0;
$errors = array();

echo "<h2>📋 Procesando áreas...</h2>\n";

foreach ($basic_areas as $area_name) {
    echo "<p>Procesando: <strong>{$area_name}</strong>... ";
    
    // Verificar si ya existe
    $existing_term = term_exists($area_name, 'area_practica');
    
    if ($existing_term) {
        echo "<span style='color: orange;'>⚠️ Ya existe</span></p>\n";
        $existing_count++;
    } else {
        // Crear el término
        $result = wp_insert_term($area_name, 'area_practica');
        
        if (is_wp_error($result)) {
            echo "<span style='color: red;'>❌ Error: " . $result->get_error_message() . "</span></p>\n";
            $errors[] = $area_name . ': ' . $result->get_error_message();
        } else {
            echo "<span style='color: green;'>✅ Creado (ID: {$result['term_id']})</span></p>\n";
            $created_count++;
        }
    }
}

// Resumen
echo "<h2>📊 Resumen</h2>\n";
echo "<div style='background: #d4edda; padding: 15px; border-left: 4px solid #28a745;'>\n";
echo "<p><strong>✅ Áreas creadas:</strong> {$created_count}</p>\n";
echo "<p><strong>⚠️ Áreas existentes:</strong> {$existing_count}</p>\n";
echo "<p><strong>❌ Errores:</strong> " . count($errors) . "</p>\n";
echo "</div>\n";

if (!empty($errors)) {
    echo "<h3>❌ Errores encontrados:</h3>\n";
    echo "<ul>\n";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>\n";
    }
    echo "</ul>\n";
}

// Verificar áreas creadas
echo "<h3>📋 Áreas disponibles ahora:</h3>\n";
$all_areas = get_terms(array(
    'taxonomy' => 'area_practica',
    'hide_empty' => false,
));

if (!empty($all_areas)) {
    echo "<ul>\n";
    foreach ($all_areas as $area) {
        echo "<li><strong>{$area->name}</strong> (ID: {$area->term_id})</li>\n";
    }
    echo "</ul>\n";
} else {
    echo "<p>❌ No se encontraron áreas de práctica.</p>\n";
}

echo "<h3>🎉 ¡Listo!</h3>\n";
echo "<p>Ahora puedes:</p>\n";
echo "<ol>\n";
echo "<li>Ir a <strong>Despachos > Añadir Nuevo</strong> para crear despachos</li>\n";
echo "<li>Asignar áreas de práctica a los despachos</li>\n";
echo "<li>Usar la <strong>Importación Masiva</strong> si tienes datos en Algolia</li>\n";
echo "</ol>\n";
?> 
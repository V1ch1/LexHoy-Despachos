<?php
/**
 * Configuración de Archivos JSON - LexHoy Despachos
 * Generado automáticamente por actualizar-referencias-optimizado.php
 */

// Archivo JSON principal a usar
define('LEXHOY_DESPACHOS_JSON_FILE', 'despachos_optimizados.json');

// Ruta completa al archivo
define('LEXHOY_DESPACHOS_JSON_PATH', __DIR__ . '/data/' . LEXHOY_DESPACHOS_JSON_FILE);

// Función helper para obtener la ruta del JSON
function get_lexhoy_despachos_json_path() {
    return LEXHOY_DESPACHOS_JSON_PATH;
}

// Función helper para cargar datos de despachos
function load_lexhoy_despachos_data() {
    $file = get_lexhoy_despachos_json_path();
    
    if (!file_exists($file)) {
        return false;
    }
    
    $content = file_get_contents($file);
    return json_decode($content, true);
}

// Función helper para extraer sedes de un despacho
function get_sedes_from_despacho($despacho) {
    if (isset($despacho['sedes']) && is_array($despacho['sedes'])) {
        // Estructura optimizada
        return $despacho['sedes'];
    } else {
        // Estructura antigua (compatibilidad)
        return [$despacho];
    }
}

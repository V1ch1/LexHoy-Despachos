<?php
/**
 * Script de diagnóstico de calidad de datos en Algolia
 * Analiza los registros para determinar cuántos son válidos vs vacíos
 */

// Cargar WordPress
require_once('../../../wp-config.php');

// Cargar la clase de Algolia
require_once('includes/class-lexhoy-algolia-client.php');

echo "🔍 DIAGNÓSTICO DE CALIDAD DE DATOS EN ALGOLIA\n";
echo "=============================================\n\n";

try {
    // Inicializar cliente de Algolia
    $algolia_client = new LexHoy_Algolia_Client();
    
    echo "📊 Analizando registros en Algolia...\n\n";
    
    // Obtener todos los registros
    $all_records = $algolia_client->browse_all_with_cursor();
    
    if (empty($all_records)) {
        echo "❌ No se encontraron registros en Algolia\n";
        exit;
    }
    
    $total_records = count($all_records);
    echo "📈 Total de registros encontrados: {$total_records}\n\n";
    
    // Contadores
    $valid_records = 0;
    $empty_records = 0;
    $generated_ids = 0;
    $sample_valid = [];
    $sample_empty = [];
    
    echo "🔍 Analizando calidad de registros...\n";
    
    foreach ($all_records as $index => $record) {
        // Verificar si es un registro generado por dashboard
        $is_generated = isset($record['objectID']) && 
                       strpos($record['objectID'], '_dashboard_generated_id') !== false;
        
        // Verificar si tiene datos válidos
        $has_valid_data = false;
        $required_fields = ['nombre', 'localidad', 'provincia', 'direccion', 'telefono', 'email'];
        
        foreach ($required_fields as $field) {
            if (!empty($record[$field])) {
                $has_valid_data = true;
                break;
            }
        }
        
        // Clasificar el registro
        if ($is_generated) {
            $generated_ids++;
        } elseif ($has_valid_data) {
            $valid_records++;
            if (count($sample_valid) < 3) {
                $sample_valid[] = $record;
            }
        } else {
            $empty_records++;
            if (count($sample_empty) < 3) {
                $sample_empty[] = $record;
            }
        }
        
        // Mostrar progreso cada 1000 registros
        if (($index + 1) % 1000 === 0) {
            echo "Procesados: " . ($index + 1) . "/{$total_records}\n";
        }
    }
    
    echo "\n📊 RESULTADOS DEL ANÁLISIS:\n";
    echo "============================\n";
    echo "✅ Registros válidos: {$valid_records}\n";
    echo "❌ Registros vacíos: {$empty_records}\n";
    echo "🔄 IDs generados por dashboard: {$generated_ids}\n";
    echo "📈 Total: {$total_records}\n\n";
    
    // Mostrar porcentajes
    $valid_percent = round(($valid_records / $total_records) * 100, 2);
    $empty_percent = round(($empty_records / $total_records) * 100, 2);
    $generated_percent = round(($generated_ids / $total_records) * 100, 2);
    
    echo "📊 PORCENTAJES:\n";
    echo "===============\n";
    echo "✅ Válidos: {$valid_percent}%\n";
    echo "❌ Vacíos: {$empty_percent}%\n";
    echo "🔄 Generados: {$generated_percent}%\n\n";
    
    // Mostrar ejemplos de registros válidos
    if (!empty($sample_valid)) {
        echo "✅ EJEMPLOS DE REGISTROS VÁLIDOS:\n";
        echo "==================================\n";
        foreach ($sample_valid as $i => $record) {
            echo "Ejemplo " . ($i + 1) . ":\n";
            echo "  Nombre: " . ($record['nombre'] ?? 'N/A') . "\n";
            echo "  Localidad: " . ($record['localidad'] ?? 'N/A') . "\n";
            echo "  Provincia: " . ($record['provincia'] ?? 'N/A') . "\n";
            echo "  Teléfono: " . ($record['telefono'] ?? 'N/A') . "\n";
            echo "  ObjectID: " . ($record['objectID'] ?? 'N/A') . "\n";
            echo "\n";
        }
    }
    
    // Mostrar ejemplos de registros vacíos
    if (!empty($sample_empty)) {
        echo "❌ EJEMPLOS DE REGISTROS VACÍOS:\n";
        echo "================================\n";
        foreach ($sample_empty as $i => $record) {
            echo "Ejemplo " . ($i + 1) . ":\n";
            echo "  Nombre: " . ($record['nombre'] ?? 'N/A') . "\n";
            echo "  Localidad: " . ($record['localidad'] ?? 'N/A') . "\n";
            echo "  Provincia: " . ($record['provincia'] ?? 'N/A') . "\n";
            echo "  ObjectID: " . ($record['objectID'] ?? 'N/A') . "\n";
            echo "\n";
        }
    }
    
    // Recomendaciones
    echo "💡 RECOMENDACIONES:\n";
    echo "==================\n";
    
    if ($valid_records > 0) {
        echo "✅ Tienes {$valid_records} registros válidos para migrar\n";
        echo "🔄 Puedes proceder con la migración desde Algolia a WordPress\n";
    } else {
        echo "❌ No hay registros válidos en Algolia\n";
        echo "🔄 Deberías reexportar desde WordPress a Algolia\n";
    }
    
    if ($empty_records > 0 || $generated_ids > 0) {
        echo "🧹 Se recomienda limpiar los registros vacíos y generados\n";
        echo "   - Registros vacíos: {$empty_records}\n";
        echo "   - IDs generados: {$generated_ids}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📋 Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 
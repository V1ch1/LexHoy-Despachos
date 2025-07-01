<?php
/**
 * Script de diagnóstico para verificar los títulos del plugin
 */

echo "🔍 DIAGNÓSTICO DE TÍTULOS DEL PLUGIN LEXHOY DESPACHOS\n";
echo "==================================================\n\n";

// Verificar si WordPress está cargado
if (!function_exists('wp_head')) {
    echo "❌ WordPress no está disponible en este contexto\n";
    echo "💡 Esto es normal - estamos ejecutando fuera de WordPress\n\n";
}

// Verificar archivos modificados
$files_to_check = [
    'includes/class-lexhoy-despachos-cpt.php',
    'includes/class-lexhoy-despachos-shortcode.php'
];

echo "📄 VERIFICANDO ARCHIVOS MODIFICADOS:\n";
foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $modified_date = date('Y-m-d H:i:s', filemtime($file));
        
        echo "✅ $file\n";
        echo "   📅 Última modificación: $modified_date\n";
        
        // Verificar si contiene los filtros que agregamos
        if (strpos($content, 'document_title_parts') !== false) {
            echo "   ✅ Contiene filtro document_title_parts\n";
        } else {
            echo "   ❌ NO contiene filtro document_title_parts\n";
        }
        
        if (strpos($content, 'wp_title') !== false) {
            echo "   ✅ Contiene filtro wp_title\n";
        } else {
            echo "   ❌ NO contiene filtro wp_title\n";
        }
        echo "\n";
    } else {
        echo "❌ $file - NO ENCONTRADO\n\n";
    }
}

echo "🚀 POSIBLES PROBLEMAS Y SOLUCIONES:\n";
echo "1. El plugin puede estar desactivado en WordPress\n";
echo "2. WordPress puede estar usando caché\n";
echo "3. Puede haber conflictos con otros plugins\n";
echo "4. Los filtros pueden necesitar ser más específicos\n\n";

echo "🔧 PRÓXIMOS PASOS RECOMENDADOS:\n";
echo "1. Verificar que el plugin esté activado en WordPress\n";
echo "2. Limpiar toda la caché de WordPress\n";
echo "3. Probar en modo incógnito del navegador\n";
echo "4. Verificar si hay otros plugins de SEO que puedan interferir\n\n";

echo "🌐 PARA VERIFICAR MANUALMENTE:\n";
echo "- Ve al panel de WordPress > Plugins\n";
echo "- Busca 'LexHoy Despachos' y verifica que esté activo\n";
echo "- Ve a 'Herramientas' > 'Caché' y limpia toda la caché\n";
?> 
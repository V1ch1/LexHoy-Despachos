<?php
/**
 * Script de diagnóstico para problemas con títulos de páginas
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Se requieren permisos de administrador');
}

echo "<h1>🔍 Diagnóstico de Títulos de Páginas</h1>\n";

// 1. Verificar si los hooks están registrados
echo "<h2>1. 📋 Verificación de Hooks Registrados</h2>\n";

global $wp_filter;

$hooks_to_check = [
    'document_title_parts',
    'wp_title',
    'wp_head'
];

foreach ($hooks_to_check as $hook) {
    echo "<h3>Hook: <code>$hook</code></h3>\n";
    
    if (isset($wp_filter[$hook])) {
        echo "<p>✅ Hook registrado con " . count($wp_filter[$hook]->callbacks) . " callbacks</p>\n";
        
        foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback_id => $callback_info) {
                $callback = $callback_info['function'];
                
                if (is_array($callback)) {
                    if (is_object($callback[0])) {
                        $class_name = get_class($callback[0]);
                        $method_name = $callback[1];
                        echo "<ul><li>Prioridad $priority: <code>$class_name::$method_name</code></li></ul>\n";
                    } else {
                        echo "<ul><li>Prioridad $priority: <code>" . $callback[0] . "::" . $callback[1] . "</code></li></ul>\n";
                    }
                } else {
                    echo "<ul><li>Prioridad $priority: <code>$callback</code></li></ul>\n";
                }
            }
        }
    } else {
        echo "<p>❌ Hook NO registrado</p>\n";
    }
}

// 2. Simular detección de páginas del buscador
echo "<h2>2. 🔍 Simulación de Detección de Páginas</h2>\n";

// Buscar páginas que contengan el shortcode
$pages_with_shortcode = get_posts([
    'post_type' => 'page',
    'post_status' => 'publish',
    'meta_query' => [
        [
            'key' => '_wp_page_template',
            'compare' => 'EXISTS'
        ]
    ],
    'numberposts' => -1
]);

echo "<h3>Páginas publicadas:</h3>\n";
$found_shortcode_pages = [];

foreach ($pages_with_shortcode as $page) {
    $content = $page->post_content;
    $has_shortcode = has_shortcode($content, 'lexhoy_despachos_search');
    
    if ($has_shortcode) {
        $found_shortcode_pages[] = $page;
        echo "<p>✅ <strong>" . esc_html($page->post_title) . "</strong> (ID: {$page->ID}) - Contiene shortcode</p>\n";
        echo "<ul><li>URL: " . get_permalink($page->ID) . "</li></ul>\n";
    }
}

if (empty($found_shortcode_pages)) {
    echo "<p>❌ No se encontraron páginas con el shortcode <code>[lexhoy_despachos_search]</code></p>\n";
    
    // Buscar en todas las páginas
    $all_pages = get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        'numberposts' => -1
    ]);
    
    echo "<h4>Revisando todas las páginas en busca del shortcode...</h4>\n";
    foreach ($all_pages as $page) {
        if (has_shortcode($page->post_content, 'lexhoy_despachos_search')) {
            echo "<p>✅ Encontrado en: <strong>" . esc_html($page->post_title) . "</strong> (ID: {$page->ID})</p>\n";
            $found_shortcode_pages[] = $page;
        }
    }
}

// 3. Verificar despachos individuales
echo "<h2>3. 🏢 Verificación de Despachos Individuales</h2>\n";

$sample_despachos = get_posts([
    'post_type' => 'despacho',
    'post_status' => 'publish',
    'numberposts' => 5
]);

if (!empty($sample_despachos)) {
    echo "<h3>Muestra de despachos (primeros 5):</h3>\n";
    foreach ($sample_despachos as $despacho) {
        $nombre = get_post_meta($despacho->ID, '_despacho_nombre', true);
        echo "<p>✅ <strong>" . esc_html($despacho->post_title) . "</strong> (ID: {$despacho->ID})</p>\n";
        echo "<ul>";
        echo "<li>Nombre meta: " . esc_html($nombre) . "</li>";
        echo "<li>URL: " . get_permalink($despacho->ID) . "</li>";
        echo "<li>Slug: " . esc_html($despacho->post_name) . "</li>";
        echo "</ul>\n";
    }
} else {
    echo "<p>❌ No se encontraron despachos publicados</p>\n";
}

// 4. Verificar configuración del tema
echo "<h2>4. 🎨 Verificación de Configuración del Tema</h2>\n";

$theme = wp_get_theme();
echo "<p><strong>Tema activo:</strong> " . esc_html($theme->get('Name')) . " v" . esc_html($theme->get('Version')) . "</p>\n";

// Verificar si el tema tiene soporte para títulos
if (current_theme_supports('title-tag')) {
    echo "<p>✅ El tema soporta <code>title-tag</code></p>\n";
} else {
    echo "<p>❌ El tema NO soporta <code>title-tag</code> - esto puede causar problemas</p>\n";
}

// 5. Verificar plugins de SEO
echo "<h2>5. 🔍 Verificación de Plugins de SEO</h2>\n";

$seo_plugins = [
    'wordpress-seo/wp-seo.php' => 'Yoast SEO',
    'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
    'seo-by-rank-math/rank-math.php' => 'Rank Math',
    'wp-seopress/seopress.php' => 'SEOPress'
];

$active_seo_plugins = [];

foreach ($seo_plugins as $plugin_file => $plugin_name) {
    if (is_plugin_active($plugin_file)) {
        $active_seo_plugins[] = $plugin_name;
        echo "<p>⚠️ Plugin SEO activo: <strong>$plugin_name</strong></p>\n";
    }
}

if (empty($active_seo_plugins)) {
    echo "<p>✅ No se detectaron plugins de SEO que puedan interferir</p>\n";
} else {
    echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7;'>\n";
    echo "<p><strong>⚠️ IMPORTANTE:</strong> Los plugins de SEO pueden sobrescribir los títulos configurados por el plugin.</p>\n";
    echo "</div>\n";
}

// 6. Test de funciones de título
echo "<h2>6. 🧪 Test de Funciones de Título</h2>\n";

// Instanciar la clase del shortcode
$shortcode_instance = new LexhoyDespachosShortcode();

if (!empty($found_shortcode_pages)) {
    $test_page = $found_shortcode_pages[0];
    global $post;
    $post = $test_page;
    setup_postdata($post);
    
    echo "<h3>Probando con página: " . esc_html($test_page->post_title) . "</h3>\n";
    
    // Test is_search_page()
    $is_search = method_exists($shortcode_instance, 'is_search_page') ? $shortcode_instance->is_search_page() : 'Método no encontrado';
    echo "<p><strong>is_search_page():</strong> " . ($is_search ? '✅ true' : '❌ false') . "</p>\n";
    
    // Test modify_search_page_title()
    if (method_exists($shortcode_instance, 'modify_search_page_title')) {
        $test_title = ['title' => 'Título Original'];
        $modified_title = $shortcode_instance->modify_search_page_title($test_title);
        echo "<p><strong>modify_search_page_title():</strong> " . esc_html($modified_title['title']) . "</p>\n";
    }
    
    wp_reset_postdata();
}

// 7. Verificar orden de carga de hooks
echo "<h2>7. ⚡ Orden de Carga de Hooks</h2>\n";

echo "<p><strong>Hook document_title_parts (prioridad 10):</strong></p>\n";
if (isset($wp_filter['document_title_parts'])) {
    ksort($wp_filter['document_title_parts']->callbacks);
    foreach ($wp_filter['document_title_parts']->callbacks as $priority => $callbacks) {
        echo "<ul><li>Prioridad $priority: " . count($callbacks) . " callback(s)</li></ul>\n";
    }
}

// 8. Recomendaciones
echo "<h2>8. 💡 Recomendaciones</h2>\n";

echo "<div style='background: #e7f3ff; padding: 15px; border: 1px solid #b3d9ff;'>\n";
echo "<h3>🔧 Pasos para solucionar:</h3>\n";
echo "<ol>\n";

if (empty($found_shortcode_pages)) {
    echo "<li><strong>Crear una página con el shortcode:</strong> Asegúrate de tener una página que contenga <code>[lexhoy_despachos_search]</code></li>\n";
}

if (!empty($active_seo_plugins)) {
    echo "<li><strong>Configurar plugin SEO:</strong> En " . implode(', ', $active_seo_plugins) . ", desactiva la gestión automática de títulos para las páginas del buscador</li>\n";
}

if (!current_theme_supports('title-tag')) {
    echo "<li><strong>Actualizar tema:</strong> Agrega <code>add_theme_support('title-tag');</code> al functions.php del tema</li>\n";
}

echo "<li><strong>Verificar prioridad de hooks:</strong> Los hooks del plugin deberían ejecutarse después de los del tema/SEO (prioridad > 10)</li>\n";
echo "<li><strong>Usar herramientas de desarrollo:</strong> Inspecciona el HTML para ver qué título se está generando realmente</li>\n";
echo "</ol>\n";
echo "</div>\n";

?> 
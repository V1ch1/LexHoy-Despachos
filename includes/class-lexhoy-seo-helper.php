<?php
/**
 * Clase helper para SEO y Rank Math de los despachos
 */

if (!defined('ABSPATH')) {
    exit;
}

class LexhoyDespachosSeOHelper {
    
    public function __construct() {
        // Hooks para SEO y Rank Math
        add_filter('rank_math/sitemap/urlimages', array($this, 'add_despacho_images_to_sitemap'), 10, 3);
        add_filter('rank_math/json_ld', array($this, 'add_despacho_schema'), 10, 2);
        add_action('wp_head', array($this, 'add_despacho_meta_tags'));
        add_filter('rank_math/frontend/canonical', array($this, 'modify_canonical_url'), 10, 1);
        
        // Hook para generar sitemap XML de despachos
        add_action('init', array($this, 'add_despacho_sitemap_rewrite'));
        add_action('template_redirect', array($this, 'handle_despacho_sitemap'));
    }

    /**
     * Agregar imágenes de despachos al sitemap de Rank Math
     */
    public function add_despacho_images_to_sitemap($images, $post, $post_type) {
        if ('despacho' !== $post_type) {
            return $images;
        }

        // Agregar imagen destacada si existe
        if (has_post_thumbnail($post->ID)) {
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            $image_url = wp_get_attachment_image_url($thumbnail_id, 'full');
            if ($image_url) {
                $images[] = array(
                    'src' => $image_url,
                    'title' => get_the_title($post->ID),
                    'alt' => get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true),
                );
            }
        }

        return $images;
    }

    /**
     * Agregar datos estructurados (Schema.org) para despachos
     */
    public function add_despacho_schema($schema, $post) {
        if (!is_singular('despacho') || !$post) {
            return $schema;
        }

        $nombre = get_post_meta($post->ID, '_despacho_nombre', true);
        $direccion = get_post_meta($post->ID, '_despacho_direccion', true);
        $localidad = get_post_meta($post->ID, '_despacho_localidad', true);
        $provincia = get_post_meta($post->ID, '_despacho_provincia', true);
        $codigo_postal = get_post_meta($post->ID, '_despacho_codigo_postal', true);
        $telefono = get_post_meta($post->ID, '_despacho_telefono', true);
        $email = get_post_meta($post->ID, '_despacho_email', true);
        $web = get_post_meta($post->ID, '_despacho_web', true);
        $descripcion = get_post_meta($post->ID, '_despacho_descripcion', true);
        $especialidades = get_post_meta($post->ID, '_despacho_especialidades', true);

        // Schema.org para despacho de abogados
        $law_firm_schema = array(
            '@type' => 'LegalService',
            '@id' => get_permalink($post->ID) . '#lawfirm',
            'name' => $nombre ?: get_the_title($post->ID),
            'url' => get_permalink($post->ID),
            'description' => $descripcion ?: get_the_excerpt($post->ID),
            'serviceType' => 'Legal Services',
        );

        // Agregar dirección si está disponible
        if ($direccion || $localidad || $provincia) {
            $law_firm_schema['address'] = array(
                '@type' => 'PostalAddress',
                'streetAddress' => $direccion ?: '',
                'addressLocality' => $localidad ?: '',
                'addressRegion' => $provincia ?: '',
                'postalCode' => $codigo_postal ?: '',
                'addressCountry' => 'ES'
            );
        }

        // Agregar información de contacto
        if ($telefono) {
            $law_firm_schema['telephone'] = $telefono;
        }
        if ($email) {
            $law_firm_schema['email'] = $email;
        }
        if ($web) {
            $law_firm_schema['sameAs'] = array($web);
        }

        // Agregar especialidades como servicios ofrecidos
        if ($especialidades) {
            $especialidades_array = array_map('trim', explode(',', $especialidades));
            $law_firm_schema['serviceOffered'] = array();
            foreach ($especialidades_array as $especialidad) {
                $law_firm_schema['serviceOffered'][] = array(
                    '@type' => 'Service',
                    'name' => $especialidad,
                    'serviceType' => 'Legal Service'
                );
            }
        }

        // Agregar el schema al array
        $schema['LegalService'] = $law_firm_schema;

        return $schema;
    }

    /**
     * Agregar meta tags específicos para despachos
     */
    public function add_despacho_meta_tags() {
        if (!is_singular('despacho')) {
            return;
        }

        global $post;
        $nombre = get_post_meta($post->ID, '_despacho_nombre', true);
        $localidad = get_post_meta($post->ID, '_despacho_localidad', true);
        $provincia = get_post_meta($post->ID, '_despacho_provincia', true);
        $descripcion = get_post_meta($post->ID, '_despacho_descripcion', true);
        $especialidades = get_post_meta($post->ID, '_despacho_especialidades', true);

        // Meta description personalizada
        $meta_description = '';
        if ($descripcion) {
            $meta_description = wp_trim_words(strip_tags($descripcion), 25, '...');
        } else {
            $meta_description = "Despacho de abogados " . ($nombre ?: get_the_title());
            if ($localidad && $provincia) {
                $meta_description .= " en " . $localidad . ", " . $provincia;
            }
            if ($especialidades) {
                $meta_description .= ". Especialistas en " . $especialidades;
            }
        }

        // Keywords
        $keywords = array('despacho abogados', 'bufete');
        if ($localidad) $keywords[] = $localidad;
        if ($provincia) $keywords[] = $provincia;
        if ($especialidades) {
            $esp_array = array_map('trim', explode(',', $especialidades));
            $keywords = array_merge($keywords, $esp_array);
        }

        echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
        echo '<meta name="keywords" content="' . esc_attr(implode(', ', $keywords)) . '">' . "\n";
        echo '<meta property="og:type" content="business.business">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($nombre ?: get_the_title()) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($meta_description) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '">' . "\n";
        
        if (has_post_thumbnail()) {
            $image_url = get_the_post_thumbnail_url($post->ID, 'large');
            echo '<meta property="og:image" content="' . esc_url($image_url) . '">' . "\n";
        }
    }

    /**
     * Modificar URL canónica para despachos
     */
    public function modify_canonical_url($canonical) {
        if (is_singular('despacho')) {
            // Usar nuestras URLs limpias como canónicas
            global $post;
            return home_url('/' . $post->post_name . '/');
        }
        return $canonical;
    }

    /**
     * Agregar regla de reescritura para sitemap de despachos
     */
    public function add_despacho_sitemap_rewrite() {
        add_rewrite_rule('^despachos-sitemap\.xml$', 'index.php?despachos_sitemap=1', 'top');
        add_rewrite_tag('%despachos_sitemap%', '([^&]+)');
    }

    /**
     * Manejar la generación del sitemap de despachos
     */
    public function handle_despacho_sitemap() {
        if (get_query_var('despachos_sitemap')) {
            $this->generate_despachos_sitemap();
            exit;
        }
    }

    /**
     * Generar sitemap XML para despachos
     */
    private function generate_despachos_sitemap() {
        header('Content-Type: application/xml; charset=utf-8');
        
        $despachos = get_posts(array(
            'post_type' => 'despacho',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'modified',
            'order' => 'DESC'
        ));

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($despachos as $despacho) {
            $url = home_url('/' . $despacho->post_name . '/');
            $modified = get_the_modified_date('Y-m-d\TH:i:s+00:00', $despacho->ID);
            
            echo '  <url>' . "\n";
            echo '    <loc>' . esc_url($url) . '</loc>' . "\n";
            echo '    <lastmod>' . esc_html($modified) . '</lastmod>' . "\n";
            echo '    <changefreq>monthly</changefreq>' . "\n";
            echo '    <priority>0.8</priority>' . "\n";
            echo '  </url>' . "\n";
        }

        echo '</urlset>' . "\n";
    }

    /**
     * Obtener todas las URLs de despachos para indexación
     */
    public function get_all_despacho_urls() {
        $despachos = get_posts(array(
            'post_type' => 'despacho',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids'
        ));

        $urls = array();
        foreach ($despachos as $despacho_id) {
            $post = get_post($despacho_id);
            $urls[] = home_url('/' . $post->post_name . '/');
        }

        return $urls;
    }

    /**
     * Generar archivo robots.txt específico para despachos
     */
    public function add_despachos_to_robots_txt($output) {
        $output .= "\n# Sitemap de Despachos\n";
        $output .= "Sitemap: " . home_url('/despachos-sitemap.xml') . "\n";
        return $output;
    }
} 
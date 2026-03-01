<?php
/**
 * Clase para el Interlinking entre Blog y Directorio de Despachos
 */

if (!defined('ABSPATH')) {
    exit;
}

class LexhoyInterlinking {
    
    public function __construct() {
        add_shortcode('lexhoy_abogados_relacionados', array($this, 'render_related_lawyers'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    /**
     * Encolar estilos necesarios para el widget
     */
    public function enqueue_styles() {
        if (is_singular('post')) {
            wp_enqueue_style('lexhoy-silos-premium', LEXHOY_DESPACHOS_PLUGIN_URL . 'assets/css/silos.css', array(), LEXHOY_DESPACHOS_VERSION);
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
        }
    }

    /**
     * Renderiza el widget de abogados relacionados basado en la categoría del post actual
     */
    public function render_related_lawyers($atts) {
        $atts = shortcode_atts(array(
            'limit' => 3,
            'title' => 'Abogados Especialistas Recomendados'
        ), $atts);

        // Si no estamos en un post individual, no mostrar nada
        if (!is_singular('post')) {
            return '';
        }

        // Obtener categorías del post para buscar especialidad equivalente
        $categories = get_the_category();
        if (empty($categories)) {
            return '';
        }

        $category_names = wp_list_pluck($categories, 'name');
        
        // Buscar despachos que tengan estas especialidades (taxonomía area_practica)
        $args = array(
            'post_type' => 'despacho',
            'posts_per_page' => $atts['limit'],
            'tax_query' => array(
                array(
                    'taxonomy' => 'area_practica',
                    'field'    => 'name',
                    'terms'    => $category_names,
                    'operator' => 'IN',
                ),
            ),
            'orderby' => 'rand' // Sugerencia aleatoria para rotar abogados
        );

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            return '';
        }

        ob_start();
        ?>
        <div class="lexhoy-related-lawyers-widget-premium">
            <div class="widget-header">
                <h3><i class="fas fa-balance-scale"></i> <?php echo esc_html($atts['title']); ?></h3>
                <p>Especialistas verificados recomendados para tu consulta</p>
            </div>
            
            <div class="related-lawyers-grid">
                <?php while ($query->have_posts()) : $query->the_post(); 
                    $post_id = get_the_ID();
                    $sedes = get_post_meta($post_id, '_despacho_sedes', true);
                    $sede_principal = (!empty($sedes) && is_array($sedes)) ? $sedes[0] : null;
                    $foto = $sede_principal['foto_perfil'] ?? get_post_meta($post_id, '_despacho_foto_perfil', true);
                    $localidad = $sede_principal['localidad'] ?? get_post_meta($post_id, '_despacho_localidad', true);
                    $is_verified = ($sede_principal['estado_verificacion'] ?? get_post_meta($post_id, '_despacho_estado_verificacion', true)) === 'verificado';
                ?>
                    <div class="lawyer-card-mini">
                        <div class="lawyer-avatar-wrapper">
                            <?php if ($foto): ?>
                                <img src="<?php echo esc_url($foto); ?>" class="lawyer-avatar">
                            <?php else: ?>
                                <div class="lawyer-avatar-placeholder"><i class="fas fa-user-tie"></i></div>
                            <?php endif; ?>
                            <?php if ($is_verified): ?>
                                <i class="fas fa-check-circle verified-badge-mini"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="lawyer-info-mini">
                            <h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
                            <span class="lawyer-loc-mini"><i class="fas fa-map-marker-alt"></i> <?php echo esc_html($localidad); ?></span>
                        </div>
                        
                        <div class="lawyer-action-mini">
                            <a href="<?php the_permalink(); ?>" class="btn-mini">Ver Perfil</a>
                        </div>
                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
            
            <div class="widget-footer">
                <p>¿Buscas más expertos? <a href="<?php echo home_url('/despacho/'); ?>">Explora el buscador completo &rarr;</a></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

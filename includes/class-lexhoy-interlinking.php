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
        <div class="lexhoy-related-lawyers-widget" style="margin: 30px 0; padding: 25px; background: #f8faff; border: 1px solid #e0e6ed; border-radius: 12px; font-family: 'Inter', sans-serif;">
            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 1.5rem; color: #333; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.8rem;">⚖️</span> <?php echo esc_html($atts['title']); ?>
            </h3>
            
            <div class="related-lawyers-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <?php while ($query->have_posts()) : $query->the_post(); 
                    $post_id = get_the_ID();
                    $sedes = get_post_meta($post_id, '_despacho_sedes', true);
                    $sede_principal = (!empty($sedes) && is_array($sedes)) ? $sedes[0] : null;
                    $foto = $sede_principal['foto_perfil'] ?? get_post_meta($post_id, '_despacho_foto_perfil', true);
                    $localidad = $sede_principal['localidad'] ?? get_post_meta($post_id, '_despacho_localidad', true);
                ?>
                    <div class="lawyer-item" style="background: #fff; border: 1px solid #eee; border-radius: 10px; padding: 15px; text-align: center; transition: all 0.3s ease;">
                        <?php if ($foto): ?>
                            <img src="<?php echo esc_url($foto); ?>" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover; margin-bottom: 10px; border: 2px solid #0073aa;">
                        <?php else: ?>
                            <div style="width: 70px; height: 70px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 1.5rem;">👤</div>
                        <?php endif; ?>
                        
                        <h4 style="margin: 10px 0 5px; font-size: 1.1rem; line-height: 1.2;">
                            <a href="<?php the_permalink(); ?>" style="text-decoration: none; color: #0073aa;"><?php the_title(); ?></a>
                        </h4>
                        <p style="margin: 0; font-size: 0.85rem; color: #666;">📍 <?php echo esc_html($localidad); ?></p>
                        
                        <div style="margin-top: 15px;">
                            <a href="<?php the_permalink(); ?>" style="display: inline-block; padding: 6px 14px; background: #0073aa; color: #fff; border-radius: 20px; text-decoration: none; font-size: 0.85rem; font-weight: 500;">Ver Perfil</a>
                        </div>
                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
            
            <div style="margin-top: 25px; text-align: center; border-top: 1px solid #e0e6ed; padding-top: 15px;">
                <p style="margin: 0; font-size: 0.9rem; color: #555;">
                    ¿Buscas expertos en otra provincia? <a href="<?php echo home_url('/despacho/'); ?>" style="color: #0073aa; font-weight: 600;">Explora el buscador completo &rarr;</a>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

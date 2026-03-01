<?php
/**
 * Plantilla para archivos de taxonomía (Silos)
 * Muestra el listado de despachos por provincia o especialidad con diseño PREMIUM
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$queried_object = get_queried_object();
$taxonomy = $queried_object->taxonomy;
$term_name = $queried_object->name;

// Encolar estilos premium
wp_enqueue_style('lexhoy-silos-premium', LEXHOY_DESPACHOS_PLUGIN_URL . 'assets/css/silos.css', array(), LEXHOY_DESPACHOS_VERSION);
wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
?>

<div class="lexhoy-directory-archive">
    <header class="archive-header">
        <div class="container">
            <h1 class="archive-title">
                <?php 
                if ($taxonomy === 'provincia') {
                    echo "Abogados en " . esc_html($term_name);
                } else {
                    echo "Especialistas en " . esc_html($term_name);
                }
                ?>
            </h1>
            <p class="archive-description">
                Directorio verificado de los mejores despachos de abogados y profesionales jurídicos en <?php echo esc_html($term_name); ?>. 
                Excelencia legal a tu alcance.
            </p>
        </div>
    </header>

    <div class="container">
        <div class="lexhoy-archive-content">
            <?php if (have_posts()) : ?>
                <div class="despachos-grid">
                    <?php while (have_posts()) : the_post(); 
                        $post_id = get_the_ID();
                        $nombre = get_the_title();
                        $sedes = get_post_meta($post_id, '_despacho_sedes', true);
                        $sede_principal = (!empty($sedes) && is_array($sedes)) ? $sedes[0] : null;
                        
                        $localidad = $sede_principal['localidad'] ?? get_post_meta($post_id, '_despacho_localidad', true);
                        $provincia = $sede_principal['provincia'] ?? get_post_meta($post_id, '_despacho_provincia', true);
                        $foto_perfil = $sede_principal['foto_perfil'] ?? get_post_meta($post_id, '_despacho_foto_perfil', true);
                        $is_verified = ($sede_principal['estado_verificacion'] ?? get_post_meta($post_id, '_despacho_estado_verificacion', true)) === 'verificado';
                    ?>
                        <div class="despacho-card">
                            <div class="card-header">
                                <div class="card-photo-wrapper">
                                    <?php if ($foto_perfil): ?>
                                        <img src="<?php echo esc_url($foto_perfil); ?>" class="card-photo" alt="<?php echo esc_attr($nombre); ?>">
                                    <?php else: ?>
                                        <div class="card-photo" style="display: flex; align-items: center; justify-content: center; font-size: 24px; background: #f7fafc;">🏢</div>
                                    <?php endif; ?>
                                    
                                    <?php if ($is_verified): ?>
                                        <div class="verified-icon" title="Perfil Verificado">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-title-area">
                                    <h3>
                                        <a href="<?php the_permalink(); ?>"><?php echo esc_html($nombre); ?></a>
                                    </h3>
                                    <div class="card-location">
                                        <i class="fas fa-location-dot"></i>
                                        <?php echo esc_html($localidad); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <!-- Podríamos añadir un extracto aquí más adelante -->
                            </div>
                            
                            <div class="card-footer">
                                <a href="<?php the_permalink(); ?>" class="btn-profile">Ver Perfil Completo</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="pagination">
                    <?php 
                    echo paginate_links(array(
                        'format' => '?paged=%#%',
                        'current' => max(1, get_query_var('paged')),
                        'total' => $wp_query->max_num_pages,
                        'prev_text' => '&laquo; Anterior',
                        'next_text' => 'Siguiente &raquo;',
                        'type' => 'plain'
                    ));
                    ?>
                </div>

            <?php else : ?>
                <div class="no-results" style="padding: 100px 20px; text-align: center; background: #f8faff; border-radius: 20px; border: 2px dashed #e2e8f0;">
                    <i class="fas fa-search" style="font-size: 48px; color: #cbd5e0; margin-bottom: 20px; display: block;"></i>
                    <p style="font-size: 1.4rem; color: #718096; font-weight: 600;">No hemos encontrado despachos en esta categoría por el momento.</p>
                    <p style="color: #a0aec0; margin-bottom: 30px;">Estamos expandiendo nuestra red de profesionales cada día.</p>
                    <a href="<?php echo home_url('/despacho/'); ?>" class="btn-profile">Volver al buscador</a>
                </div>
            <?php endif; ?>
        </div>
        
        <aside class="archive-footer">
            <h3>¿Eres abogado en <?php echo esc_html($term_name); ?>?</h3>
            <p style="margin-top: 15px;">Únete a LexHoy para aumentar tu visibilidad y conectar con personas que buscan tu ayuda legal. <br><br>
               <a href="<?php echo home_url('/registro-despachos/'); ?>">Registra tu despacho gratis aquí &rarr;</a>
            </p>
        </aside>
    </div>
</div>

<?php get_footer(); ?>

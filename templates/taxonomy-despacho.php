<?php
/**
 * Plantilla para archivos de taxonomía (Silos)
 * Muestra el listado de despachos por provincia o especialidad
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$queried_object = get_queried_object();
$taxonomy = $queried_object->taxonomy;
$term_name = $queried_object->name;
$term_slug = $queried_object->slug;

// Encolar estilos del buscador para mantener coherencia
wp_enqueue_style('lexhoy-search-styles', LEXHOY_DESPACHOS_PLUGIN_URL . 'assets/css/search.css', array(), LEXHOY_DESPACHOS_VERSION);
wp_enqueue_style('lexhoy-single-styles', LEXHOY_DESPACHOS_PLUGIN_URL . 'assets/css/single-despacho.css', array(), LEXHOY_DESPACHOS_VERSION);
?>

<div class="lexhoy-directory-archive">
    <div class="container">
        <header class="archive-header" style="padding: 40px 0; border-bottom: 1px solid #eee; margin-bottom: 30px;">
            <h1 class="archive-title" style="font-size: 2.5rem; color: #1a1a1a;">
                <?php 
                if ($taxonomy === 'provincia') {
                    echo "Abogados en " . esc_html($term_name);
                } else {
                    echo "Especialistas en " . esc_html($term_name);
                }
                ?>
            </h1>
            <p class="archive-description" style="font-size: 1.1rem; color: #666; margin-top: 10px;">
                Encuentra los mejores despachos de abogados y profesionales jurídicos en <?php echo esc_html($term_name); ?>. 
                Perfiles verificados y especializados para tu caso legal.
            </p>
        </header>

        <div class="lexhoy-archive-content" style="display: grid; grid-template-columns: 1fr; gap: 24px;">
            <?php if (have_posts()) : ?>
                <div class="despachos-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;">
                    <?php while (have_posts()) : the_post(); 
                        $post_id = get_the_ID();
                        $nombre = get_the_title();
                        $sedes = get_post_meta($post_id, '_despacho_sedes', true);
                        $sede_principal = (!empty($sedes) && is_array($sedes)) ? $sedes[0] : null;
                        
                        $localidad = $sede_principal['localidad'] ?? get_post_meta($post_id, '_despacho_localidad', true);
                        $provincia = $sede_principal['provincia'] ?? get_post_meta($post_id, '_despacho_provincia', true);
                        $foto_perfil = $sede_principal['foto_perfil'] ?? get_post_meta($post_id, '_despacho_foto_perfil', true);
                        $is_verified = $sede_principal['is_verified'] ?? get_post_meta($post_id, '_despacho_is_verified', true);
                    ?>
                        <div class="despacho-card" style="border: 1px solid #eee; border-radius: 12px; padding: 20px; transition: transform 0.2s; background: #fff;">
                            <div class="card-header" style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                                <?php if ($foto_perfil): ?>
                                    <img src="<?php echo esc_url($foto_perfil); ?>" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 60px; height: 60px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 24px;">🏢</div>
                                <?php endif; ?>
                                <div>
                                    <h3 style="margin: 0; font-size: 1.2rem;">
                                        <a href="<?php the_permalink(); ?>" style="text-decoration: none; color: #0073aa;"><?php echo esc_html($nombre); ?></a>
                                    </h3>
                                    <?php if ($is_verified): ?>
                                        <span class="verified-badge" style="color: #28a745; font-size: 0.8rem;">✅ Verificado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <p style="margin: 5px 0; color: #666; font-size: 0.9rem;">
                                    📍 <?php echo esc_html($localidad . ($provincia ? ", $provincia" : "")); ?>
                                </p>
                            </div>
                            <div class="card-footer" style="margin-top: 20px; text-align: right;">
                                <a href="<?php the_permalink(); ?>" class="button" style="background: #0073aa; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none;">Ver Perfil</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="pagination" style="margin-top: 40px; text-align: center;">
                    <?php 
                    echo paginate_links(array(
                        'format' => '?paged=%#%',
                        'current' => max(1, get_query_var('paged')),
                        'total' => $wp_query->max_num_pages,
                        'prev_text' => '&laquo; Anterior',
                        'next_text' => 'Siguiente &raquo;',
                    ));
                    ?>
                </div>

            <?php else : ?>
                <div class="no-results" style="padding: 60px; text-align: center; border: 2px dashed #eee; border-radius: 12px;">
                    <p style="font-size: 1.2rem; color: #999;">No hemos encontrado despachos en esta categoría por el momento.</p>
                    <a href="<?php echo home_url('/despacho/'); ?>" class="button">Volver al buscador</a>
                </div>
            <?php endif; ?>
        </div>
        
        <aside class="archive-footer" style="margin-top: 60px; padding: 40px; background: #f9f9f9; border-radius: 12px;">
            <p>¿Eres abogado en <?php echo esc_html($term_name); ?>? <a href="<?php echo home_url('/registro-despachos/'); ?>">Registra tu despacho gratis</a> en LexHoy y llega a miles de clientes potenciales cada mes.</p>
        </aside>
    </div>
</div>

<?php get_footer(); ?>

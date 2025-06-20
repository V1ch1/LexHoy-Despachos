<?php
/**
 * Plantilla para mostrar despachos individuales
 * 
 * Esta plantilla se usa cuando se accede a una URL limpia como:
 * lexhoy.com/nombre-de-despacho
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

get_header(); ?>

<div class="container despacho-single">
    <div class="row">
        <div class="col-md-8">
            <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
                
                <article id="post-<?php the_ID(); ?>" <?php post_class('despacho-article'); ?>>
                    
                    <!-- Encabezado del despacho -->
                    <header class="despacho-header">
                        <?php
                        // Forzar limpieza de caché para asegurar datos frescos
                        wp_cache_flush();
                        
                        // Obtener nombre desde meta (preferencia) o título del post
                        $nombre_despacho = get_post_meta(get_the_ID(), '_despacho_nombre', true);
                        if (empty($nombre_despacho)) {
                            $nombre_despacho = get_the_title();
                        }
                        ?>
                        <h1 class="despacho-title"><?php echo esc_html($nombre_despacho); ?></h1>
                        
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="despacho-image">
                                <?php the_post_thumbnail('large', array('class' => 'img-fluid')); ?>
                            </div>
                        <?php endif; ?>
                    </header>

                    <!-- Información del despacho -->
                    <div class="despacho-info">
                        <?php
                        // Obtener metadatos del despacho
                        $localidad = get_post_meta(get_the_ID(), '_despacho_localidad', true);
                        $provincia = get_post_meta(get_the_ID(), '_despacho_provincia', true);
                        $direccion = get_post_meta(get_the_ID(), '_despacho_direccion', true);
                        $codigo_postal = get_post_meta(get_the_ID(), '_despacho_codigo_postal', true);
                        $telefono = get_post_meta(get_the_ID(), '_despacho_telefono', true);
                        $email = get_post_meta(get_the_ID(), '_despacho_email', true);
                        $web = get_post_meta(get_the_ID(), '_despacho_web', true);
                        $descripcion = get_post_meta(get_the_ID(), '_despacho_descripcion', true);
                        $especialidades = get_post_meta(get_the_ID(), '_despacho_especialidades', true);
                        $especialidades_array = array_filter(array_map('trim', explode(',', $especialidades)));
                        $horario = get_post_meta(get_the_ID(), '_despacho_horario', true);
                        $experiencia = get_post_meta(get_the_ID(), '_despacho_experiencia', true);
                        $tamaño_despacho = get_post_meta(get_the_ID(), '_despacho_tamaño', true);
                        $año_fundacion = get_post_meta(get_the_ID(), '_despacho_año_fundacion', true);
                        $is_verified = get_post_meta(get_the_ID(), '_despacho_is_verified', true) === '1';
                        $redes_sociales = get_post_meta(get_the_ID(), '_despacho_redes_sociales', true);
                        // Obtener áreas de práctica (taxonomía)
                        $areas_practica = wp_get_post_terms(get_the_ID(), 'area_practica');
                        ?>
                        
                        <!-- Información de contacto -->
                        <div class="despacho-contact">
                            <h3>Información de Contacto</h3>
                            <div class="contact-details definition-grid">
                                <?php if ($localidad && $provincia) : ?>
                                    <span class="def-label">Ubicación:</span><span class="def-value"><?php echo esc_html($localidad . ', ' . $provincia); ?></span>
                                <?php endif; ?>
                                <?php if ($direccion) : ?>
                                    <span class="def-label">Dirección:</span><span class="def-value"><?php echo esc_html($direccion); ?></span>
                                <?php endif; ?>
                                <?php if ($codigo_postal) : ?>
                                    <span class="def-label">Código Postal:</span><span class="def-value"><?php echo esc_html($codigo_postal); ?></span>
                                <?php endif; ?>
                                <?php if ($telefono) : ?>
                                    <span class="def-label">Teléfono:</span><span class="def-value"><a href="tel:<?php echo esc_attr($telefono); ?>"><?php echo esc_html($telefono); ?></a></span>
                                <?php endif; ?>
                                <?php if ($email) : ?>
                                    <span class="def-label">Email:</span><span class="def-value"><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></span>
                                <?php endif; ?>
                                <?php if ($web) : ?>
                                    <span class="def-label">Sitio Web:</span><span class="def-value"><a href="<?php echo esc_url($web); ?>" target="_blank" rel="noopener"><?php echo esc_html($web); ?></a></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Descripción -->
                        <?php if ($descripcion) : ?>
                            <div class="despacho-description">
                                <h3>Descripción</h3>
                                <p><?php echo esc_html($descripcion); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Áreas de práctica -->
                        <?php if ($areas_practica) : ?>
                            <div class="despacho-areas">
                                <h3>Áreas de Práctica</h3>
                                <ul class="two-col-grid">
                                    <?php foreach ($areas_practica as $area) : ?>
                                        <li><a href="<?php echo get_term_link($area); ?>"><?php echo esc_html($area->name); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Especialidades -->
                        <?php if (!empty($especialidades_array)) : ?>
                            <div class="despacho-specialties">
                                <h3>Especialidades</h3>
                                <ul class="two-col-grid">
                                    <?php foreach ($especialidades_array as $esp) : ?>
                                        <li><?php echo esc_html($esp); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Horario -->
                        <?php if ($horario && is_array($horario)) : ?>
                            <div class="despacho-horario">
                                <h3>Horario de Atención</h3>
                                <div class="definition-grid">
                                    <?php foreach ($horario as $dia => $valor) : ?>
                                        <?php if ($valor) : ?>
                                            <span class="def-label"><?php echo ucfirst($dia); ?>:</span><span class="def-value"><?php echo esc_html($valor); ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Redes sociales -->
                        <?php if ($redes_sociales && is_array($redes_sociales)) : ?>
                            <div class="despacho-redes">
                                <h3>Redes Sociales</h3>
                                <div class="definition-grid">
                                    <?php foreach ($redes_sociales as $red => $url) : ?>
                                        <?php if ($url) : ?>
                                            <span class="def-label"><?php echo ucfirst($red); ?>:</span><span class="def-value"><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-<?php echo esc_attr($red); ?>"></span> <?php echo ucfirst($red); ?></a></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Información adicional -->
                        <div class="despacho-details">
                            <h3>Información Adicional</h3>
                            <div class="definition-grid">
                                <?php if ($experiencia) : ?>
                                    <span class="def-label">Experiencia:</span><span class="def-value"><?php echo esc_html($experiencia); ?></span>
                                <?php endif; ?>
                                
                                <?php if ($tamaño_despacho) : ?>
                                    <span class="def-label">Tamaño del Despacho:</span><span class="def-value"><?php echo esc_html($tamaño_despacho); ?></span>
                                <?php endif; ?>
                                
                                <?php if ($año_fundacion) : ?>
                                    <span class="def-label">Año de Fundación:</span><span class="def-value"><?php echo esc_html($año_fundacion); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Estado de verificación -->
                        <?php if ($is_verified) : ?>
                            <div class="despacho-verification">
                                <div class="verification-badge">
                                    <span class="badge badge-success">✓ Despacho Verificado</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Contenido del post -->
                    <div class="despacho-content">
                        <?php the_content(); ?>
                    </div>

                </article>

            <?php endwhile; endif; ?>
        </div>
        
        <div class="col-md-4">
            <!-- Sidebar o información adicional -->
            <div class="despacho-sidebar">
                <h3>Otros Despachos</h3>
                <?php
                $otros_despachos = get_posts(array(
                    'post_type' => 'despacho',
                    'posts_per_page' => 5,
                    'post__not_in' => array(get_the_ID()),
                    'post_status' => 'publish'
                ));
                
                if ($otros_despachos) : ?>
                    <ul class="otros-despachos">
                        <?php foreach ($otros_despachos as $despacho) : ?>
                            <li>
                                <a href="<?php echo get_permalink($despacho->ID); ?>">
                                    <?php echo esc_html($despacho->post_title); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p>No hay otros despachos disponibles.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.despacho-single {
    padding: 2rem 0;
}

.despacho-header {
    margin-bottom: 2rem;
}

.despacho-title {
    color: #333;
    margin-bottom: 1rem;
}

.despacho-image {
    margin-bottom: 1rem;
}

.despacho-info {
    background: #f8f9fa;
    padding: 2rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.despacho-contact h3,
.despacho-description h3,
.despacho-specialties h3,
.despacho-details h3 {
    color: #007cba;
    border-bottom: 2px solid #007cba;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

.contact-details p {
    margin-bottom: 0.5rem;
}

.contact-details a {
    color: #007cba;
    text-decoration: none;
}

.contact-details a:hover {
    text-decoration: underline;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.detail-item {
    background: white;
    padding: 1rem;
    border-radius: 4px;
    border-left: 4px solid #007cba;
}

.verification-badge {
    margin-top: 1rem;
}

.badge-success {
    background-color: #28a745;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
}

.despacho-sidebar {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
}

.otros-despachos {
    list-style: none;
    padding: 0;
}

.otros-despachos li {
    margin-bottom: 0.5rem;
    padding: 0.5rem;
    background: white;
    border-radius: 4px;
}

.otros-despachos a {
    color: #007cba;
    text-decoration: none;
}

.otros-despachos a:hover {
    text-decoration: underline;
}

@media (max-width: 768px) {
    .details-grid {
        grid-template-columns: 1fr;
    }
}

.despacho-info ul.two-col-grid {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem 1rem;
}

.despacho-info ul.two-col-grid li {
    margin: 0;
}

@media (max-width: 768px) {
    .despacho-info ul.two-col-grid {
        grid-template-columns: 1fr;
    }
}

/* Nueva definición grid label/valor */
.definition-grid {
    display: grid;
    grid-template-columns: 160px 1fr;
    row-gap: 0.4rem;
    column-gap: 1rem;
    margin: 0;
}

.definition-grid .def-label {
    font-weight: 600;
}

.definition-grid .def-value a {
    color: #007cba;
    text-decoration: none;
}

.definition-grid .def-value a:hover {
    text-decoration: underline;
}
</style>

<?php get_footer(); ?> 
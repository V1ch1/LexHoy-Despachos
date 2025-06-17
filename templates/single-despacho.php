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
                        <h1 class="despacho-title"><?php the_title(); ?></h1>
                        
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
                        $horario = get_post_meta(get_the_ID(), '_despacho_horario', true);
                        $experiencia = get_post_meta(get_the_ID(), '_despacho_experiencia', true);
                        $tamaño_despacho = get_post_meta(get_the_ID(), '_despacho_tamaño', true);
                        $año_fundacion = get_post_meta(get_the_ID(), '_despacho_año_fundacion', true);
                        $is_verified = get_post_meta(get_the_ID(), '_despacho_is_verified', true);
                        ?>
                        
                        <!-- Información de contacto -->
                        <div class="despacho-contact">
                            <h3>Información de Contacto</h3>
                            <div class="contact-details">
                                <?php if ($localidad && $provincia) : ?>
                                    <p><strong>Ubicación:</strong> <?php echo esc_html($localidad . ', ' . $provincia); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($direccion) : ?>
                                    <p><strong>Dirección:</strong> <?php echo esc_html($direccion); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($codigo_postal) : ?>
                                    <p><strong>Código Postal:</strong> <?php echo esc_html($codigo_postal); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($telefono) : ?>
                                    <p><strong>Teléfono:</strong> <a href="tel:<?php echo esc_attr($telefono); ?>"><?php echo esc_html($telefono); ?></a></p>
                                <?php endif; ?>
                                
                                <?php if ($email) : ?>
                                    <p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></p>
                                <?php endif; ?>
                                
                                <?php if ($web) : ?>
                                    <p><strong>Sitio Web:</strong> <a href="<?php echo esc_url($web); ?>" target="_blank" rel="noopener"><?php echo esc_html($web); ?></a></p>
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

                        <!-- Especialidades -->
                        <?php if ($especialidades) : ?>
                            <div class="despacho-specialties">
                                <h3>Especialidades</h3>
                                <p><?php echo esc_html($especialidades); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Información adicional -->
                        <div class="despacho-details">
                            <h3>Información Adicional</h3>
                            <div class="details-grid">
                                <?php if ($horario) : ?>
                                    <div class="detail-item">
                                        <strong>Horario:</strong> <?php echo esc_html($horario); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($experiencia) : ?>
                                    <div class="detail-item">
                                        <strong>Experiencia:</strong> <?php echo esc_html($experiencia); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($tamaño_despacho) : ?>
                                    <div class="detail-item">
                                        <strong>Tamaño del Despacho:</strong> <?php echo esc_html($tamaño_despacho); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($año_fundacion) : ?>
                                    <div class="detail-item">
                                        <strong>Año de Fundación:</strong> <?php echo esc_html($año_fundacion); ?>
                                    </div>
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
</style>

<?php get_footer(); ?> 
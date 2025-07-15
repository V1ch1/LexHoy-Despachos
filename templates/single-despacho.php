<?php
/**
 * Plantilla personalizada para mostrar despachos individuales
 * Diseño IDÉNTICO al buscador de despachos
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Función helper para obtener la URL de la página del buscador
 */
function get_search_page_url() {
    // Usar caché para evitar consultas repetidas
    $cache_key = 'lexhoy_search_page_url';
    $search_url = wp_cache_get($cache_key);
    
    if (false !== $search_url) {
        return $search_url;
    }
    
    // Buscar páginas que contengan el shortcode del buscador
    $pages = get_posts(array(
        'post_type' => 'page',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids' // Solo obtener IDs para optimizar
    ));
    
    foreach ($pages as $page_id) {
        $page = get_post($page_id);
        if ($page && strpos($page->post_content, '[lexhoy_despachos_search]') !== false) {
            $search_url = get_permalink($page_id);
            wp_cache_set($cache_key, $search_url, '', 3600); // Cache por 1 hora
            return $search_url;
        }
    }
    
    // Si no se encuentra, buscar en posts también
    $posts = get_posts(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids' // Solo obtener IDs para optimizar
    ));
    
    foreach ($posts as $post_id) {
        $post = get_post($post_id);
        if ($post && strpos($post->post_content, '[lexhoy_despachos_search]') !== false) {
            $search_url = get_permalink($post_id);
            wp_cache_set($cache_key, $search_url, '', 3600); // Cache por 1 hora
            return $search_url;
        }
    }
    
    // Si no se encuentra ninguna página con el shortcode, devolver la home
    $search_url = home_url('/');
    wp_cache_set($cache_key, $search_url, '', 3600); // Cache por 1 hora
    return $search_url;
}

// Cargar estilos personalizados
wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
wp_enqueue_style('lexhoy-despacho-single', LEXHOY_DESPACHOS_PLUGIN_URL . 'assets/css/single-despacho.css', array(), LEXHOY_DESPACHOS_VERSION);

get_header(); ?>

<div class="lexhoy-despacho-single">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        
        <?php
        // Obtener todos los metadatos del despacho
        $post_id = get_the_ID();
        
        // Información básica
        $nombre = get_post_meta($post_id, '_despacho_nombre', true);
        $localidad = get_post_meta($post_id, '_despacho_localidad', true);
        $provincia = get_post_meta($post_id, '_despacho_provincia', true);
        $codigo_postal = get_post_meta($post_id, '_despacho_codigo_postal', true);
        $direccion = get_post_meta($post_id, '_despacho_direccion', true);
        $telefono = get_post_meta($post_id, '_despacho_telefono', true);
        $email = get_post_meta($post_id, '_despacho_email', true);
        $web = get_post_meta($post_id, '_despacho_web', true);
        $descripcion = get_post_meta($post_id, '_despacho_descripcion', true);
        $estado_verificacion = get_post_meta($post_id, '_despacho_estado_verificacion', true);
        $is_verified = get_post_meta($post_id, '_despacho_is_verified', true);
        
        // Información adicional
        $especialidades = get_post_meta($post_id, '_despacho_especialidades', true);
        $horario = get_post_meta($post_id, '_despacho_horario', true);
        $redes_sociales = get_post_meta($post_id, '_despacho_redes_sociales', true);
        $experiencia = get_post_meta($post_id, '_despacho_experiencia', true);
        $tamano_despacho = get_post_meta($post_id, '_despacho_tamaño', true);
        $ano_fundacion = get_post_meta($post_id, '_despacho_año_fundacion', true);
        $estado_registro = get_post_meta($post_id, '_despacho_estado_registro', true);
        $foto_perfil = get_post_meta($post_id, '_despacho_foto_perfil', true);
        
        // Áreas de práctica
        $areas_practica = wp_get_post_terms($post_id, 'area_practica', array('fields' => 'names'));
        
        // Auto-detectar entorno para fotos
        $is_local = (strpos($_SERVER['HTTP_HOST'], 'lexhoy.local') !== false);
        ?>
        
        <!-- Cabecera del despacho - NUEVA ESTRUCTURA RESPONSIVE -->
        <div class="despacho-header">
            <!-- Fila de botones superiores -->
            <div class="despacho-buttons-row">
                <!-- Botón de regreso -->
                <a href="<?php echo esc_url(get_search_page_url()); ?>" class="despacho-back-button">
                    <i class="fas fa-arrow-left"></i>
                    Volver al buscador
                </a>
                
                <!-- Badge de verificación (o espacio vacío para mantener alineación) -->
                <div class="verification-badge-container">
                    <?php if ($is_verified == '1'): ?>
                        <div class="verification-badge verified">
                            <i class="fas fa-check-circle"></i>
                            Verificado
                        </div>
                    <?php elseif ($estado_verificacion == 'pendiente'): ?>
                        <div class="verification-badge pending">
                            <i class="fas fa-clock"></i>
                            Pendiente verificación
                        </div>
                    <?php elseif ($estado_verificacion == 'rechazado'): ?>
                        <div class="verification-badge rejected">
                            <i class="fas fa-times-circle"></i>
                            Rechazado
                        </div>
                    <?php else: ?>
                        <!-- Espacio vacío para mantener la alineación -->
                        <div class="verification-badge-spacer"></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Foto de perfil centrada -->
            <?php
            // DEBUG: Información de la foto de perfil
            echo "<!-- DEBUG FOTO - Post ID: {$post_id} -->";
            echo "<!-- DEBUG FOTO - Variable \$foto_perfil: " . ($foto_perfil ? $foto_perfil : 'VACÍA') . " -->";
            echo "<!-- DEBUG FOTO - get_post_meta directo: " . get_post_meta($post_id, '_despacho_foto_perfil', true) . " -->";
            ?>
            <?php if ($foto_perfil): ?>
                <div class="despacho-profile-photo">
                    <img src="<?php echo esc_url($foto_perfil); ?>" alt="Foto de perfil de <?php echo esc_attr($nombre ?: get_the_title()); ?>" class="profile-image">
                </div>
            <?php else: ?>
                <!-- DEBUG: No hay foto, usando predeterminada -->
                <div class="despacho-profile-photo">
                    <img src="<?php echo $is_local ? 'http://lexhoy.local' : 'https://lexhoy.com'; ?>/wp-content/uploads/2025/07/FOTO-DESPACHO-500X500.webp" alt="Foto predeterminada" class="profile-image">
                </div>
            <?php endif; ?>
            
            <!-- Título y subtítulo centrados -->
            <div class="despacho-title-row">
                <div class="despacho-title-content">
                    <h1 class="despacho-title"><?php echo esc_html($nombre ?: get_the_title()); ?></h1>
                    <p class="despacho-subtitle">
                        <?php 
                        $location_parts = array_filter(array($localidad, $provincia));
                        echo esc_html(implode(', ', $location_parts));
                        ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Contenido principal - IDÉNTICO al layout del buscador -->
        <div class="despacho-content">
            <!-- Columna principal - IDÉNTICA a results-sidebar -->
            <div class="despacho-main">
                
                <!-- Información de contacto - IDÉNTICO a las tarjetas del buscador -->
                <div class="despacho-section contact-info-section">
                    <h3><i class="fas fa-address-book"></i> Información de Contacto</h3>
                    <div class="contact-info">
                        <?php if ($telefono): ?>
                            <div class="contact-item">
                                <i class="fas fa-phone"></i>
                                <?php if ($is_verified == '1'): ?>
                                    <a href="tel:<?php echo esc_attr($telefono); ?>"><?php echo esc_html($telefono); ?></a>
                                <?php else: ?>
                                    <span class="phone-verification-notice">Solo mostramos teléfonos de despachos verificados</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($email): ?>
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <?php if ($is_verified == '1'): ?>
                                    <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                <?php else: ?>
                                    <span class="phone-verification-notice">Solo mostramos emails de despachos verificados</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($web): ?>
                            <div class="contact-item">
                                <i class="fas fa-globe"></i>
                                <a href="<?php echo esc_url($web); ?>" target="_blank"><?php echo esc_html($web); ?></a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($direccion): ?>
                            <div class="contact-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo esc_html($direccion); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($codigo_postal): ?>
                            <div class="contact-item">
                                <i class="fas fa-mail-bulk"></i>
                                <span><?php echo esc_html($codigo_postal); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Sedes del Despacho - NUEVA SECCIÓN -->
                <?php 
                $sedes = LexhoySedesManager::get_sedes_activas(get_the_ID());
                if (!empty($sedes)): ?>
                    <div class="despacho-section despacho-sedes">
                        <h3><i class="fas fa-building"></i> Nuestras Sedes</h3>
                        <div class="sedes-container">
                            <?php foreach ($sedes as $sede): ?>
                                <div class="sede-card <?php echo $sede['es_principal'] ? 'sede-principal' : ''; ?>">
                                    <div class="sede-header">
                                        <h4>
                                            🏢 <?php echo esc_html($sede['nombre']); ?>
                                            <?php if ($sede['es_principal']): ?>
                                                <span class="badge-principal">⭐ Principal</span>
                                            <?php endif; ?>
                                        </h4>
                                    </div>
                                    
                                    <div class="sede-info">
                                        <?php if (!empty($sede['persona_contacto'])): ?>
                                            <div class="sede-contact-item">
                                                <i class="fas fa-user"></i>
                                                <span><strong>Contacto:</strong> <?php echo esc_html($sede['persona_contacto']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($sede['telefono'])): ?>
                                            <div class="sede-contact-item">
                                                <i class="fas fa-phone"></i>
                                                <a href="tel:<?php echo esc_attr($sede['telefono']); ?>" class="phone-link">
                                                    <?php echo esc_html($sede['telefono']); ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($sede['email_contacto'])): ?>
                                            <div class="sede-contact-item">
                                                <i class="fas fa-envelope"></i>
                                                <a href="mailto:<?php echo esc_attr($sede['email_contacto']); ?>">
                                                    <?php echo esc_html($sede['email_contacto']); ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($sede['direccion_completa'])): ?>
                                            <div class="sede-contact-item">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?php echo esc_html($sede['direccion_completa']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($sede['horario_atencion'])): ?>
                                            <div class="sede-contact-item">
                                                <i class="fas fa-clock"></i>
                                                <span><strong>Horario:</strong> <?php echo esc_html($sede['horario_atencion']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($sede['servicios_especificos'])): ?>
                                            <div class="sede-contact-item">
                                                <i class="fas fa-gavel"></i>
                                                <span><strong>Servicios:</strong> <?php echo esc_html($sede['servicios_especificos']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($sede['observaciones'])): ?>
                                            <div class="sede-contact-item">
                                                <i class="fas fa-info-circle"></i>
                                                <span><strong>Observaciones:</strong> <?php echo esc_html($sede['observaciones']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Descripción - IDÉNTICO a las tarjetas del buscador -->
                <?php if ($descripcion): ?>
                    <div class="despacho-section">
                        <h3><i class="fas fa-info-circle"></i> Descripción</h3>
                        <p><?php echo esc_html($descripcion); ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Experiencia - IDÉNTICO a las tarjetas del buscador -->
                <?php if ($experiencia): ?>
                    <div class="despacho-section">
                        <h3><i class="fas fa-briefcase"></i> Experiencia</h3>
                        <p><?php echo esc_html($experiencia); ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Horario - IDÉNTICO a las tarjetas del buscador -->
                <?php if ($horario && is_array($horario)): ?>
                    <div class="despacho-section">
                        <h3><i class="fas fa-clock"></i> Horario de Atención</h3>
                        <div class="schedule-grid">
                            <?php
                            $dias = array(
                                'lunes' => 'Lunes',
                                'martes' => 'Martes', 
                                'miercoles' => 'Miércoles',
                                'jueves' => 'Jueves',
                                'viernes' => 'Viernes',
                                'sabado' => 'Sábado',
                                'domingo' => 'Domingo'
                            );
                            
                            foreach ($dias as $dia_key => $dia_nombre):
                                $horario_dia = isset($horario[$dia_key]) ? $horario[$dia_key] : '';
                                if ($horario_dia):
                            ?>
                                <div class="schedule-item">
                                    <span class="schedule-day"><?php echo esc_html($dia_nombre); ?></span>
                                    <span class="schedule-time"><?php echo esc_html($horario_dia); ?></span>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                
            </div>
            
            <!-- Columna lateral - IDÉNTICA a filters-sidebar -->
            <div class="despacho-sidebar">
                
                <!-- Áreas de práctica - IDÉNTICO al estilo del buscador -->
                <?php if (!empty($areas_practica)): ?>
                    <div class="despacho-section">
                        <h3><i class="fas fa-gavel"></i> Áreas de Práctica</h3>
                        <div class="specialties-list">
                            <?php foreach ($areas_practica as $area): ?>
                                <span class="specialty-tag"><?php echo esc_html($area); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Especialidades - IDÉNTICO al estilo del buscador -->
                <?php if ($especialidades): ?>
                    <div class="despacho-section">
                        <h3><i class="fas fa-star"></i> Especialidades</h3>
                        <div class="specialties-list">
                            <?php 
                            $especialidades_array = array_filter(array_map('trim', explode(',', $especialidades)));
                            foreach ($especialidades_array as $especialidad): 
                            ?>
                                <span class="specialty-tag"><?php echo esc_html($especialidad); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Información adicional - IDÉNTICO al estilo del buscador -->
                <div class="despacho-section">
                    <h3><i class="fas fa-building"></i> Información del Despacho</h3>
                    
                    <?php if ($tamano_despacho): ?>
                        <p><strong>Tamaño:</strong> <?php echo esc_html($tamano_despacho); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($ano_fundacion): ?>
                        <p><strong>Año de fundación:</strong> <?php echo esc_html($ano_fundacion); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($estado_registro): ?>
                        <p>
                            <span class="registration-status <?php echo esc_attr($estado_registro); ?>">
                                <?php echo esc_html(ucfirst($estado_registro)); ?>
                            </span>
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Redes sociales - IDÉNTICO al estilo del buscador -->
                <?php if ($redes_sociales && is_array($redes_sociales)): ?>
                    <div class="despacho-section">
                        <h3><i class="fas fa-share-alt"></i> Redes Sociales</h3>
                        <div class="social-links">
                            <?php
                            $redes_iconos = array(
                                'facebook' => 'fab fa-facebook-f',
                                'twitter' => 'fab fa-twitter',
                                'linkedin' => 'fab fa-linkedin-in',
                                'instagram' => 'fab fa-instagram'
                            );
                            
                            foreach ($redes_sociales as $red => $url):
                                if ($url && isset($redes_iconos[$red])):
                            ?>
                                <a href="<?php echo esc_url($url); ?>" class="social-link" target="_blank" rel="noopener">
                                    <i class="<?php echo esc_attr($redes_iconos[$red]); ?>"></i>
                                </a>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Botón para actualizar datos del despacho -->
                <div class="despacho-section">
                    <h3><i class="fas fa-edit"></i> Actualizar Información</h3>
                    <p>¿Eres la persona responsable de este despacho? ¿Quieres actualizar los datos? Ponte en contacto con nosotros.</p>
                    <button class="update-despacho-btn" onclick="openUpdatePopup()">
                        <i class="fas fa-pen-to-square"></i>
                        Actualizar Datos del Despacho
                    </button>
                </div>
                
            </div>
        </div>
        
        <!-- Popup para actualizar datos -->
        <div id="update-despacho-popup" class="update-popup-overlay">
            <div class="update-popup-content">
                <div class="update-popup-header">
                    <h3><i class="fas fa-edit"></i> Actualizar Datos del Despacho</h3>
                    <button class="close-popup-btn" onclick="closeUpdatePopup()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="update-popup-body">
                    <p><strong>Despacho:</strong> <?php echo esc_html($nombre ?: get_the_title()); ?></p>
                    <p>Para actualizar la información de este despacho, por favor completa el formulario de contacto. Nos pondremos en contacto contigo lo antes posible.</p>
                    
                    <!-- Contact Form 7 - Formulario Despachos -->
                    <div class="despacho-contact-form">
                        <?php 
                        // Verificar si Contact Form 7 está activo
                        if (function_exists('wpcf7_get_contact_form_by_title')) {
                            // Renderizar el formulario con datos del despacho
                            echo do_shortcode('[contact-form-7 id="0725a70" title="Formulario Despachos"]');
                        } elseif (function_exists('do_shortcode')) {
                            // Intentar renderizar el shortcode directamente
                            echo do_shortcode('[contact-form-7 id="0725a70" title="Formulario Despachos"]');
                        } else {
                            // Fallback si Contact Form 7 no está disponible
                            echo '<div class="contact-form-fallback">';
                            echo '<p style="text-align: center; padding: 40px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; color: #856404;">';
                            echo '<i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>';
                            echo '<strong>Contact Form 7 requerido</strong><br>';
                            echo 'Para mostrar el formulario, instala y activa el plugin Contact Form 7.';
                            echo '</p>';
                            echo '</div>';
                        }
                        ?>
                        
                        <!-- Campos ocultos con información del despacho -->
                        <input type="hidden" id="despacho-telefono-oculto" value="<?php echo esc_attr($telefono); ?>" />
                        <input type="hidden" id="despacho-nombre-oculto" value="<?php echo esc_attr($nombre ?: get_the_title()); ?>" />
                        <input type="hidden" id="despacho-id-oculto" value="<?php echo esc_attr($post_id); ?>" />
                        
                        <script>
                        // Rellenar automáticamente campos del formulario si están disponibles
                        document.addEventListener('DOMContentLoaded', function() {
                            setTimeout(function() {
                                // Intentar encontrar y rellenar campos del formulario
                                const nombreDespacho = document.getElementById('despacho-nombre-oculto')?.value;
                                const telefonoDespacho = document.getElementById('despacho-telefono-oculto')?.value;
                                const despacheId = document.getElementById('despacho-id-oculto')?.value;
                                
                                // Buscar campos del formulario y pre-rellenarlos
                                const form = document.querySelector('.despacho-contact-form form');
                                if (form) {
                                    // Campo de nombre del despacho (si existe)
                                    const nameField = form.querySelector('input[name*="despacho"], input[name*="empresa"], input[name*="nombre-despacho"]');
                                    if (nameField && nombreDespacho) {
                                        nameField.value = nombreDespacho;
                                    }
                                    
                                    // Campo oculto de teléfono (si existe)
                                    const phoneField = form.querySelector('input[name*="telefono"], input[type="tel"]');
                                    if (phoneField && telefonoDespacho) {
                                        phoneField.value = telefonoDespacho;
                                        phoneField.type = 'hidden'; // Hacer el campo oculto
                                    }
                                    
                                    // Campo de ID del despacho (si existe)
                                    const idField = form.querySelector('input[name*="despacho-id"], input[name*="id"]');
                                    if (idField && despacheId) {
                                        idField.value = despacheId;
                                    }
                                }
                            }, 500);
                        });
                        </script>
                    </div>
                </div>
            </div>
        </div>
        
    <?php endwhile; endif; ?>
</div>

<script>
function openUpdatePopup() {
    document.getElementById('update-despacho-popup').style.display = 'flex';
    document.body.style.overflow = 'hidden'; // Prevenir scroll del body
}

function closeUpdatePopup() {
    document.getElementById('update-despacho-popup').style.display = 'none';
    document.body.style.overflow = 'auto'; // Restaurar scroll del body
}

// Cerrar popup al hacer clic fuera del contenido
document.addEventListener('DOMContentLoaded', function() {
    const popup = document.getElementById('update-despacho-popup');
    popup.addEventListener('click', function(e) {
        if (e.target === popup) {
            closeUpdatePopup();
        }
    });
    
    // Cerrar popup con la tecla Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && popup.style.display === 'flex') {
            closeUpdatePopup();
        }
    });
});
</script>

<?php get_footer(); ?> 
<?php
/**
 * Plantilla personalizada para mostrar despachos individuales
 * Diseño IDÉNTICO al buscador de despachos
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// La URL de búsqueda se obtiene ahora de forma centralizada desde LexhoyDespachosCPT
$search_url = '';
if (class_exists('LexhoyDespachosCPT')) {
    $cpt_manager = new LexhoyDespachosCPT();
    $search_url = $cpt_manager->get_search_page_url();
}
if (empty($search_url)) {
    $search_url = home_url('/');
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
        
        // Limpiar caché para asegurar datos más recientes
        wp_cache_delete($post_id, 'post_meta');
        
        // NUEVA LÓGICA: Obtener datos de sedes primero, fallback a campos legacy
        $sedes = get_post_meta($post_id, '_despacho_sedes', true);
        $sede_principal = null;
        
        // Buscar sede principal
        if (!empty($sedes) && is_array($sedes)) {
            foreach ($sedes as $sede) {
                if (isset($sede['es_principal']) && $sede['es_principal']) {
                    $sede_principal = $sede;
                    break;
                }
            }
            // Si no hay sede principal marcada, usar la primera
            if (!$sede_principal && !empty($sedes)) {
                $sede_principal = $sedes[0];
            }
        }
        
        // Función helper para obtener valor de sede principal o fallback
        $get_despacho_data = function($sede_key, $legacy_key) use ($sede_principal, $post_id) {
            // FORZAR: SIEMPRE leer desde metadatos legacy (datos editables)
            return get_post_meta($post_id, $legacy_key, true) ?: '';
        };
        
        // Información básica desde sede principal (nueva estructura)
        $nombre = get_the_title(); // TÍTULO DEL DESPACHO - NO de la sede
        
        // Construir dirección desde los campos separados de la sede
        $calle = $sede_principal['calle'] ?? get_post_meta($post_id, '_despacho_calle', true);
        $numero = $sede_principal['numero'] ?? get_post_meta($post_id, '_despacho_numero', true);
        $piso = $sede_principal['piso'] ?? get_post_meta($post_id, '_despacho_piso', true);
        
        // Construir dirección completa
        $direccion_parts = array_filter(array($calle, $numero, $piso));
        $direccion = !empty($direccion_parts) ? implode(' ', $direccion_parts) : 
                    (get_post_meta($post_id, '_despacho_direccion', true) ?: '');
        
        $localidad = $sede_principal['localidad'] ?? get_post_meta($post_id, '_despacho_localidad', true);
        $provincia = $sede_principal['provincia'] ?? get_post_meta($post_id, '_despacho_provincia', true);
        $codigo_postal = $sede_principal['codigo_postal'] ?? get_post_meta($post_id, '_despacho_codigo_postal', true);
        
        // DEBUG TEMPORAL: Ver qué datos se están leyendo
        error_log("FRONTEND DEBUG - Post ID: {$post_id}");
        error_log("FRONTEND DEBUG - Sede principal calle: " . ($sede_principal['calle'] ?? 'N/A'));
        error_log("FRONTEND DEBUG - Sede principal numero: " . ($sede_principal['numero'] ?? 'N/A'));
        error_log("FRONTEND DEBUG - Sede principal piso: " . ($sede_principal['piso'] ?? 'N/A'));
        error_log("FRONTEND DEBUG - Sede principal codigo_postal: " . ($sede_principal['codigo_postal'] ?? 'N/A'));
        error_log("FRONTEND DEBUG - Variable direccion final: " . $direccion);
        $telefono = $sede_principal['telefono'] ?? get_post_meta($post_id, '_despacho_telefono', true);
        $email = $sede_principal['email_contacto'] ?? get_post_meta($post_id, '_despacho_email', true);
        $web = $sede_principal['web'] ?? get_post_meta($post_id, '_despacho_web', true);
        
        // --- CAMBIO FASE 2: DESCRIPCIÓN DINÁMICA ---
        $descripcion = '';
        if (class_exists('LexhoyDespachosCPT')) {
            $cpt_manager = new LexhoyDespachosCPT();
            $descripcion = $cpt_manager->get_dynamic_description($post_id);
        } else {
            $descripcion = $sede_principal['descripcion'] ?? get_post_meta($post_id, '_despacho_descripcion', true);
        }
        $estado_verificacion = $sede_principal['estado_verificacion'] ?? get_post_meta($post_id, '_despacho_estado_verificacion', true);
        $is_verified = $sede_principal['is_verified'] ?? get_post_meta($post_id, '_despacho_is_verified', true);
        
        // Información adicional (desde sede principal)
        $especialidades = $sede_principal['especialidades'] ?? get_post_meta($post_id, '_despacho_especialidades', true);
        $experiencia = $sede_principal['experiencia'] ?? get_post_meta($post_id, '_despacho_experiencia', true);
        $tamano_despacho = $sede_principal['tamano_despacho'] ?? get_post_meta($post_id, '_despacho_tamaño', true);
        $ano_fundacion = $sede_principal['ano_fundacion'] ?? get_post_meta($post_id, '_despacho_año_fundacion', true);
        $estado_registro = $sede_principal['estado_registro'] ?? get_post_meta($post_id, '_despacho_estado_registro', true);
        $foto_perfil = $sede_principal['foto_perfil'] ?? get_post_meta($post_id, '_despacho_foto_perfil', true);
        $numero_colegiado = $sede_principal['numero_colegiado'] ?? get_post_meta($post_id, '_despacho_numero_colegiado', true);
        $colegio = $sede_principal['colegio'] ?? get_post_meta($post_id, '_despacho_colegio', true);
        
        // DEBUG TEMPORAL: Ver datos profesionales y verificación
        error_log("FRONTEND DEBUG - Sede principal numero_colegiado: " . ($sede_principal['numero_colegiado'] ?? 'N/A'));
        error_log("FRONTEND DEBUG - Sede principal colegio: " . ($sede_principal['colegio'] ?? 'N/A'));
        error_log("FRONTEND DEBUG - Sede principal is_verified: " . ($sede_principal['is_verified'] ?? 'N/A'));
        error_log("FRONTEND DEBUG - Sede principal estado_verificacion: " . ($sede_principal['estado_verificacion'] ?? 'N/A'));
        error_log("FRONTEND DEBUG - Variable numero_colegiado final: " . $numero_colegiado);
        error_log("FRONTEND DEBUG - Variable colegio final: " . $colegio);
        error_log("FRONTEND DEBUG - Variable is_verified final: " . ($is_verified ? 'TRUE' : 'FALSE'));
        error_log("FRONTEND DEBUG - Variable estado_verificacion final: " . $estado_verificacion);
        
        // Horarios (nueva estructura vs legacy)
        $horario = null;
        if ($sede_principal && isset($sede_principal['horarios']) && is_array($sede_principal['horarios'])) {
            $horario = $sede_principal['horarios'];
        } else {
            $horario = get_post_meta($post_id, '_despacho_horario', true);
        }
        
        // Redes sociales (nueva estructura vs legacy)
        $redes_sociales = null;
        if ($sede_principal && isset($sede_principal['redes_sociales']) && is_array($sede_principal['redes_sociales'])) {
            $redes_sociales = $sede_principal['redes_sociales'];
        } else {
            $redes_sociales = get_post_meta($post_id, '_despacho_redes_sociales', true);
        }
        

        // Áreas de práctica: solo sede principal (igual que en el buscador)
        if ($sede_principal && isset($sede_principal['areas_practica']) && is_array($sede_principal['areas_practica']) && !empty($sede_principal['areas_practica'])) {
            $areas_practica = array_unique(array_filter($sede_principal['areas_practica']));
            sort($areas_practica);
        } else {
            // Fallback: usar taxonomía si no hay áreas en sede principal
            $areas_practica = wp_get_post_terms($post_id, 'area_practica', array('fields' => 'names'));
            $areas_practica = array_unique(array_filter($areas_practica));
            sort($areas_practica);
        }
        // Debug para áreas de práctica (solo sede principal)
        error_log("FRONTEND DEBUG - Areas practica (solo sede principal): " . json_encode($areas_practica));
        
        // Auto-detectar entorno para fotos
        $is_local = (strpos($_SERVER['HTTP_HOST'], 'lexhoy.local') !== false);
        
        // Función auxiliar para verificar si todos los horarios están vacíos
        function horarios_todos_vacios($horarios) {
            if (!$horarios || !is_array($horarios)) {
                return true;
            }
            foreach ($horarios as $horario) {
                if (!empty(trim($horario))) {
                    return false;
                }
            }
            return true;
        }
        
        // Función auxiliar para verificar si todas las redes sociales están vacías
        function redes_sociales_todas_vacias($redes) {
            if (!$redes || !is_array($redes)) {
                return true;
            }
            foreach ($redes as $red) {
                if (!empty(trim($red))) {
                    return false;
                }
            }
            return true;
        }
        
        // Función auxiliar para verificar si la información del despacho está vacía
        function informacion_despacho_vacia($tamano, $ano_fundacion) {
            return empty(trim($tamano)) && empty(trim($ano_fundacion));
        }
        ?>
        
        <!-- Cabecera del despacho - NUEVA ESTRUCTURA RESPONSIVE -->
        <div class="despacho-header">
            <!-- Fila de botones superiores -->
            <div class="despacho-buttons-row">
                <!-- Botón de regreso -->
                <a href="<?php echo esc_url($search_url); ?>" class="despacho-back-button">
                    <i class="fas fa-arrow-left"></i>
                    Volver al buscador
                </a>
                
                <?php
                // --- CAMBIO FASE 4: BREADCRUMBS SCHEMA ---
                $prov_terms = wp_get_post_terms($post_id, 'provincia');
                if (!empty($prov_terms) && !is_wp_error($prov_terms)) {
                    $prov_term = $prov_terms[0];
                    $breadcrumb_schema = array(
                        "@context" => "https://schema.org",
                        "@type" => "BreadcrumbList",
                        "itemListElement" => array(
                            array(
                                "@type" => "ListItem",
                                "position" => 1,
                                "name" => "Inicio",
                                "item" => home_url('/')
                            ),
                            array(
                                "@type" => "ListItem",
                                "position" => 2,
                                "name" => "Abogados en " . $prov_term->name,
                                "item" => get_term_link($prov_term)
                            ),
                            array(
                                "@type" => "ListItem",
                                "position" => 3,
                                "name" => $nombre,
                                "item" => get_permalink($post_id)
                            )
                        )
                    );
                    echo '<script type="application/ld+json">' . json_encode($breadcrumb_schema) . '</script>';
                }
                ?>
                
                <!-- Badge de verificación (o espacio vacío para mantener alineación) -->
                <div class="verification-badge-container">
                    <?php if ($estado_verificacion === 'verificado'): ?>
                        <div class="verification-badge verified">
                            <i class="fas fa-check-circle"></i>
                            Verificado
                        </div>
                    <?php elseif ($estado_verificacion === 'pendiente'): ?>
                        <div class="verification-badge pending">
                            <i class="fas fa-clock"></i>
                            Pendiente verificación
                        </div>
                    <?php elseif ($estado_verificacion === 'rechazado'): ?>
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
                    // Información de la foto de perfil
            ?>
            <?php if ($foto_perfil): ?>
                <div class="despacho-profile-photo">
                    <img src="<?php echo esc_url($foto_perfil); ?>" alt="Foto de perfil de <?php echo esc_attr($nombre ?: get_the_title()); ?>" class="profile-image">
                </div>
            <?php else: ?>
                <!-- Foto predeterminada -->
                <div class="despacho-profile-photo">
                    <img src="<?php echo $is_local ? 'http://lexhoy.local' : 'https://lexhoy.com'; ?>/wp-content/uploads/2025/07/FOTO-DESPACHO-500X500.webp" alt="Foto predeterminada" class="profile-image">
                </div>
            <?php endif; ?>
            
            <!-- Título centrado (sin subtítulo) -->
            <div class="despacho-title-row">
                <div class="despacho-title-content">
                    <h1 class="despacho-title"><?php echo esc_html($nombre ?: get_the_title()); ?></h1>
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
                                <?php if ($estado_verificacion === 'verificado'): ?>
                                    <a href="tel:<?php echo esc_attr($telefono); ?>"><?php echo esc_html($telefono); ?></a>
                                <?php else: ?>
                                    <span class="phone-verification-notice">Solo mostramos teléfonos de despachos verificados</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($email): ?>
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <?php if ($estado_verificacion === 'verificado'): ?>
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
                        
                        <?php if ($direccion || $localidad || $provincia): ?>
                            <div class="contact-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>
                                    <?php 
                                    // Construir dirección completa con localidad y provincia
                                    $address_parts = array();
                                    
                                    // Agregar dirección si existe
                                    if (!empty($direccion)) {
                                        $address_parts[] = $direccion;
                                    }
                                    
                                    // Agregar localidad y provincia
                                    $location_parts = array_filter(array($localidad, $provincia));
                                    if (!empty($location_parts)) {
                                        $address_parts[] = implode(', ', $location_parts);
                                    }
                                    
                                    // Agregar código postal si existe
                                    if (!empty($codigo_postal)) {
                                        $address_parts[] = '(' . $codigo_postal . ')';
                                    }
                                    
                                    echo esc_html(implode(', ', $address_parts));
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($numero_colegiado): ?>
                            <div class="contact-item">
                                <i class="fas fa-id-card"></i>
                                <span><strong>Nº Colegiado:</strong> <?php echo esc_html($numero_colegiado); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($colegio): ?>
                            <div class="contact-item">
                                <i class="fas fa-university"></i>
                                <span><strong>Colegio:</strong> <?php echo esc_html($colegio); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Sedes del Despacho - NUEVA SECCIÓN (solo si hay sedes adicionales NO principales) -->
                <?php 
                // Obtener sedes del nuevo formato
                $sedes = get_post_meta($post_id, '_despacho_sedes', true);
                $sedes_adicionales = array();
                if (!empty($sedes) && is_array($sedes)) {
                    foreach ($sedes as $sede) {
                        if (!isset($sede['es_principal']) || !$sede['es_principal']) {
                            $sedes_adicionales[] = $sede;
                        }
                    }
                }
                if (!empty($sedes_adicionales)): ?>
                    <div class="despacho-section despacho-sedes">
                        <h3><i class="fas fa-building"></i> Otras Sedes</h3>
                        <div class="sedes-container">
                            <?php foreach ($sedes_adicionales as $sede): ?>
                                <div class="sede-card"><?php // Solo sedes adicionales (no principales) ?>
                                    <div class="sede-header">
                                        <h4>
                                            🏢 <?php echo esc_html($sede['nombre']); ?>
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
                                                <?php if ($estado_verificacion === 'verificado'): ?>
                                                    <a href="tel:<?php echo esc_attr($sede['telefono']); ?>" class="phone-link">
                                                        <?php echo esc_html($sede['telefono']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="phone-verification-notice">Solo mostramos teléfonos de despachos verificados</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($sede['email_contacto'])): ?>
                                            <div class="sede-contact-item">
                                                <i class="fas fa-envelope"></i>
                                                <?php if ($estado_verificacion === 'verificado'): ?>
                                                    <a href="mailto:<?php echo esc_attr($sede['email_contacto']); ?>">
                                                        <?php echo esc_html($sede['email_contacto']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="phone-verification-notice">Solo mostramos emails de despachos verificados</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php 
                                        // Construir dirección completa de la sede
                                        $direccion_parts = array_filter(array(
                                            $sede['calle'] ?? '',
                                            $sede['numero'] ?? '',
                                            $sede['piso'] ?? ''
                                        ));
                                        $direccion_sede = !empty($direccion_parts) ? implode(' ', $direccion_parts) : ($sede['direccion_completa'] ?? '');
                                        
                                        $localidad_parts = array_filter(array(
                                            $sede['localidad'] ?? '',
                                            $sede['provincia'] ?? '',
                                            $sede['codigo_postal'] ?? ''
                                        ));
                                        $localidad_sede = !empty($localidad_parts) ? implode(', ', $localidad_parts) : '';
                                        
                                        $direccion_completa_sede = trim($direccion_sede . ($localidad_sede ? ', ' . $localidad_sede : ''));
                                        
                                        if (!empty($direccion_completa_sede)): ?>
                                            <div class="sede-contact-item">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?php echo esc_html($direccion_completa_sede); ?></span>
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
                                        
                                        <?php if (!empty($sede['areas_practica']) && is_array($sede['areas_practica'])): ?>
                                            <div class="sede-contact-item">
                                                <i class="fas fa-gavel"></i>
                                                <span><strong>Áreas de práctica:</strong> 
                                                    <?php echo esc_html(implode(', ', $sede['areas_practica'])); ?>
                                                </span>
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
                <?php if ($horario && is_array($horario) && !horarios_todos_vacios($horario)): ?>
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
                <?php if (!informacion_despacho_vacia($tamano_despacho, $ano_fundacion)): ?>
                    <div class="despacho-section">
                        <h3><i class="fas fa-building"></i> Información del Despacho</h3>
                        
                        <?php if ($tamano_despacho): ?>
                            <p><strong>Tamaño:</strong> <?php echo esc_html($tamano_despacho); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($ano_fundacion): ?>
                            <p><strong>Año de fundación:</strong> <?php echo esc_html($ano_fundacion); ?></p>
                        <?php endif; ?>
                        
                        <?php // Estado de registro eliminado ya que no aporta valor al cliente ?>
                    </div>
                <?php endif; ?>
                
                <!-- Redes sociales - IDÉNTICO al estilo del buscador -->
                <?php if ($redes_sociales && is_array($redes_sociales) && !redes_sociales_todas_vacias($redes_sociales)): ?>
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
                    <p>¿Eres la persona responsable de este despacho? ¿Quieres actualizar los datos? Accede a nuestro portal para gestionar tu información.</p>
                    <a href="https://despachos.lexhoy.com/" class="update-despacho-btn">
                        <i class="fas fa-pen-to-square"></i>
                        Actualizar Datos del Despacho
                    </a>
                </div>
                
            </div>
        </div>
        
    <?php endwhile; endif; ?>
</div>


<?php get_footer(); ?> 
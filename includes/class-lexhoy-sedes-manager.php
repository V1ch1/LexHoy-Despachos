<?php
/**
 * Gestor de Sedes para Despachos
 * Permite a cada despacho tener múltiples sedes con información específica
 */

if (!defined('ABSPATH')) {
    exit;
}

class LexhoySedesManager {
    
    public function __construct() {
        // Solo inicializar si WordPress está disponible
        if (function_exists('add_action')) {
            // Agregar meta box para gestión de sedes
            add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
            add_action('save_post', array($this, 'save_sedes_data'));
            
            // AJAX handlers para gestión dinámica de sedes
            add_action('wp_ajax_lexhoy_add_sede', array($this, 'ajax_add_sede'));
            add_action('wp_ajax_lexhoy_remove_sede', array($this, 'ajax_remove_sede'));
            add_action('wp_ajax_lexhoy_update_sede', array($this, 'ajax_update_sede'));
            add_action('wp_ajax_upload_sede_photo', array($this, 'ajax_upload_sede_photo'));
            add_action('wp_ajax_lexhoy_cargar_sedes_json', array($this, 'ajax_cargar_sedes_json'));
            
            // Enqueue scripts y styles para admin
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        }
    }
    
    /**
     * Agregar meta box para gestión de sedes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'despacho_sedes',
            '🏢 Gestión de Sedes',
            array($this, 'render_sedes_meta_box'),
            'despacho',
            'normal',
            'high'
        );
    }
    
    /**
     * Renderizar meta box de sedes
     */
    public function render_sedes_meta_box($post) {
        // Obtener sedes existentes de WordPress
        $sedes_wp = get_post_meta($post->ID, '_despacho_sedes', true);
        if (!is_array($sedes_wp)) {
            $sedes_wp = array();
        }
        
        // OPTIMIZACIÓN: No cargar datos del JSON sincrónicamente para evitar pantalla en blanco
        // Los datos se cargarán vía AJAX después de renderizar la página
        $sedes_json = array();
        
        // Nonce para seguridad
        wp_nonce_field('despacho_sedes_meta_box', 'despacho_sedes_nonce');
        ?>
        
        <div id="sedes-container">
            
            <?php if (!empty($sedes_json)): ?>
                <div class="sedes-json-section">
                    <h3 style="color: #2563eb; margin-bottom: 20px;">
                        🏢 Sedes Existentes del Despacho
                        <small style="color: #666; font-weight: normal;">
                            (Datos del JSON - Solo lectura)
                        </small>
                    </h3>
                    
                    <div class="sedes-json-grid">
                        <?php foreach ($sedes_json as $index => $sede_json): ?>
                            <div class="sede-json-card">
                                <div class="sede-json-header">
                                    <h4><?php echo esc_html($sede_json['nombre'] ?? 'Sede sin nombre'); ?></h4>
                                    <div class="sede-json-badges">
                                        <?php if (!empty($sede_json['es_principal'])): ?>
                                            <span class="badge badge-principal">⭐ Principal</span>
                                        <?php endif; ?>
                                        <?php if (!empty($sede_json['activa'])): ?>
                                            <span class="badge badge-activa">✅ Activa</span>
                                        <?php endif; ?>
                                        <?php if (!empty($sede_json['is_verified'])): ?>
                                            <span class="badge badge-verificada">🔒 Verificada</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="sede-json-content">
                                    <?php if (!empty($sede_json['persona_contacto'])): ?>
                                        <p><strong>👤 Contacto:</strong> <?php echo esc_html($sede_json['persona_contacto']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($sede_json['telefono'])): ?>
                                        <p><strong>📞 Teléfono:</strong> <?php echo esc_html($sede_json['telefono']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($sede_json['email_contacto'])): ?>
                                        <p><strong>📧 Email:</strong> <?php echo esc_html($sede_json['email_contacto']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($sede_json['localidad']) || !empty($sede_json['provincia'])): ?>
                                        <p><strong>📍 Ubicación:</strong> 
                                            <?php echo esc_html(trim($sede_json['localidad'] . ', ' . $sede_json['provincia'], ', ')); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($sede_json['areas_practica']) && is_array($sede_json['areas_practica'])): ?>
                                        <p><strong>⚖️ Áreas:</strong> 
                                            <?php echo esc_html(implode(', ', $sede_json['areas_practica'])); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="sede-json-actions">
                                    <button type="button" class="button button-secondary import-sede-btn" 
                                            data-sede-index="<?php echo $index; ?>">
                                        📥 Importar a WordPress
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <h5 style="color: #1976d2; margin: 20px 0 15px;">📱 Redes Sociales</h5>
                    
                    <div class="redes-sociales" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="sede-field">
                            <label for="sede_facebook_<?php echo $index; ?>" style="font-size: 12px;">📘 Facebook</label>
                            <input type="url" 
                                   id="sede_facebook_<?php echo $index; ?>" 
                                   name="sedes[<?php echo $index; ?>][facebook]" 
                                   value="<?php echo esc_attr($sede['facebook']); ?>"
                                   placeholder="https://facebook.com/..."
                                   style="font-size: 12px;">
                        </div>
                        
                        <div class="sede-field">
                            <label for="sede_twitter_<?php echo $index; ?>" style="font-size: 12px;">🐦 Twitter</label>
                            <input type="url" 
                                   id="sede_twitter_<?php echo $index; ?>" 
                                   name="sedes[<?php echo $index; ?>][twitter]" 
                                   value="<?php echo esc_attr($sede['twitter']); ?>"
                                   placeholder="https://twitter.com/..."
                                   style="font-size: 12px;">
                        </div>
                        
                        <div class="sede-field">
                            <label for="sede_linkedin_<?php echo $index; ?>" style="font-size: 12px;">💼 LinkedIn</label>
                            <input type="url" 
                                   id="sede_linkedin_<?php echo $index; ?>" 
                                   name="sedes[<?php echo $index; ?>][linkedin]" 
                                   value="<?php echo esc_attr($sede['linkedin']); ?>"
                                   placeholder="https://linkedin.com/..."
                                   style="font-size: 12px;">
                        </div>
                        
                        <div class="sede-field">
                            <label for="sede_instagram_<?php echo $index; ?>" style="font-size: 12px;">📷 Instagram</label>
                            <input type="url" 
                                   id="sede_instagram_<?php echo $index; ?>" 
                                   name="sedes[<?php echo $index; ?>][instagram]" 
                                   value="<?php echo esc_attr($sede['instagram']); ?>"
                                   placeholder="https://instagram.com/..."
                                   style="font-size: 12px;">
                        </div>
                    </div>
                </div>
                
                <div class="sedes-divider">
                    <hr style="margin: 30px 0; border: 1px solid #ddd;">
                </div>
            <?php endif; ?>
            
            <div class="sedes-wp-section">
                <div class="sedes-header">
                    <h3 style="color: #2563eb; margin-bottom: 15px;">
                        🏗️ Sedes Gestionadas en WordPress
                    </h3>
                    
                    <div id="sedes-list">
                        <?php if (!empty($sedes_wp)): ?>
                            <?php foreach ($sedes_wp as $index => $sede): ?>
                                <?php $this->render_sede_form($index, $sede, false); // No contraer sedes existentes ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php 
                            // Si no hay sedes en WordPress, crear la primera sede con datos existentes del despacho
                            $sede_inicial = array('es_principal' => true, 'activa' => true);
                            
                            // Intentar cargar datos desde metadatos tradicionales del despacho
                            $direccion_completa = get_post_meta($post->ID, '_despacho_direccion', true);
                            
                            // Intentar separar calle y número de la dirección completa
                            $calle = '';
                            $numero = '';
                            if (!empty($direccion_completa)) {
                                // Buscar patrones como "Calle Principal 123" o "Av. España, 45"
                                if (preg_match('/^(.+?)[\s,]+(\d+[a-zA-Z]*)/', $direccion_completa, $matches)) {
                                    $calle = trim($matches[1]);
                                    $numero = trim($matches[2]);
                                } else {
                                    // Si no se puede separar, poner todo en calle
                                    $calle = $direccion_completa;
                                }
                            }
                            
                            // Obtener áreas de práctica como taxonomía
                            $areas_practica_terms = wp_get_post_terms($post->ID, 'area_practica', array('fields' => 'names'));
                            $areas_practica = is_array($areas_practica_terms) ? $areas_practica_terms : array();
                            
                            // Obtener horarios y redes sociales como arrays
                            $horario = get_post_meta($post->ID, '_despacho_horario', true);
                            $redes_sociales = get_post_meta($post->ID, '_despacho_redes_sociales', true);
                            
                            // Asegurar que son arrays
                            if (!is_array($horario)) $horario = array();
                            if (!is_array($redes_sociales)) $redes_sociales = array();
                            
                            $campos_meta = array(
                                'nombre' => get_post_meta($post->ID, '_despacho_nombre', true),
                                'persona_contacto' => '', // Este campo no existe en metadatos tradicionales
                                'email_contacto' => get_post_meta($post->ID, '_despacho_email', true),
                                'telefono' => get_post_meta($post->ID, '_despacho_telefono', true),
                                'direccion' => $direccion_completa, // Dirección completa original
                                'calle' => $calle, // Calle extraída
                                'numero' => $numero, // Número extraído
                                'localidad' => get_post_meta($post->ID, '_despacho_localidad', true),
                                'provincia' => get_post_meta($post->ID, '_despacho_provincia', true),
                                'codigo_postal' => get_post_meta($post->ID, '_despacho_codigo_postal', true),
                                'web' => get_post_meta($post->ID, '_despacho_web', true),
                                'descripcion' => get_post_meta($post->ID, '_despacho_descripcion', true),
                                'numero_colegiado' => get_post_meta($post->ID, '_despacho_numero_colegiado', true),
                                'colegio' => get_post_meta($post->ID, '_despacho_colegio', true),
                                'experiencia' => get_post_meta($post->ID, '_despacho_experiencia', true),
                                'especialidades' => get_post_meta($post->ID, '_despacho_especialidades', true),
                                'areas_practica' => $areas_practica, // Áreas de práctica desde taxonomía
                                'estado_verificacion' => get_post_meta($post->ID, '_despacho_estado_verificacion', true),
                                'is_verified' => get_post_meta($post->ID, '_despacho_is_verified', true) === '1',
                                'foto_perfil' => get_post_meta($post->ID, '_despacho_foto_perfil', true),
                                
                                // Campos adicionales que podrían existir
                                'ano_fundacion' => get_post_meta($post->ID, '_despacho_año_fundacion', true),
                                'tamano_despacho' => get_post_meta($post->ID, '_despacho_tamaño', true),
                                'estado_registro' => get_post_meta($post->ID, '_despacho_estado_registro', true),
                                
                                // Horarios detallados
                                'horario_lunes' => isset($horario['lunes']) ? $horario['lunes'] : '',
                                'horario_martes' => isset($horario['martes']) ? $horario['martes'] : '',
                                'horario_miercoles' => isset($horario['miercoles']) ? $horario['miercoles'] : '',
                                'horario_jueves' => isset($horario['jueves']) ? $horario['jueves'] : '',
                                'horario_viernes' => isset($horario['viernes']) ? $horario['viernes'] : '',
                                'horario_sabado' => isset($horario['sabado']) ? $horario['sabado'] : '',
                                'horario_domingo' => isset($horario['domingo']) ? $horario['domingo'] : '',
                                
                                // Redes sociales
                                'facebook' => isset($redes_sociales['facebook']) ? $redes_sociales['facebook'] : '',
                                'twitter' => isset($redes_sociales['twitter']) ? $redes_sociales['twitter'] : '',
                                'linkedin' => isset($redes_sociales['linkedin']) ? $redes_sociales['linkedin'] : '',
                                'instagram' => isset($redes_sociales['instagram']) ? $redes_sociales['instagram'] : ''
                            );
                            
                            // Solo usar datos si realmente hay información
                            $tiene_datos = false;
                            foreach ($campos_meta as $campo => $valor) {
                                if (!empty($valor) && $campo !== 'es_principal' && $campo !== 'activa') {
                                    $tiene_datos = true;
                                    break;
                                }
                            }
                            
                            if ($tiene_datos) {
                                // Fusionar datos existentes con valores por defecto de sede
                                $sede_inicial = array_merge($sede_inicial, $campos_meta);
                                
                                // Si no hay nombre específico, usar el título del post
                                if (empty($sede_inicial['nombre'])) {
                                    $sede_inicial['nombre'] = $post->post_title;
                                }
                            }
                            
                            $this->render_sede_form(0, $sede_inicial, false); // Expandida por defecto
                            ?>
                        <?php endif; ?>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <button type="button" id="add-sede-btn" class="button button-primary button-large">
                            ➕ Añadir Nueva Sede Desde Cero
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Template para nuevas sedes -->
        <template id="sede-template">
            <?php $this->render_sede_form('{{INDEX}}', array()); ?>
        </template>
        
        <style>
            /* Estilos para sedes del JSON */
            .sedes-json-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }
            
            .sede-json-card {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .sede-json-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 1px solid #dee2e6;
            }
            
            .sede-json-header h4 {
                margin: 0;
                color: #2563eb;
                font-size: 16px;
            }
            
            .sede-json-badges {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .badge {
                padding: 3px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                text-align: center;
            }
            
            .badge-principal {
                background: #fef3cd;
                color: #856404;
                border: 1px solid #ffeaa7;
            }
            
            .badge-activa {
                background: #d1ecf1;
                color: #0c5460;
                border: 1px solid #abdde5;
            }
            
            .badge-verificada {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            
            .sede-json-content p {
                margin: 8px 0;
                font-size: 14px;
                line-height: 1.4;
            }
            
            .sede-json-actions {
                margin-top: 15px;
                text-align: center;
            }
            
            /* Estilos para sedes de WordPress */
            .sedes-header {
                margin-bottom: 20px;
            }
            
            .sede-item {
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
                background: #ffffff;
                position: relative;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            
            .sede-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid #e9ecef;
            }
            
            .sede-title {
                font-weight: bold;
                color: #2563eb;
                margin: 0;
                font-size: 18px;
            }
            
            .sede-actions {
                display: flex;
                gap: 10px;
            }
            
            .sede-toggle {
                background: #6c757d;
                color: white;
                border: none;
                padding: 8px 12px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 12px;
                transition: background 0.2s;
            }
            
            .sede-toggle:hover {
                background: #5a6268;
            }
            
            .sede-remove {
                background: #dc3545;
                color: white;
                border: none;
                padding: 8px 12px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 12px;
                transition: background 0.2s;
            }
            
            .sede-remove:hover {
                background: #c82333;
            }
            
            .sede-content {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            
            .sede-field {
                margin-bottom: 15px;
            }
            
            .sede-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 5px;
                color: #495057;
            }
            
            .sede-field input,
            .sede-field textarea,
            .sede-field select {
                width: 100%;
                padding: 8px;
                border: 1px solid #ced4da;
                border-radius: 4px;
                font-size: 14px;
            }
            
            .sede-field textarea {
                height: 80px;
                resize: vertical;
            }
            
            .sede-collapsed .sede-content {
                display: none;
            }
            
            .sede-es-principal {
                background: #f8f9fa;
                border-color: #ffc107;
                box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.2);
            }
            
            /* Estilos para checkboxes de configuración */
            .configuracion-sede {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 20px;
                margin: 15px 0;
            }
            
            .configuracion-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }
            
            .configuracion-item {
                display: flex;
                align-items: center;
                padding: 12px;
                background: white;
                border: 1px solid #dee2e6;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .configuracion-item:hover {
                border-color: #2563eb;
                box-shadow: 0 2px 4px rgba(37, 99, 235, 0.1);
            }
            
            .configuracion-item input[type="checkbox"] {
                margin-right: 10px;
                width: 18px;
                height: 18px;
                cursor: pointer;
            }
            
            .configuracion-item .config-label {
                font-weight: 600;
                cursor: pointer;
            }
            
            .config-principal { color: #ffc107; }
            .config-activa { color: #28a745; }
            .config-verificada { color: #17a2b8; }
            
            /* Estilos para áreas de práctica */
            .areas-practica-container {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 20px;
                margin: 15px 0;
            }
            
            .areas-practica-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 10px;
                margin-top: 10px;
            }
            
            .area-practica-item {
                display: flex;
                align-items: center;
                padding: 8px 12px;
                background: white;
                border: 1px solid #dee2e6;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .area-practica-item:hover {
                border-color: #2563eb;
                background: #f1f5f9;
            }
            
            .area-practica-item input[type="checkbox"] {
                margin-right: 8px;
                cursor: pointer;
            }
            
            .area-practica-item label {
                font-size: 13px;
                cursor: pointer;
                margin-bottom: 0;
            }
            
            @media (max-width: 768px) {
                .sede-content {
                    grid-template-columns: 1fr;
                }
                
                .sedes-json-grid {
                    grid-template-columns: 1fr;
                }
                
                .configuracion-grid {
                    grid-template-columns: 1fr;
                }
                
                .areas-practica-grid {
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                }
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let sedeIndex = Math.max(<?php echo count($sedes_wp); ?>, 1); // Asegurar que siempre hay al menos 1
            

            
            // Añadir nueva sede
            $('#add-sede-btn').on('click', function() {
                const template = $('#sede-template').html();
                const newSedeHtml = template.replace(/{{INDEX}}/g, sedeIndex);
                
                // Remover mensaje de "no sedes" si existe
                $('.no-sedes-message').remove();
                
                $('#sedes-list').append(newSedeHtml);
                sedeIndex++;
                
                // Scroll a la nueva sede
                const newSede = $('#sedes-list .sede-item:last');
                newSede[0].scrollIntoView({ behavior: 'smooth' });
            });
            
            // Importar sede desde JSON
            $('.import-sede-btn').on('click', function() {
                const sedeData = <?php echo json_encode($sedes_json); ?>;
                const sedeIndex_import = $(this).data('sede-index');
                const sede = sedeData[sedeIndex_import];
                
                if (sede) {
                    // Crear nueva sede con datos del JSON
                    const template = $('#sede-template').html();
                    let newSedeHtml = template.replace(/{{INDEX}}/g, sedeIndex);
                    
                    // Remover mensaje de "no sedes" si existe
                    $('.no-sedes-message').remove();
                    
                    $('#sedes-list').append(newSedeHtml);
                    
                    // Rellenar campos con datos del JSON
                    const newSedeElement = $('#sedes-list .sede-item:last');
                    
                    Object.keys(sede).forEach(function(key) {
                        const input = newSedeElement.find(`[name*="[${key}]"]`);
                        
                        if (input.length) {
                            if (input.attr('type') === 'checkbox') {
                                input.prop('checked', !!sede[key]);
                            } else if (Array.isArray(sede[key])) {
                                // Para areas_practica
                                sede[key].forEach(function(area) {
                                    newSedeElement.find(`input[value="${area}"]`).prop('checked', true);
                                });
                            } else {
                                input.val(sede[key]);
                            }
                        }
                    });
                    
                    // Actualizar título
                    const nombreSede = sede.nombre || 'Sede importada';
                    newSedeElement.find('.sede-title').text('🏢 ' + nombreSede);
                    
                    sedeIndex++;
                    
                    // Scroll a la nueva sede
                    newSedeElement[0].scrollIntoView({ behavior: 'smooth' });
                    
                    // Deshabilitar botón de importar
                    $(this).prop('disabled', true).text('✅ Importada');
                }
            });
            
            // Remover sede
            $(document).on('click', '.sede-remove', function() {
                const sedeItem = $(this).closest('.sede-item');
                const sedeIndex_current = sedeItem.find('input[name*="[nombre]"]').attr('name').match(/\[(\d+)\]/)[1];
                
                // Evitar eliminar la primera sede (índice 0)
                if (sedeIndex_current == 0) {
                    alert('❌ No se puede eliminar la sede principal obligatoria.');
                    return;
                }
                
                if (confirm('¿Estás seguro de que quieres eliminar esta sede?')) {
                    sedeItem.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Siempre debe haber al menos una sede, así que no mostrar mensaje de "no hay sedes"
                    });
                }
            });
            
            // Toggle expandir/contraer sede
            $(document).on('click', '.sede-toggle', function() {
                const sedeItem = $(this).closest('.sede-item');
                sedeItem.toggleClass('sede-collapsed');
                
                const isCollapsed = sedeItem.hasClass('sede-collapsed');
                $(this).text(isCollapsed ? '👁️ Mostrar' : '➖ Contraer');
            });
            
            // Actualizar título de sede cuando cambia el nombre
            $(document).on('input', '.sede-nombre', function() {
                const nombre = $(this).val() || 'Sede sin nombre';
                $(this).closest('.sede-item').find('.sede-title').text('🏢 ' + nombre);
            });
            
            // Manejar checkbox de sede principal
            $(document).on('change', '.sede-es-principal', function() {
                const sedeItem = $(this).closest('.sede-item');
                
                if ($(this).is(':checked')) {
                    // Desmarcar todas las otras sedes como principales
                    $('.sede-es-principal').not(this).prop('checked', false);
                    $('.sede-item').removeClass('sede-es-principal');
                    
                    // Marcar esta sede como principal
                    sedeItem.addClass('sede-es-principal');
                } else {
                    sedeItem.removeClass('sede-es-principal');
                }
            });
        });
        
        // Función global para importar sede desde JSON
        window.importarSedeDesdeJSON = function(sede, sedeIndex_import, button) {
            // Crear nueva sede con datos del JSON
            const template = $('#sede-template').html();
            let newSedeHtml = template.replace(/{{INDEX}}/g, sedeIndex);
            
            // Remover mensaje de "no sedes" si existe
            $('.no-sedes-message').remove();
            
            $('#sedes-list').append(newSedeHtml);
            
            // Rellenar campos con datos del JSON
            const newSedeElement = $('#sedes-list .sede-item:last');
            
            Object.keys(sede).forEach(function(key) {
                const input = newSedeElement.find(`[name*="[${key}]"]`);
                
                if (input.length) {
                    if (input.attr('type') === 'checkbox') {
                        input.prop('checked', !!sede[key]);
                    } else if (Array.isArray(sede[key])) {
                        // Para areas_practica
                        sede[key].forEach(function(area) {
                            newSedeElement.find(`input[value="${area}"]`).prop('checked', true);
                        });
                    } else {
                        input.val(sede[key]);
                    }
                }
            });
            
            // Actualizar título
            const nombreSede = sede.nombre || 'Sede importada';
            newSedeElement.find('.sede-title').text('🏢 ' + nombreSede);
            
            sedeIndex++;
            
            // Scroll a la nueva sede
            newSedeElement[0].scrollIntoView({ behavior: 'smooth' });
            
            // Deshabilitar botón de importar
            button.prop('disabled', true).text('✅ Importada');
        };
        
        // Funciones globales para manejo de fotos de perfil
        window.updatePhotoPreview = function(index, url) {
            const preview = document.getElementById('preview_foto_' + index);
            const defaultLogo = 'https://lexhoy.com/wp-content/uploads/2025/07/FOTO-DESPACHO-500X500.webp';
            
            if (url && url.trim() !== '') {
                // Verificar si la URL de imagen es válida
                const img = new Image();
                img.onload = function() {
                    preview.src = url;
                };
                img.onerror = function() {
                    preview.src = defaultLogo;
                    alert('No se pudo cargar la imagen. Se usará el logo por defecto.');
                };
                img.src = url;
            } else {
                preview.src = defaultLogo;
            }
        };
        
        window.useDefaultLogo = function(index) {
            const input = document.getElementById('sede_foto_perfil_' + index);
            const urlInput = document.getElementById('sede_foto_url_' + index);
            const fileInput = document.getElementById('sede_foto_upload_' + index);
            const preview = document.getElementById('preview_foto_' + index);
            const status = document.getElementById('upload_status_' + index);
            const defaultLogo = 'https://lexhoy.com/wp-content/uploads/2025/07/FOTO-DESPACHO-500X500.webp';
            
            input.value = defaultLogo;
            if (urlInput) urlInput.value = '';
            if (fileInput) fileInput.value = '';
            if (status) status.textContent = '';
            preview.src = defaultLogo;
        };
        
        window.clearPhoto = function(index) {
            const input = document.getElementById('sede_foto_perfil_' + index);
            const urlInput = document.getElementById('sede_foto_url_' + index);
            const fileInput = document.getElementById('sede_foto_upload_' + index);
            const preview = document.getElementById('preview_foto_' + index);
            const status = document.getElementById('upload_status_' + index);
            const defaultLogo = 'https://lexhoy.com/wp-content/uploads/2025/07/FOTO-DESPACHO-500X500.webp';
            
            input.value = '';
            if (urlInput) urlInput.value = '';
            if (fileInput) fileInput.value = '';
            if (status) status.textContent = '';
            preview.src = defaultLogo;
        };
        
        // Función para subir archivo de imagen
        window.uploadPhotoFile = function(index, fileInput) {
            const file = fileInput.files[0];
            const status = document.getElementById('upload_status_' + index);
            const preview = document.getElementById('preview_foto_' + index);
            const hiddenInput = document.getElementById('sede_foto_perfil_' + index);
            const urlInput = document.getElementById('sede_foto_url_' + index);
            
            if (!file) return;
            
            // Validar tamaño (2MB máximo)
            const maxSize = 2 * 1024 * 1024; // 2MB en bytes
            if (file.size > maxSize) {
                alert('❌ El archivo es demasiado grande. Máximo 2MB.');
                fileInput.value = '';
                return;
            }
            
            // Validar tipo de archivo
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('❌ Formato no válido. Solo se permiten JPG, PNG y WEBP.');
                fileInput.value = '';
                return;
            }
            
            status.textContent = '⏳ Subiendo...';
            status.style.color = '#ffc107';
            
            // Crear FormData para envío AJAX
            const formData = new FormData();
            formData.append('action', 'upload_sede_photo');
            formData.append('file', file);
            formData.append('nonce', '<?php echo wp_create_nonce("upload_sede_photo"); ?>');
            
            // Enviar archivo via AJAX
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Actualizar campos con la URL de la imagen subida
                    hiddenInput.value = data.data.url;
                    preview.src = data.data.url;
                    if (urlInput) urlInput.value = '';
                    
                    status.textContent = '✅ Subida exitosa';
                    status.style.color = '#28a745';
                    
                    setTimeout(() => {
                        status.textContent = '';
                    }, 3000);
                } else {
                    throw new Error(data.data || 'Error al subir la imagen');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                status.textContent = '❌ Error al subir';
                status.style.color = '#dc3545';
                fileInput.value = '';
                
                setTimeout(() => {
                    status.textContent = '';
                }, 5000);
            });
        };
        
        // Actualizar función updatePhotoPreview para manejar URL manual
        window.updatePhotoPreview = function(index, url) {
            const preview = document.getElementById('preview_foto_' + index);
            const hiddenInput = document.getElementById('sede_foto_perfil_' + index);
            const fileInput = document.getElementById('sede_foto_upload_' + index);
            const defaultLogo = 'https://lexhoy.com/wp-content/uploads/2025/07/FOTO-DESPACHO-500X500.webp';
            
            if (url && url.trim() !== '') {
                // Limpiar el input de archivo si se usa URL manual
                if (fileInput) fileInput.value = '';
                
                // Verificar si la URL de imagen es válida
                const img = new Image();
                img.onload = function() {
                    preview.src = url;
                    hiddenInput.value = url;
                };
                img.onerror = function() {
                    preview.src = defaultLogo;
                    hiddenInput.value = '';
                    alert('No se pudo cargar la imagen. Se usará el logo por defecto.');
                };
                img.src = url;
            } else {
                preview.src = defaultLogo;
                hiddenInput.value = '';
            }
        };
        </script>
        <?php
    }
    
    /**
     * Renderizar formulario individual de sede
     */
    private function render_sede_form($index, $sede = array(), $collapsed = false) {
        // Valores por defecto - TODOS LOS CAMPOS DEL JSON
        $defaults = array(
            // CAMPOS BÁSICOS
            'nombre' => '',
            'persona_contacto' => '',
            'email_contacto' => '',
            'telefono' => '',
            'direccion' => '',
            'calle' => '',
            'numero' => '',
            'piso' => '',
            'localidad' => '',
            'provincia' => '',
            'codigo_postal' => '',
            'pais' => 'España',
            'web' => '',
            'descripcion' => '',
            
            // CAMPOS PROFESIONALES
            'numero_colegiado' => '',
            'colegio' => '',
            'experiencia' => '',
            'ano_fundacion' => '',
            'tamano_despacho' => '',
            
            // ESPECIALIDADES Y ÁREAS
            'especialidades' => '',
            'areas_practica' => array(),
            'servicios_especificos' => '',
            'certificaciones' => '',
            
            // HORARIOS DETALLADOS
            'horario_lunes' => '',
            'horario_martes' => '',
            'horario_miercoles' => '',
            'horario_jueves' => '',
            'horario_viernes' => '',
            'horario_sabado' => '',
            'horario_domingo' => '',
            'horario_atencion' => '',
            
            // REDES SOCIALES
            'facebook' => '',
            'twitter' => '',
            'linkedin' => '',
            'instagram' => '',
            
            // ESTADO Y VERIFICACIÓN
            'estado_verificacion' => 'pendiente',
            'is_verified' => false,
            'estado_registro' => 'activo',
            'foto_perfil' => '',
            
            // CAMPOS DE SEDE
            'observaciones' => '',
            'es_principal' => false,
            'activa' => true
        );
        
        $sede = array_merge($defaults, $sede);
        $nombre_mostrar = !empty($sede['nombre']) ? $sede['nombre'] : 'Sede sin nombre';
        $es_principal_class = $sede['es_principal'] ? 'sede-es-principal' : '';
        $collapsed_class = $collapsed ? 'sede-collapsed' : '';
        $toggle_text = $collapsed ? '👁️ Mostrar' : '➖ Contraer';
        ?>
        
        <div class="sede-item <?php echo $es_principal_class; ?> <?php echo $collapsed_class; ?>">
            <div class="sede-header">
                <h5 class="sede-title">🏢 <?php echo esc_html($nombre_mostrar); ?></h5>
                <div class="sede-actions">
                    <button type="button" class="sede-toggle"><?php echo $toggle_text; ?></button>
                    <?php if ($index > 0): // Solo mostrar eliminar si no es la primera sede ?>
                        <button type="button" class="sede-remove">🗑️ Eliminar</button>
                    <?php else: ?>
                        <span style="color: #666; font-size: 12px; font-style: italic;">
                            📌 Sede Obligatoria
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="sede-content">
                <!-- Columna izquierda - INFORMACIÓN BÁSICA -->
                <div class="sede-column-left">
                    <h5 style="color: #1976d2; margin-bottom: 15px;">📋 Información Básica</h5>
                    
                    <div class="sede-field">
                        <label for="sede_nombre_<?php echo $index; ?>">🏢 Nombre de la Sede *</label>
                        <input type="text" 
                               id="sede_nombre_<?php echo $index; ?>" 
                               name="sedes[<?php echo $index; ?>][nombre]" 
                               value="<?php echo esc_attr($sede['nombre']); ?>"
                               class="sede-nombre"
                               placeholder="ej: Sede Central Madrid"
                               required>
                    </div>
                    
                    <div class="sede-field">
                        <label for="sede_descripcion_<?php echo $index; ?>">📝 Descripción</label>
                        <textarea id="sede_descripcion_<?php echo $index; ?>" 
                                  name="sedes[<?php echo $index; ?>][descripcion]" 
                                  rows="3"
                                  placeholder="Descripción de la sede y sus servicios"><?php echo esc_textarea($sede['descripcion']); ?></textarea>
                    </div>
                    
                    <div class="sede-field">
                        <label for="sede_web_<?php echo $index; ?>">🌐 Sitio Web</label>
                        <input type="url" 
                               id="sede_web_<?php echo $index; ?>" 
                               name="sedes[<?php echo $index; ?>][web]" 
                               value="<?php echo esc_attr($sede['web']); ?>"
                               placeholder="https://www.despacho.com">
                    </div>
                    
                    <div class="sede-field">
                        <label for="sede_ano_fundacion_<?php echo $index; ?>">📅 Año de Fundación</label>
                        <input type="number" 
                               id="sede_ano_fundacion_<?php echo $index; ?>" 
                               name="sedes[<?php echo $index; ?>][ano_fundacion]" 
                               value="<?php echo esc_attr($sede['ano_fundacion']); ?>"
                               min="1800" max="<?php echo date('Y'); ?>"
                               placeholder="2020">
                    </div>
                    
                    <div class="sede-field">
                        <label for="sede_tamano_<?php echo $index; ?>">👥 Tamaño del Despacho</label>
                        <select id="sede_tamano_<?php echo $index; ?>" name="sedes[<?php echo $index; ?>][tamano_despacho]">
                            <option value="">Seleccionar...</option>
                            <option value="Solo" <?php selected($sede['tamano_despacho'], 'Solo'); ?>>Solo (1 persona)</option>
                            <option value="Pequeño" <?php selected($sede['tamano_despacho'], 'Pequeño'); ?>>Pequeño (2-5 personas)</option>
                            <option value="Mediano" <?php selected($sede['tamano_despacho'], 'Mediano'); ?>>Mediano (6-15 personas)</option>
                            <option value="Grande" <?php selected($sede['tamano_despacho'], 'Grande'); ?>>Grande (16-50 personas)</option>
                            <option value="Muy Grande" <?php selected($sede['tamano_despacho'], 'Muy Grande'); ?>>Muy Grande (50+ personas)</option>
                        </select>
                    </div>
                    
                    <h5 style="color: #1976d2; margin: 20px 0 15px;">👤 Contacto Principal</h5>
                    
                    <div class="sede-field">
                        <label for="sede_persona_contacto_<?php echo $index; ?>">👤 Persona de Contacto *</label>
                        <input type="text" 
                               id="sede_persona_contacto_<?php echo $index; ?>" 
                               name="sedes[<?php echo $index; ?>][persona_contacto]" 
                               value="<?php echo esc_attr($sede['persona_contacto']); ?>"
                               placeholder="ej: Ana García López"
                               required>
                    </div>
                    
                    <div class="sede-field">
                        <label for="sede_email_<?php echo $index; ?>">📧 Email de Contacto *</label>
                        <input type="email" 
                               id="sede_email_<?php echo $index; ?>" 
                               name="sedes[<?php echo $index; ?>][email_contacto]" 
                               value="<?php echo esc_attr($sede['email_contacto']); ?>"
                               placeholder="ej: madrid@despacho.com"
                               required>
                    </div>
                    
                    <div class="sede-field">
                        <label for="sede_telefono_<?php echo $index; ?>">📞 Teléfono *</label>
                        <input type="tel" 
                               id="sede_telefono_<?php echo $index; ?>" 
                               name="sedes[<?php echo $index; ?>][telefono]" 
                               value="<?php echo esc_attr($sede['telefono']); ?>"
                               placeholder="ej: +34 91 123 45 67"
                               required>
                    </div>
                    
                    <h5 style="color: #1976d2; margin: 20px 0 15px;">⚖️ Información Profesional</h5>
                    
                    <div class="sede-field">
                        <label for="sede_numero_colegiado_<?php echo $index; ?>">🆔 Número de Colegiado</label>
                        <input type="text" 
                               id="sede_numero_colegiado_<?php echo $index; ?>" 
                               name="sedes[<?php echo $index; ?>][numero_colegiado]" 
                               value="<?php echo esc_attr($sede['numero_colegiado']); ?>"
                               placeholder="ej: 12345">
                    </div>
                    
                    <div class="sede-field">
                        <label for="sede_colegio_<?php echo $index; ?>">🏛️ Colegio de Abogados</label>
                        <input type="text" 
                               id="sede_colegio_<?php echo $index; ?>" 
                               name="sedes[<?php echo $index; ?>][colegio]" 
                               value="<?php echo esc_attr($sede['colegio']); ?>"
                               placeholder="ej: Colegio de Abogados de Madrid">
                    </div>
                    
                    <div class="sede-field">
                        <label for="sede_experiencia_<?php echo $index; ?>">💼 Experiencia</label>
                        <textarea id="sede_experiencia_<?php echo $index; ?>" 
                                  name="sedes[<?php echo $index; ?>][experiencia]" 
                                  rows="3"
                                  placeholder="Describe la experiencia profesional..."><?php echo esc_textarea($sede['experiencia']); ?></textarea>
                    </div>
                    
                    <!-- Redes Sociales -->
                    <h5 style="color: #1976d2; margin: 20px 0 15px;">🌐 Redes Sociales</h5>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="sede-field">
                            <label for="sede_facebook_<?php echo $index; ?>">📘 Facebook</label>
                            <input type="url" 
                                   id="sede_facebook_<?php echo $index; ?>" 
                                   name="sedes[<?php echo $index; ?>][facebook]" 
                                   value="<?php echo esc_attr($sede['facebook']); ?>"
                                   placeholder="https://facebook.com/tudespacho">
                        </div>
                        
                        <div class="sede-field">
                            <label for="sede_twitter_<?php echo $index; ?>">🐦 Twitter/X</label>
                            <input type="url" 
                                   id="sede_twitter_<?php echo $index; ?>" 
                                   name="sedes[<?php echo $index; ?>][twitter]" 
                                   value="<?php echo esc_attr($sede['twitter']); ?>"
                                   placeholder="https://twitter.com/tudespacho">
                        </div>
                        
                        <div class="sede-field">
                            <label for="sede_linkedin_<?php echo $index; ?>">💼 LinkedIn</label>
                            <input type="url" 
                                   id="sede_linkedin_<?php echo $index; ?>" 
                                   name="sedes[<?php echo $index; ?>][linkedin]" 
                                   value="<?php echo esc_attr($sede['linkedin']); ?>"
                                   placeholder="https://linkedin.com/company/tudespacho">
                        </div>
                        
                        <div class="sede-field">
                            <label for="sede_instagram_<?php echo $index; ?>">📷 Instagram</label>
                            <input type="url" 
                                   id="sede_instagram_<?php echo $index; ?>" 
                                   name="sedes[<?php echo $index; ?>][instagram]" 
                                   value="<?php echo esc_attr($sede['instagram']); ?>"
                                   placeholder="https://instagram.com/tudespacho">
                        </div>
                    </div>
                </div>
                
                <!-- Columna derecha - DIRECCIÓN Y ESPECIALIDADES -->
                <div class="sede-column-right">
                    <h5 style="color: #1976d2; margin-bottom: 15px;">📍 Dirección Completa</h5>
                    
                    <div class="sede-field">
                        <label for="sede_calle_<?php echo $index; ?>">🛣️ Calle *</label>
                        <input type="text" 
                               id="sede_calle_<?php echo $index; ?>" 
                               name="sedes[<?php echo $index; ?>][calle]" 
                               value="<?php echo esc_attr($sede['calle']); ?>"
                               placeholder="ej: Calle Mayor"
                               required>
                    </div>
                    
                    <div class="sede-field" style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px;">
                        <div>
                            <label for="sede_numero_<?php echo $index; ?>">🔢 Número *</label>
                            <input type="text" 
                                   id="sede_numero_<?php echo $index; ?>" 
                                   name="sedes[<?php echo $index; ?>][numero]" 
                                   value="<?php echo esc_attr($sede['numero']); ?>"
                                   placeholder="123"
                                   required>
                        </div>
                        <div>
                            <label for="sede_piso_<?php echo $index; ?>">🏠 Piso/Oficina</label>
                            <input type="text" 
                                   id="sede_piso_<?php echo $index; ?>" 
                                   name="sedes[<?php echo $index; ?>][piso]" 
                                   value="<?php echo esc_attr($sede['piso']); ?>"
                                   placeholder="3º A">
                        </div>
                    </div>
                    
                    <div class="sede-field" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label for="sede_localidad_<?php echo $index; ?>">🏙️ Localidad *</label>
                            <input type="text" 
                                   id="sede_localidad_<?php echo $index; ?>" 
                                   name="sedes[<?php echo $index; ?>][localidad]" 
                                   value="<?php echo esc_attr($sede['localidad']); ?>"
                                   placeholder="Madrid"
                                   required>
                        </div>
                        <div>
                            <label for="sede_provincia_<?php echo $index; ?>">🗺️ Provincia *</label>
                            <input type="text" 
                                   id="sede_provincia_<?php echo $index; ?>" 
                                   name="sedes[<?php echo $index; ?>][provincia]" 
                                   value="<?php echo esc_attr($sede['provincia']); ?>"
                                   placeholder="Madrid"
                                   required>
                        </div>
                    </div>
                    
                    <div class="sede-field" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label for="sede_cp_<?php echo $index; ?>">📮 Código Postal</label>
                            <input type="text" 
                                   id="sede_cp_<?php echo $index; ?>" 
                                   name="sedes[<?php echo $index; ?>][codigo_postal]" 
                                   value="<?php echo esc_attr($sede['codigo_postal']); ?>"
                                   placeholder="28001">
                        </div>
                        <div>
                            <label for="sede_pais_<?php echo $index; ?>">🌍 País</label>
                            <input type="text" 
                                   id="sede_pais_<?php echo $index; ?>" 
                                   name="sedes[<?php echo $index; ?>][pais]" 
                                   value="<?php echo esc_attr($sede['pais']); ?>"
                                   placeholder="España">
                        </div>
                    </div>
                    
                    <h5 style="color: #1976d2; margin: 20px 0 15px;">⚖️ Especialidades y Servicios</h5>
                    
                    <div class="sede-field">
                        <label for="sede_especialidades_<?php echo $index; ?>">🎯 Especialidades Específicas (separadas por coma)</label>
                        <input type="text" 
                               id="sede_especialidades_<?php echo $index; ?>" 
                               name="sedes[<?php echo $index; ?>][especialidades]" 
                               value="<?php echo esc_attr($sede['especialidades']); ?>"
                               placeholder="ej: Mediación familiar, Arbitraje comercial, Derecho marítimo">
                    </div>
                    
                    <div class="sede-field">
                        <label for="sede_servicios_<?php echo $index; ?>">⚖️ Servicios Específicos</label>
                        <textarea id="sede_servicios_<?php echo $index; ?>" 
                                  name="sedes[<?php echo $index; ?>][servicios_especificos]" 
                                  rows="3"
                                  placeholder="ej: Derecho mercantil, Asesoría fiscal, Representación judicial"><?php echo esc_textarea($sede['servicios_especificos']); ?></textarea>
                    </div>
                    
                    <div class="sede-field">
                        <label for="sede_certificaciones_<?php echo $index; ?>">🏆 Certificaciones</label>
                        <textarea id="sede_certificaciones_<?php echo $index; ?>" 
                                  name="sedes[<?php echo $index; ?>][certificaciones]" 
                                  rows="2"
                                  placeholder="ej: ISO 9001, Certificación en mediación"><?php echo esc_textarea($sede['certificaciones']); ?></textarea>
                    </div>
                    
                    <!-- Áreas de Práctica con Checkboxes -->
                    <div class="areas-practica-container">
                        <h5 style="color: #1976d2; margin-bottom: 10px;">⚖️ Áreas de Práctica</h5>
                        <p style="font-size: 13px; color: #6c757d; margin-bottom: 15px;">
                            Selecciona las áreas en las que opera esta sede:
                        </p>
                        
                        <div class="areas-practica-grid">
                            <?php
                            $areas_disponibles = array(
                                'Administrativo' => 'Derecho Administrativo',
                                'Civil' => 'Derecho Civil',
                                'Fiscal' => 'Derecho Fiscal',
                                'Laboral' => 'Derecho Laboral',
                                'Mercantil' => 'Derecho Mercantil',
                                'Penal' => 'Derecho Penal',
                                'Familia' => 'Derecho de Familia',
                                'Sucesiones' => 'Sucesiones',
                                'Vivienda' => 'Derecho de Vivienda',
                                'Bancario' => 'Derecho Bancario',
                                'Inmobiliario' => 'Derecho Inmobiliario',
                                'Consumo' => 'Derecho del Consumo',
                                'Empresarial' => 'Derecho Empresarial',
                                'Seguros' => 'Derecho de Seguros',
                                'Medio Ambiente' => 'Derecho Medio Ambiente'
                            );
                            
                            $areas_seleccionadas = is_array($sede['areas_practica']) ? $sede['areas_practica'] : array();
                            
                            foreach ($areas_disponibles as $valor => $etiqueta): ?>
                                <div class="area-practica-item">
                                    <input type="checkbox" 
                                           id="area_<?php echo $index; ?>_<?php echo sanitize_title($valor); ?>" 
                                           name="sedes[<?php echo $index; ?>][areas_practica][]" 
                                           value="<?php echo esc_attr($valor); ?>"
                                           <?php checked(in_array($valor, $areas_seleccionadas)); ?>>
                                    <label for="area_<?php echo $index; ?>_<?php echo sanitize_title($valor); ?>">
                                        <?php echo esc_html($etiqueta); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <h5 style="color: #1976d2; margin: 20px 0 15px;">🕒 Horarios Detallados</h5>
                    
                    <div class="sede-field">
                        <label for="sede_horario_general_<?php echo $index; ?>">🕒 Horario General</label>
                        <input type="text" 
                               id="sede_horario_general_<?php echo $index; ?>" 
                               name="sedes[<?php echo $index; ?>][horario_atencion]" 
                               value="<?php echo esc_attr($sede['horario_atencion']); ?>"
                               placeholder="ej: L-V: 9:00-18:00, S: 9:00-14:00">
                    </div>
                    
                    <div class="horarios-detalle" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                        <?php
                        $dias = array(
                            'horario_lunes' => 'Lunes',
                            'horario_martes' => 'Martes', 
                            'horario_miercoles' => 'Miércoles',
                            'horario_jueves' => 'Jueves',
                            'horario_viernes' => 'Viernes',
                            'horario_sabado' => 'Sábado',
                            'horario_domingo' => 'Domingo'
                        );
                        
                        foreach ($dias as $campo => $dia): ?>
                            <div class="sede-field-small">
                                <label for="sede_<?php echo $campo; ?>_<?php echo $index; ?>" style="font-size: 12px;"><?php echo $dia; ?></label>
                                <input type="text" 
                                       id="sede_<?php echo $campo; ?>_<?php echo $index; ?>" 
                                       name="sedes[<?php echo $index; ?>][<?php echo $campo; ?>]" 
                                       value="<?php echo esc_attr($sede[$campo]); ?>"
                                       placeholder="9:00-18:00"
                                       style="font-size: 12px; padding: 3px;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    

                </div>
                
                <!-- Fila completa para campos adicionales -->
                <div class="sede-field" style="grid-column: 1 / -1;">
                    <h5 style="color: #1976d2; margin: 20px 0 15px;">📄 Información Adicional</h5>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label for="sede_estado_verificacion_<?php echo $index; ?>">✅ Estado de Verificación</label>
                            <select id="sede_estado_verificacion_<?php echo $index; ?>" name="sedes[<?php echo $index; ?>][estado_verificacion]">
                                <option value="pendiente" <?php selected($sede['estado_verificacion'], 'pendiente'); ?>>⏳ Pendiente verificación</option>
                                <option value="verificado" <?php selected($sede['estado_verificacion'], 'verificado'); ?>>✅ Verificado</option>
                                <option value="rechazado" <?php selected($sede['estado_verificacion'], 'rechazado'); ?>>❌ Rechazado</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="sede_estado_registro_<?php echo $index; ?>">📋 Estado de Registro</label>
                            <select id="sede_estado_registro_<?php echo $index; ?>" name="sedes[<?php echo $index; ?>][estado_registro]">
                                <option value="activo" <?php selected($sede['estado_registro'], 'activo'); ?>>✅ Activo</option>
                                <option value="inactivo" <?php selected($sede['estado_registro'], 'inactivo'); ?>>❌ Inactivo</option>
                                <option value="suspendido" <?php selected($sede['estado_registro'], 'suspendido'); ?>>⏸️ Suspendido</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label for="sede_foto_perfil_<?php echo $index; ?>">📸 Foto de Perfil</label>
                        
                        <!-- Vista previa de la imagen -->
                        <div class="foto-perfil-preview" style="margin: 10px 0;">
                            <?php 
                            $foto_actual = !empty($sede['foto_perfil']) ? $sede['foto_perfil'] : 'https://lexhoy.com/wp-content/uploads/2025/07/FOTO-DESPACHO-500X500.webp';
                            ?>
                            <img id="preview_foto_<?php echo $index; ?>" 
                                 src="<?php echo esc_url($foto_actual); ?>" 
                                 style="width: 100px; height: 100px; object-fit: cover; border: 2px solid #ddd; border-radius: 8px; display: block; margin-bottom: 10px;"
                                 alt="Foto de perfil">
                        </div>
                        
                        <!-- Campo oculto para la URL final -->
                        <input type="hidden" 
                               id="sede_foto_perfil_<?php echo $index; ?>" 
                               name="sedes[<?php echo $index; ?>][foto_perfil]" 
                               value="<?php echo esc_attr($sede['foto_perfil']); ?>">
                        
                        <!-- Input de archivo para subida -->
                        <div style="margin-bottom: 10px;">
                            <input type="file" 
                                   id="sede_foto_upload_<?php echo $index; ?>" 
                                   accept="image/*"
                                   style="margin-bottom: 5px;"
                                   onchange="uploadPhotoFile(<?php echo $index; ?>, this)">
                            <small style="display: block; color: #666; margin-bottom: 5px;">
                                🔺 Máximo 2MB - Formatos: JPG, PNG, WEBP
                            </small>
                        </div>
                        
                        <!-- Campo manual para URL externa -->
                        <div style="margin-bottom: 10px;">
                            <input type="url" 
                                   id="sede_foto_url_<?php echo $index; ?>" 
                                   placeholder="O introduce URL externa: https://ejemplo.com/foto.jpg"
                                   style="width: 100%;"
                                   onchange="updatePhotoPreview(<?php echo $index; ?>, this.value)">
                        </div>
                        
                        <!-- Botones de control -->
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <button type="button" 
                                    class="button button-secondary" 
                                    onclick="useDefaultLogo(<?php echo $index; ?>)"
                                    style="font-size: 12px;">
                                🔄 Foto por Defecto
                            </button>
                            
                            <button type="button" 
                                    class="button button-secondary" 
                                    onclick="clearPhoto(<?php echo $index; ?>)"
                                    style="font-size: 12px;">
                                🗑️ Limpiar
                            </button>
                            
                            <span id="upload_status_<?php echo $index; ?>" style="font-size: 12px; color: #28a745;"></span>
                        </div>
                        
                        <small style="color: #666; display: block; margin-top: 5px;">
                            💡 Puedes subir un archivo o usar una URL externa. Se usará la foto por defecto si no especificas ninguna.
                        </small>
                    </div>
                    
                    <label for="sede_observaciones_<?php echo $index; ?>">📝 Observaciones y Notas Internas</label>
                    <textarea id="sede_observaciones_<?php echo $index; ?>" 
                              name="sedes[<?php echo $index; ?>][observaciones]" 
                              rows="3"
                              placeholder="Información adicional sobre esta sede, notas internas, comentarios especiales..."><?php echo esc_textarea($sede['observaciones']); ?></textarea>
                </div>
                
                <!-- Configuración de la Sede -->
                <div class="sede-field" style="grid-column: 1 / -1;">
                    <div class="configuracion-sede">
                        <h5 style="color: #1976d2; margin-bottom: 15px;">⚙️ Configuración de la Sede</h5>
                        
                        <div class="configuracion-grid">
                            <div class="configuracion-item">
                                <input type="checkbox" 
                                       id="sede_principal_<?php echo $index; ?>"
                                       name="sedes[<?php echo $index; ?>][es_principal]" 
                                       value="1" 
                                       class="sede-es-principal"
                                       <?php checked($sede['es_principal'], true); ?>>
                                <label for="sede_principal_<?php echo $index; ?>" class="config-label config-principal">
                                    ⭐ Sede Principal
                                </label>
                            </div>
                            
                            <div class="configuracion-item">
                                <input type="checkbox" 
                                       id="sede_activa_<?php echo $index; ?>"
                                       name="sedes[<?php echo $index; ?>][activa]" 
                                       value="1" 
                                       <?php checked($sede['activa'], true); ?>>
                                <label for="sede_activa_<?php echo $index; ?>" class="config-label config-activa">
                                    ✅ Sede Activa
                                </label>
                            </div>
                            
                            <div class="configuracion-item">
                                <input type="checkbox" 
                                       id="sede_verificada_<?php echo $index; ?>"
                                       name="sedes[<?php echo $index; ?>][is_verified]" 
                                       value="1" 
                                       <?php checked($sede['is_verified'], true); ?>>
                                <label for="sede_verificada_<?php echo $index; ?>" class="config-label config-verificada">
                                    🔒 Verificada
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;">
                        <small style="color: #856404;">
                            <strong>💡 Nota:</strong> 
                            • Solo puede haber una sede principal por despacho<br>
                            • Las sedes inactivas no se mostrarán en el frontend<br>
                            • La verificación afecta la visibilidad de los datos de contacto
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Guardar datos de sedes
     */
    public function save_sedes_data($post_id) {
        // Verificar nonce
        if (!isset($_POST['despacho_sedes_nonce']) || !wp_verify_nonce($_POST['despacho_sedes_nonce'], 'despacho_sedes_meta_box')) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // No guardar en autoguardado
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Solo para el post type despacho
        if (get_post_type($post_id) !== 'despacho') {
            return;
        }
        
        // Procesar sedes
        $sedes_data = array();
        
        if (isset($_POST['sedes']) && is_array($_POST['sedes'])) {
            foreach ($_POST['sedes'] as $index => $sede) {
                // Validar campos obligatorios
                if (empty($sede['nombre']) || empty($sede['persona_contacto']) || 
                    empty($sede['email_contacto']) || empty($sede['telefono']) ||
                    empty($sede['calle']) || empty($sede['numero']) ||
                    empty($sede['localidad']) || empty($sede['provincia'])) {
                    continue; // Saltar sedes incompletas
                }
                
                $sede_limpia = array(
                    // CAMPOS BÁSICOS
                    'nombre' => sanitize_text_field($sede['nombre']),
                    'descripcion' => sanitize_textarea_field($sede['descripcion'] ?? ''),
                    'web' => esc_url_raw($sede['web'] ?? ''),
                    'ano_fundacion' => intval($sede['ano_fundacion'] ?? 0),
                    'tamano_despacho' => sanitize_text_field($sede['tamano_despacho'] ?? ''),
                    
                    // CONTACTO
                    'persona_contacto' => sanitize_text_field($sede['persona_contacto']),
                    'email_contacto' => sanitize_email($sede['email_contacto']),
                    'telefono' => sanitize_text_field($sede['telefono']),
                    
                    // INFORMACIÓN PROFESIONAL
                    'numero_colegiado' => sanitize_text_field($sede['numero_colegiado'] ?? ''),
                    'colegio' => sanitize_text_field($sede['colegio'] ?? ''),
                    'experiencia' => sanitize_textarea_field($sede['experiencia'] ?? ''),
                    
                    // DIRECCIÓN
                    'calle' => sanitize_text_field($sede['calle']),
                    'numero' => sanitize_text_field($sede['numero']),
                    'piso' => sanitize_text_field($sede['piso'] ?? ''),
                    'localidad' => sanitize_text_field($sede['localidad']),
                    'provincia' => sanitize_text_field($sede['provincia']),
                    'codigo_postal' => sanitize_text_field($sede['codigo_postal'] ?? ''),
                    'pais' => sanitize_text_field($sede['pais'] ?? 'España'),
                    
                    // ESPECIALIDADES Y SERVICIOS
                    'especialidades' => sanitize_text_field($sede['especialidades'] ?? ''),
                    'areas_practica' => isset($sede['areas_practica']) && is_array($sede['areas_practica']) 
                                       ? array_map('sanitize_text_field', $sede['areas_practica']) 
                                       : array(),
                    'servicios_especificos' => sanitize_textarea_field($sede['servicios_especificos'] ?? ''),
                    'certificaciones' => sanitize_textarea_field($sede['certificaciones'] ?? ''),
                    
                    // HORARIOS
                    'horario_atencion' => sanitize_text_field($sede['horario_atencion'] ?? ''),
                    'horario_lunes' => sanitize_text_field($sede['horario_lunes'] ?? ''),
                    'horario_martes' => sanitize_text_field($sede['horario_martes'] ?? ''),
                    'horario_miercoles' => sanitize_text_field($sede['horario_miercoles'] ?? ''),
                    'horario_jueves' => sanitize_text_field($sede['horario_jueves'] ?? ''),
                    'horario_viernes' => sanitize_text_field($sede['horario_viernes'] ?? ''),
                    'horario_sabado' => sanitize_text_field($sede['horario_sabado'] ?? ''),
                    'horario_domingo' => sanitize_text_field($sede['horario_domingo'] ?? ''),
                    
                    // REDES SOCIALES
                    'facebook' => esc_url_raw($sede['facebook'] ?? ''),
                    'twitter' => esc_url_raw($sede['twitter'] ?? ''),
                    'linkedin' => esc_url_raw($sede['linkedin'] ?? ''),
                    'instagram' => esc_url_raw($sede['instagram'] ?? ''),
                    
                    // ESTADOS Y VERIFICACIÓN
                    'estado_verificacion' => sanitize_text_field($sede['estado_verificacion'] ?? 'pendiente'),
                    'estado_registro' => sanitize_text_field($sede['estado_registro'] ?? 'activo'),
                    'foto_perfil' => esc_url_raw($sede['foto_perfil'] ?? ''),
                    'is_verified' => isset($sede['is_verified']) && $sede['is_verified'] === '1',
                    
                    // CONFIGURACIÓN DE SEDE
                    'observaciones' => sanitize_textarea_field($sede['observaciones'] ?? ''),
                    'es_principal' => isset($sede['es_principal']) && $sede['es_principal'] === '1',
                    'activa' => isset($sede['activa']) && $sede['activa'] === '1',
                    
                    // METADATOS
                    'fecha_creacion' => current_time('mysql'),
                    'fecha_actualizacion' => current_time('mysql')
                );
                
                // Generar dirección completa
                $direccion_completa = $sede_limpia['calle'] . ' ' . $sede_limpia['numero'];
                if (!empty($sede_limpia['piso'])) {
                    $direccion_completa .= ', ' . $sede_limpia['piso'];
                }
                $direccion_completa .= ', ' . $sede_limpia['localidad'];
                if (!empty($sede_limpia['codigo_postal'])) {
                    $direccion_completa .= ' (' . $sede_limpia['codigo_postal'] . ')';
                }
                $direccion_completa .= ', ' . $sede_limpia['provincia'] . ', ' . $sede_limpia['pais'];
                
                $sede_limpia['direccion_completa'] = $direccion_completa;
                
                $sedes_data[] = $sede_limpia;
            }
        }
        
        // Asegurar que solo hay una sede principal
        $principal_encontrada = false;
        foreach ($sedes_data as &$sede) {
            if ($sede['es_principal'] && !$principal_encontrada) {
                $principal_encontrada = true;
            } elseif ($sede['es_principal'] && $principal_encontrada) {
                $sede['es_principal'] = false;
            }
        }
        
        // Si no hay sede principal pero hay sedes, marcar la primera como principal
        if (!$principal_encontrada && !empty($sedes_data)) {
            $sedes_data[0]['es_principal'] = true;
        }
        
        // Guardar sedes
        update_post_meta($post_id, '_despacho_sedes', $sedes_data);
        
        // Actualizar conteo de sedes
        update_post_meta($post_id, '_despacho_num_sedes', count($sedes_data));
    }
    
    /**
     * Enqueue assets para admin
     */
    public function enqueue_admin_assets($hook) {
        global $post_type;
        
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            if ($post_type === 'despacho') {
                wp_enqueue_script('jquery');
            }
        }
    }
    
    /**
     * Obtener sedes de un despacho
     */
    public static function get_sedes($despacho_id) {
        $sedes = get_post_meta($despacho_id, '_despacho_sedes', true);
        return is_array($sedes) ? $sedes : array();
    }
    
    /**
     * Obtener sede principal de un despacho
     */
    public static function get_sede_principal($despacho_id) {
        $sedes = self::get_sedes($despacho_id);
        
        foreach ($sedes as $sede) {
            if ($sede['es_principal'] && $sede['activa']) {
                return $sede;
            }
        }
        
        // Si no hay sede principal pero hay sedes activas, devolver la primera
        foreach ($sedes as $sede) {
            if ($sede['activa']) {
                return $sede;
            }
        }
        
        return null;
    }
    
    /**
     * Obtener sedes activas de un despacho
     */
    public static function get_sedes_activas($despacho_id) {
        $sedes = self::get_sedes($despacho_id);
        return array_filter($sedes, function($sede) {
            return $sede['activa'];
        });
    }
    
    /**
     * AJAX: Añadir sede
     */
    public function ajax_add_sede() {
        check_ajax_referer('lexhoy_sedes_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        if (!current_user_can('edit_post', $post_id)) {
            wp_die('Sin permisos');
        }
        
        // Lógica para añadir sede...
        wp_send_json_success(array('message' => 'Sede añadida'));
    }
    
    /**
     * AJAX: Remover sede
     */
    public function ajax_remove_sede() {
        check_ajax_referer('lexhoy_sedes_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $sede_index = intval($_POST['sede_index']);
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_die('Sin permisos');
        }
        
        // Lógica para remover sede...
        wp_send_json_success(array('message' => 'Sede eliminada'));
    }
    
    /**
     * AJAX: Actualizar sede
     */
    public function ajax_update_sede() {
        check_ajax_referer('lexhoy_sedes_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        if (!current_user_can('edit_post', $post_id)) {
            wp_die('Sin permisos');
        }
        
        // Lógica para actualizar sede...
        wp_send_json_success(array('message' => 'Sede actualizada'));
    }

    /**
     * AJAX: Subir foto de sede
     */
    public function ajax_upload_sede_photo() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'upload_sede_photo')) {
            wp_send_json_error('Nonce inválido');
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Sin permisos para subir archivos');
            return;
        }
        
        // Verificar que se envió un archivo
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('No se recibió ningún archivo válido');
            return;
        }
        
        $file = $_FILES['file'];
        
        // Validar tamaño (2MB máximo)
        $max_size = 2 * 1024 * 1024; // 2MB
        if ($file['size'] > $max_size) {
            wp_send_json_error('El archivo es demasiado grande. Máximo 2MB.');
            return;
        }
        
        // Validar tipo de archivo
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/webp');
        $file_type = wp_check_filetype($file['name']);
        
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error('Formato no válido. Solo se permiten JPG, PNG y WEBP.');
            return;
        }
        
        // Configurar el upload
        $upload_overrides = array(
            'test_form' => false,
            'test_size' => true,
            'test_upload' => true
        );
        
        // Realizar el upload usando WordPress
        $uploaded_file = wp_handle_upload($file, $upload_overrides);
        
        if (isset($uploaded_file['error'])) {
            wp_send_json_error('Error al subir: ' . $uploaded_file['error']);
            return;
        }
        
        // Si todo va bien, devolver la URL del archivo
        wp_send_json_success(array(
            'url' => $uploaded_file['url'],
            'file' => $uploaded_file['file']
        ));
    }

    /**
     * Cache estático para evitar recargar el JSON en la misma petición
     */
    private static $json_cache = null;
    private static $json_cache_file = null;

    /**
     * Obtener sedes desde el JSON basado en el nombre del despacho - OPTIMIZADO
     */
    private function get_sedes_from_json($despacho_nombre) {
        // Función deshabilitada - ya no usamos archivos JSON
        // Usamos Algolia como fuente principal de datos
        return array();
    }
    
    /**
     * AJAX: Cargar sedes desde JSON de forma asíncrona
     */
    public function ajax_cargar_sedes_json() {
        // Verificar permisos
        if (!current_user_can('edit_posts')) {
            wp_die('Sin permisos');
        }
        
        $despacho_nombre = sanitize_text_field($_POST['despacho_nombre'] ?? '');
        
        if (empty($despacho_nombre)) {
            wp_send_json_error('Nombre de despacho requerido');
        }
        
        // Buscar sedes
        $sedes_json = $this->get_sedes_from_json($despacho_nombre);
        
        if (empty($sedes_json)) {
            wp_send_json_success(array(
                'html' => '<p style="text-align: center; color: #666; padding: 20px;">No se encontraron sedes para este despacho en el JSON.</p>',
                'count' => 0
            ));
        }
        
        // Generar HTML para las sedes encontradas
        ob_start();
        ?>
        <div class="sedes-json-grid">
            <?php foreach ($sedes_json as $index => $sede_json): ?>
                <div class="sede-json-card">
                    <div class="sede-json-header">
                        <h4><?php echo esc_html($sede_json['nombre'] ?? 'Sede sin nombre'); ?></h4>
                        <div class="sede-json-badges">
                            <?php if (!empty($sede_json['es_principal'])): ?>
                                <span class="badge badge-principal">⭐ Principal</span>
                            <?php endif; ?>
                            <?php if (!empty($sede_json['activa'])): ?>
                                <span class="badge badge-activa">✅ Activa</span>
                            <?php endif; ?>
                            <?php if (!empty($sede_json['is_verified'])): ?>
                                <span class="badge badge-verificada">🔒 Verificada</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="sede-json-content">
                        <?php if (!empty($sede_json['persona_contacto'])): ?>
                            <p><strong>👤 Contacto:</strong> <?php echo esc_html($sede_json['persona_contacto']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($sede_json['telefono'])): ?>
                            <p><strong>📞 Teléfono:</strong> <?php echo esc_html($sede_json['telefono']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($sede_json['email_contacto'])): ?>
                            <p><strong>📧 Email:</strong> <?php echo esc_html($sede_json['email_contacto']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($sede_json['localidad']) || !empty($sede_json['provincia'])): ?>
                            <p><strong>📍 Ubicación:</strong> 
                                <?php echo esc_html(trim($sede_json['localidad'] . ', ' . $sede_json['provincia'], ', ')); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($sede_json['areas_practica']) && is_array($sede_json['areas_practica'])): ?>
                            <p><strong>⚖️ Áreas:</strong> 
                                <?php echo esc_html(implode(', ', $sede_json['areas_practica'])); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="sede-json-actions">
                        <button type="button" class="button button-secondary import-sede-btn" 
                                data-sede-index="<?php echo $index; ?>">
                            📥 Importar a WordPress
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <script>
        // Actualizar variable global con los datos de las sedes
        window.sedesJsonData = <?php echo json_encode($sedes_json); ?>;
        
        // Re-bind import buttons
        jQuery('.import-sede-btn').off('click').on('click', function() {
            const sedeIndex_import = jQuery(this).data('sede-index');
            const sede = window.sedesJsonData[sedeIndex_import];
            
            if (sede) {
                // Lógica de importación... (similar a la existente)
                importarSedeDesdeJSON(sede, sedeIndex_import, jQuery(this));
            }
        });
        </script>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'count' => count($sedes_json)
        ));
    }
} 
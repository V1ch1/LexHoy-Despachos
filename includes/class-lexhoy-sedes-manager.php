<?php
/**
 * Gestor de Sedes para Despachos
 */

if (!defined('ABSPATH')) {
    exit;
}

class LexhoySedesManager {
    
    private $algolia_client;
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_sedes'));
        add_action('wp_ajax_add_sede', array($this, 'ajax_add_sede'));
        add_action('wp_ajax_remove_sede', array($this, 'ajax_remove_sede'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Cargar scripts necesarios
     */
    public function enqueue_scripts($hook) {
        global $post;
        
        if ($hook == 'post.php' && $post->post_type == 'despacho') {
            wp_enqueue_script('jquery');
            
            // Cargar media uploader
            wp_enqueue_media();
            wp_enqueue_script('wp-media');
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
        
        // Cargar sedes desde Algolia (con manejo de errores)
        $sedes_json = array();
        try {
            $sedes_json = $this->get_sedes_from_algolia($post->ID);
        } catch (Exception $e) {
            // Si hay error, continuar sin datos de Algolia
            error_log('Error cargando sedes desde Algolia: ' . $e->getMessage());
            $sedes_json = array();
        }
        
        // Nonce para seguridad
        wp_nonce_field('despacho_sedes_meta_box', 'despacho_sedes_nonce');
        ?>
        
        <div id="sedes-container">
            <div class="sedes-wp-section">
                <div class="sedes-header">
                    <h3 style="color: #2563eb; margin-bottom: 15px;">
                        🏗️ Sedes Gestionadas en WordPress
                    </h3>
                    
                    <div id="sedes-list">
                        <?php if (!empty($sedes_wp)): ?>
                            <?php foreach ($sedes_wp as $index => $sede): ?>
                                <?php $this->render_sede_form($index, $sede, false); ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php 
                            // Si no hay sedes en WordPress, crear la primera sede con datos del JSON
                            $sede_inicial = array('es_principal' => true, 'activa' => true);
                            
                            // Priorizar datos del JSON si existen
                            if (!empty($sedes_json)) {
                                // Usar la primera sede del JSON (generalmente la principal)
                                $sede_json = $sedes_json[0];
                                
                                // Mapear datos del JSON a estructura de formulario
                                $sede_inicial = array_merge($sede_inicial, array(
                                    'nombre' => $sede_json['nombre'] ?? '',
                                    'descripcion' => $sede_json['descripcion'] ?? '',
                                    'web' => $sede_json['web'] ?? '',
                                    'ano_fundacion' => $sede_json['ano_fundacion'] ?? '',
                                    'tamano_despacho' => $sede_json['tamano_despacho'] ?? '',
                                    'persona_contacto' => $sede_json['persona_contacto'] ?? '',
                                    'email_contacto' => $sede_json['email_contacto'] ?? '',
                                    'telefono' => $sede_json['telefono'] ?? '',
                                    'numero_colegiado' => $sede_json['numero_colegiado'] ?? '',
                                    'colegio' => $sede_json['colegio'] ?? '',
                                    'experiencia' => $sede_json['experiencia'] ?? '',
                                    'calle' => $sede_json['calle'] ?? '',
                                    'numero' => $sede_json['numero'] ?? '',
                                    'piso' => $sede_json['piso'] ?? '',
                                    'localidad' => $sede_json['localidad'] ?? '',
                                    'provincia' => $sede_json['provincia'] ?? '',
                                    'codigo_postal' => $sede_json['codigo_postal'] ?? '',
                                    'pais' => $sede_json['pais'] ?? 'España',
                                    'especialidades' => $sede_json['especialidades'] ?? '',
                                    'areas_practica' => $sede_json['areas_practica'] ?? array(),
                                    'servicios_especificos' => $sede_json['servicios_especificos'] ?? '',
                                    'estado_verificacion' => $sede_json['estado_verificacion'] ?? 'pendiente',
                                    'estado_registro' => $sede_json['estado_registro'] ?? 'activo',
                                    'foto_perfil' => $sede_json['foto_perfil'] ?? '',
                                    'is_verified' => (($sede_json['estado_verificacion'] ?? 'pendiente') === 'verificado') ? true : false,
                                    'observaciones' => $sede_json['observaciones'] ?? '',
                                    'horarios' => $sede_json['horarios'] ?? array(),
                                    'redes_sociales' => $sede_json['redes_sociales'] ?? array(),
                                    'es_principal' => $sede_json['es_principal'] ?? true,
                                    'activa' => $sede_json['activa'] ?? true
                                ));
                            }
                            
                            $this->render_sede_form(0, $sede_inicial, false);
                            ?>
                        <?php endif; ?>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <button type="button" id="add-sede-btn" class="button button-secondary">
                            ➕ Añadir Nueva Sede
                        </button>
                        
                        <?php if (!empty($sedes_json) && count($sedes_json) > count($sedes_wp)): ?>
                            <button type="button" id="load-algolia-sedes" class="button button-primary" style="margin-left: 10px;">
                                🔄 Cargar Sedes Restantes desde Algolia (<?php echo count($sedes_json) - count($sedes_wp); ?>)
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($sedes_json)): ?>
            <div class="sedes-info-section" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #28a745; border-radius: 4px;">
                <h4 style="color: #28a745; margin-bottom: 10px;">📊 Información desde Algolia</h4>
                <p><strong>Total sedes en Algolia:</strong> <?php echo count($sedes_json); ?></p>
                <details style="margin-top: 10px;">
                    <summary style="cursor: pointer; color: #0073aa;">Ver detalles de sedes en Algolia</summary>
                    <div style="margin-top: 10px;">
                        <?php foreach ($sedes_json as $i => $sede): ?>
                            <div style="border: 1px solid #ddd; padding: 10px; margin: 5px 0; background: white;">
                                <strong><?php echo esc_html($sede['nombre'] ?? 'Sede sin nombre'); ?></strong>
                                <?php if (!empty($sede['localidad'])): ?>
                                    - <?php echo esc_html($sede['localidad']); ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            </div>
        <?php endif; ?>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var sedeIndex = <?php echo max(count($sedes_wp), 1); ?>;
            
            // Funcionalidad existente
            $('#add-sede-btn').click(function() {
                $.post(ajaxurl, {
                    action: 'add_sede',
                    sede_index: sedeIndex
                }, function(response) {
                    $('#sedes-list').append(response);
                    sedeIndex++;
                });
            });
            
            $(document).on('click', '.remove-sede', function() {
                if (confirm('¿Estás seguro de que quieres eliminar esta sede?')) {
                    $(this).closest('.sede-form').remove();
                }
            });
            
            $(document).on('click', '.toggle-sede', function() {
                $(this).closest('.sede-form').find('.sede-content').slideToggle();
            });
            
            // Cargar sedes restantes desde Algolia
            $('#load-algolia-sedes').click(function() {
                var sedesJson = <?php echo json_encode($sedes_json); ?>;
                var currentCount = <?php echo count($sedes_wp); ?>;
                
                for (var i = currentCount; i < sedesJson.length; i++) {
                    var sede = sedesJson[i];
                    $.post(ajaxurl, {
                        action: 'add_sede',
                        sede_index: sedeIndex,
                        sede_data: sede
                    }, function(response) {
                        $('#sedes-list').append(response);
                        sedeIndex++;
                    });
                }
                
                $(this).hide();
            });
            
            // NUEVO: Media Uploader para fotos
            var mediaUploader;
            
            $(document).on('click', '.upload-foto-btn', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var targetInput = button.data('target');
                var previewContainer = button.data('preview');
                
                // Si ya existe el uploader, abrirlo
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                // Crear el media uploader
                mediaUploader = wp.media({
                    title: 'Seleccionar Foto de Perfil',
                    button: {
                        text: 'Usar esta foto'
                    },
                    library: {
                        type: 'image'
                    },
                    multiple: false
                });
                
                // Cuando se selecciona una imagen
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    
                    // Llenar el campo de URL
                    $('#' + targetInput).val(attachment.url);
                    
                    // Mostrar vista previa
                    var previewHtml = '<img src="' + attachment.url + '" style="max-width: 150px; max-height: 150px; border: 1px solid #ddd; border-radius: 4px;">' +
                                     '<button type="button" class="button button-link-delete remove-foto-btn" data-target="' + targetInput + '" data-preview="' + previewContainer + '" style="display: block; margin-top: 5px; color: #dc3232;">🗑️ Eliminar foto</button>';
                    
                    $('#' + previewContainer).html(previewHtml).show();
                });
                
                // Abrir el uploader
                mediaUploader.open();
            });
            
            // Eliminar foto
            $(document).on('click', '.remove-foto-btn', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var targetInput = button.data('target');
                var previewContainer = button.data('preview');
                
                if (confirm('¿Estás seguro de que quieres eliminar esta foto?')) {
                    $('#' + targetInput).val('');
                    $('#' + previewContainer).html('').hide();
                }
            });
        });
        </script>
        
        <?php
    }
    
    /**
     * Renderizar formulario individual de sede
     */
    private function render_sede_form($index, $sede = array(), $collapsed = true) {
        $sede = wp_parse_args($sede, array(
            'nombre' => '',
            'descripcion' => '',
            'web' => '',
            'ano_fundacion' => '',
            'tamano_despacho' => '',
            'persona_contacto' => '',
            'email_contacto' => '',
            'telefono' => '',
            'numero_colegiado' => '',
            'colegio' => '',
            'experiencia' => '',
            'calle' => '',
            'numero' => '',
            'piso' => '',
            'localidad' => '',
            'provincia' => '',
            'codigo_postal' => '',
            'pais' => 'España',
            'especialidades' => '',
            'areas_practica' => array(),
            'servicios_especificos' => '',
            'certificaciones' => array(),
            'estado_verificacion' => 'pendiente',
            'estado_registro' => 'activo',
            'foto_perfil' => '',
            'is_verified' => false,
            'observaciones' => '',
            'horarios' => array(),
            'redes_sociales' => array(),
            'es_principal' => false,
            'activa' => true
        ));
        
        $collapse_class = $collapsed ? 'style="display: none;"' : '';
        ?>
        
        <div class="sede-form" style="border: 1px solid #ddd; margin-bottom: 15px; border-radius: 6px; background: #fff;">
            <div class="sede-header" style="background: #f1f1f1; padding: 12px; border-bottom: 1px solid #ddd; cursor: pointer;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h4 style="margin: 0; color: #2c3e50;">
                        <span class="toggle-sede">📍 <?php echo !empty($sede['nombre']) ? esc_html($sede['nombre']) : 'Nueva Sede'; ?></span>
                        <?php if ($sede['es_principal']): ?>
                            <span style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px; margin-left: 10px;">PRINCIPAL</span>
                        <?php endif; ?>
                        <?php if (!$sede['activa']): ?>
                            <span style="background: #95a5a6; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px; margin-left: 5px;">INACTIVA</span>
                        <?php endif; ?>
                    </h4>
                    <div>
                        <button type="button" class="toggle-sede button button-small">
                            <?php echo $collapsed ? 'Expandir' : 'Contraer'; ?>
                        </button>
                        <button type="button" class="remove-sede button button-small" style="margin-left: 5px; color: #e74c3c;">
                            Eliminar
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="sede-content" <?php echo $collapse_class; ?>>
                <div style="padding: 20px;">
                    <!-- Nombre de la sede -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Nombre de la Sede *</label>
                        <input type="text" name="sedes[<?php echo $index; ?>][nombre]" 
                               value="<?php echo esc_attr($sede['nombre']); ?>" 
                               style="width: 100%; padding: 8px;" required
                               placeholder="Ej: Sede Barcelona, Oficina Centro, etc.">
                        <small style="color: #666;">Este es el nombre específico de la sede, no del despacho</small>
                    </div>
                    
                    <!-- Descripción -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Descripción</label>
                        <textarea name="sedes[<?php echo $index; ?>][descripcion]" 
                                  style="width: 100%; padding: 8px; height: 80px;"><?php echo esc_textarea($sede['descripcion']); ?></textarea>
                    </div>
                    
                    <!-- Información básica -->
                    <div class="sede-row" style="display: grid; grid-template-columns: 1fr 1fr 150px 150px; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Sitio Web</label>
                            <input type="url" name="sedes[<?php echo $index; ?>][web]" 
                                   value="<?php echo esc_attr($sede['web']); ?>" 
                                   style="width: 100%; padding: 8px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Persona de Contacto</label>
                            <input type="text" name="sedes[<?php echo $index; ?>][persona_contacto]" 
                                   value="<?php echo esc_attr($sede['persona_contacto']); ?>" 
                                   style="width: 100%; padding: 8px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Año Fundación</label>
                            <input type="number" name="sedes[<?php echo $index; ?>][ano_fundacion]" 
                                   value="<?php echo esc_attr($sede['ano_fundacion']); ?>" 
                                   style="width: 100%; padding: 8px;" min="1900" max="2030">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Tamaño Despacho</label>
                            <select name="sedes[<?php echo $index; ?>][tamano_despacho]" style="width: 100%; padding: 8px;">
                                <option value="">Seleccionar</option>
                                <option value="pequeño" <?php selected($sede['tamano_despacho'], 'pequeño'); ?>>Pequeño (1-5)</option>
                                <option value="mediano" <?php selected($sede['tamano_despacho'], 'mediano'); ?>>Mediano (6-20)</option>
                                <option value="grande" <?php selected($sede['tamano_despacho'], 'grande'); ?>>Grande (21+)</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Contacto y información profesional -->
                    <div class="sede-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Teléfono</label>
                            <input type="text" name="sedes[<?php echo $index; ?>][telefono]" 
                                   value="<?php echo esc_attr($sede['telefono']); ?>" 
                                   style="width: 100%; padding: 8px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Email de Contacto</label>
                            <input type="email" name="sedes[<?php echo $index; ?>][email_contacto]" 
                                   value="<?php echo esc_attr($sede['email_contacto']); ?>" 
                                   style="width: 100%; padding: 8px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Nº Colegiado</label>
                            <input type="text" name="sedes[<?php echo $index; ?>][numero_colegiado]" 
                                   value="<?php echo esc_attr($sede['numero_colegiado']); ?>" 
                                   style="width: 100%; padding: 8px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Colegio</label>
                            <input type="text" name="sedes[<?php echo $index; ?>][colegio]" 
                                   value="<?php echo esc_attr($sede['colegio']); ?>" 
                                   style="width: 100%; padding: 8px;">
                        </div>
                    </div>
                    
                    <!-- Experiencia -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Experiencia</label>
                        <textarea name="sedes[<?php echo $index; ?>][experiencia]" 
                                  style="width: 100%; padding: 8px; height: 60px;"
                                  placeholder="Describe la experiencia profesional del despacho..."><?php echo esc_textarea($sede['experiencia']); ?></textarea>
                    </div>
                    
                    <!-- Dirección -->
                    <div style="margin-bottom: 20px;">
                        <h4 style="margin-bottom: 10px; color: #2c3e50;">📍 Dirección de la Sede</h4>
                        <div class="sede-row" style="display: grid; grid-template-columns: 1fr 100px 100px 150px; gap: 20px; margin-bottom: 15px;">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Calle *</label>
                                <input type="text" name="sedes[<?php echo $index; ?>][calle]" 
                                       value="<?php echo esc_attr($sede['calle']); ?>" 
                                       style="width: 100%; padding: 8px;"
                                       placeholder="Ej: Calle Mayor">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Número</label>
                                <input type="text" name="sedes[<?php echo $index; ?>][numero]" 
                                       value="<?php echo esc_attr($sede['numero']); ?>" 
                                       style="width: 100%; padding: 8px;"
                                       placeholder="Ej: 123">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Piso</label>
                                <input type="text" name="sedes[<?php echo $index; ?>][piso]" 
                                       value="<?php echo esc_attr($sede['piso']); ?>" 
                                       style="width: 100%; padding: 8px;"
                                       placeholder="Ej: 2º A">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Código Postal *</label>
                                <input type="text" name="sedes[<?php echo $index; ?>][codigo_postal]" 
                                       value="<?php echo esc_attr($sede['codigo_postal']); ?>" 
                                       style="width: 100%; padding: 8px;"
                                       placeholder="Ej: 28001">
                            </div>
                        </div>
                    </div>
                    
                    <div class="sede-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Localidad</label>
                            <input type="text" name="sedes[<?php echo $index; ?>][localidad]" 
                                   value="<?php echo esc_attr($sede['localidad']); ?>" 
                                   style="width: 100%; padding: 8px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Provincia</label>
                            <input type="text" name="sedes[<?php echo $index; ?>][provincia]" 
                                   value="<?php echo esc_attr($sede['provincia']); ?>" 
                                   style="width: 100%; padding: 8px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">País</label>
                            <input type="text" name="sedes[<?php echo $index; ?>][pais]" 
                                   value="<?php echo esc_attr($sede['pais']); ?>" 
                                   style="width: 100%; padding: 8px;">
                        </div>
                    </div>
                    
                    <!-- Especialidades y servicios -->
                    <div style="margin-bottom: 20px;">
                        <h4 style="margin-bottom: 10px; color: #2c3e50;">⚖️ Especialidades y Servicios</h4>
                        <div class="sede-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Especialidades</label>
                                <textarea name="sedes[<?php echo $index; ?>][especialidades]" 
                                          style="width: 100%; padding: 8px; height: 80px;"><?php echo esc_textarea($sede['especialidades']); ?></textarea>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Servicios Específicos</label>
                                <textarea name="sedes[<?php echo $index; ?>][servicios_especificos]" 
                                          style="width: 100%; padding: 8px; height: 80px;"><?php echo esc_textarea($sede['servicios_especificos']); ?></textarea>
                            </div>
                        </div>
                        
                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 10px; font-weight: bold;">Áreas de Práctica</label>
                            <?php 
                            // Obtener todas las áreas de práctica disponibles
                            $all_areas = get_terms(array(
                                'taxonomy' => 'area_practica',
                                'hide_empty' => false,
                                'orderby' => 'name',
                                'order' => 'ASC'
                            ));
                            
                            // Áreas seleccionadas para esta sede
                            $selected_areas = is_array($sede['areas_practica']) ? $sede['areas_practica'] : 
                                             (is_string($sede['areas_practica']) ? explode(', ', $sede['areas_practica']) : array());
                            
                            if (!empty($all_areas) && !is_wp_error($all_areas)): ?>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                                    <?php foreach ($all_areas as $area): 
                                        $checked = in_array($area->name, $selected_areas) ? 'checked' : '';
                                    ?>
                                        <label style="display: flex; align-items: center; margin-bottom: 5px; font-weight: normal;">
                                            <input type="checkbox" 
                                                   name="sedes[<?php echo $index; ?>][areas_practica][]" 
                                                   value="<?php echo esc_attr($area->name); ?>" 
                                                   <?php echo $checked; ?>
                                                   style="margin-right: 8px;">
                                            <?php echo esc_html($area->name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <small style="color: #666; display: block; margin-top: 5px;">
                                    Selecciona las áreas de práctica que corresponden a esta sede. 
                                    <?php if (current_user_can('manage_options')): ?>
                                        <a href="<?php echo admin_url('edit-tags.php?taxonomy=area_practica&post_type=despacho'); ?>" target="_blank">Gestionar áreas</a>
                                    <?php endif; ?>
                                </small>
                            <?php else: ?>
                                <p style="color: #999; font-style: italic;">
                                    No hay áreas de práctica disponibles. 
                                    <?php if (current_user_can('manage_options')): ?>
                                        <a href="<?php echo admin_url('edit-tags.php?taxonomy=area_practica&post_type=despacho'); ?>">Crear áreas</a> o 
                                        <a href="<?php echo admin_url('edit.php?post_type=despacho&page=sync-areas'); ?>">Sincronizar desde Algolia</a>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Horarios -->
                    <div style="margin-bottom: 20px;">
                        <h4 style="margin-bottom: 10px; color: #2c3e50;">🕐 Horarios de Atención</h4>
                        <?php 
                        $dias = array('lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo');
                        $horarios = is_array($sede['horarios']) ? $sede['horarios'] : array();
                        ?>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <?php foreach ($dias as $dia): ?>
                                <div>
                                    <label style="display: block; margin-bottom: 3px; text-transform: capitalize;"><?php echo $dia; ?></label>
                                    <input type="text" name="sedes[<?php echo $index; ?>][horarios][<?php echo $dia; ?>]" 
                                           value="<?php echo esc_attr($horarios[$dia] ?? ''); ?>" 
                                           style="width: 100%; padding: 6px; font-size: 12px;">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Redes Sociales -->
                    <div style="margin-bottom: 20px;">
                        <h4 style="margin-bottom: 10px; color: #2c3e50;">🌐 Redes Sociales</h4>
                        <?php 
                        $redes = array(
                            'facebook' => 'Facebook',
                            'twitter' => 'Twitter',
                            'linkedin' => 'LinkedIn',
                            'instagram' => 'Instagram'
                        );
                        $redes_sociales = is_array($sede['redes_sociales']) ? $sede['redes_sociales'] : array();
                        ?>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <?php foreach ($redes as $red => $label): ?>
                                <div>
                                    <label style="display: block; margin-bottom: 3px;"><?php echo $label; ?></label>
                                    <input type="url" name="sedes[<?php echo $index; ?>][redes_sociales][<?php echo $red; ?>]" 
                                           value="<?php echo esc_attr($redes_sociales[$red] ?? ''); ?>" 
                                           style="width: 100%; padding: 6px; font-size: 12px;">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Estado y verificación -->
                    <div style="margin-bottom: 20px;">
                        <h4 style="margin-bottom: 10px; color: #2c3e50;">🔍 Estado y Verificación</h4>
                        <div class="sede-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Estado Verificación</label>
                                <select name="sedes[<?php echo $index; ?>][estado_verificacion]" style="width: 100%; padding: 8px;">
                                    <option value="pendiente" <?php selected($sede['estado_verificacion'], 'pendiente'); ?>>Pendiente</option>
                                    <option value="verificado" <?php selected($sede['estado_verificacion'], 'verificado'); ?>>Verificado</option>
                                    <option value="rechazado" <?php selected($sede['estado_verificacion'], 'rechazado'); ?>>Rechazado</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Estado Registro</label>
                                <select name="sedes[<?php echo $index; ?>][estado_registro]" style="width: 100%; padding: 8px;">
                                    <option value="activo" <?php selected($sede['estado_registro'], 'activo'); ?>>Activo</option>
                                    <option value="inactivo" <?php selected($sede['estado_registro'], 'inactivo'); ?>>Inactivo</option>
                                    <option value="suspendido" <?php selected($sede['estado_registro'], 'suspendido'); ?>>Suspendido</option>
                                </select>
                            </div>
                            <div>
                                <!-- Checkbox eliminado - se usa solo el desplegable Estado Verificación -->
                            </div>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Observaciones</label>
                            <textarea name="sedes[<?php echo $index; ?>][observaciones]" 
                                      style="width: 100%; padding: 8px; height: 60px;"
                                      placeholder="Observaciones internas sobre la sede..."><?php echo esc_textarea($sede['observaciones']); ?></textarea>
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <label style="display: block; margin-bottom: 10px; font-weight: bold;">🖼️ Foto de Perfil</label>
                            <div style="display: flex; gap: 10px; align-items: flex-start;">
                                <div style="flex: 1;">
                                    <input type="url" 
                                           name="sedes[<?php echo $index; ?>][foto_perfil]" 
                                           id="foto_perfil_<?php echo $index; ?>"
                                           value="<?php echo esc_attr($sede['foto_perfil']); ?>" 
                                           style="width: 100%; padding: 8px;"
                                           placeholder="https://ejemplo.com/foto.jpg">
                                </div>
                                <button type="button" 
                                        class="button button-secondary upload-foto-btn"
                                        data-target="foto_perfil_<?php echo $index; ?>"
                                        data-preview="preview_<?php echo $index; ?>">
                                    📁 Subir Foto
                                </button>
                            </div>
                            
                            <!-- Vista previa de la foto -->
                            <?php if (!empty($sede['foto_perfil'])): ?>
                                <div id="preview_<?php echo $index; ?>" style="margin-top: 10px;">
                                    <img src="<?php echo esc_url($sede['foto_perfil']); ?>" 
                                         style="max-width: 150px; max-height: 150px; border: 1px solid #ddd; border-radius: 4px;">
                                    <button type="button" 
                                            class="button button-link-delete remove-foto-btn"
                                            data-target="foto_perfil_<?php echo $index; ?>"
                                            data-preview="preview_<?php echo $index; ?>"
                                            style="display: block; margin-top: 5px; color: #dc3232;">
                                        🗑️ Eliminar foto
                                    </button>
                                </div>
                            <?php else: ?>
                                <div id="preview_<?php echo $index; ?>" style="margin-top: 10px; display: none;">
                                    <!-- Se llenará con JavaScript -->
                                </div>
                            <?php endif; ?>
                            
                            <small style="color: #666; display: block; margin-top: 5px;">
                                Puedes subir una foto o pegar la URL directamente. Si no se especifica, se usará la foto predefinida del sistema.
                            </small>
                        </div>
                    </div>
                    
                    <!-- Opciones de Estado -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                        <div>
                            <label style="display: flex; align-items: center;">
                                <input type="checkbox" name="sedes[<?php echo $index; ?>][es_principal]" 
                                       value="1" <?php checked($sede['es_principal'], true); ?> 
                                       style="margin-right: 8px;">
                                <strong>Sede Principal</strong>
                            </label>
                        </div>
                        <div>
                            <label style="display: flex; align-items: center;">
                                <input type="checkbox" name="sedes[<?php echo $index; ?>][activa]" 
                                       value="1" <?php checked($sede['activa'], true); ?> 
                                       style="margin-right: 8px;">
                                <strong>Sede Activa</strong>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
    }
    
    /**
     * Guardar sedes del despacho
     */
    public function save_sedes($post_id) {
        // DEBUG TEMPORAL: Log para ver si la función se ejecuta
        error_log("=== LEXHOY DEBUG save_sedes ejecutándose para post $post_id ===");
        error_log("POST keys: " . implode(', ', array_keys($_POST)));
        error_log("Nonce presente: " . (isset($_POST['despacho_sedes_nonce']) ? 'SÍ' : 'NO'));
        
        // Verificar nonce
        if (!isset($_POST['despacho_sedes_nonce']) || !wp_verify_nonce($_POST['despacho_sedes_nonce'], 'despacho_sedes_meta_box')) {
            error_log("LEXHOY DEBUG: Nonce inválido o faltante - SALIENDO");
            return;
        }
        
        error_log("LEXHOY DEBUG: Nonce válido - CONTINUANDO");
        
        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Verificar si es autoguardado
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verificar tipo de post
        if (get_post_type($post_id) != 'despacho') {
            return;
        }
        
        error_log("LEXHOY DEBUG: ¿Hay datos de sedes en POST? " . (isset($_POST['sedes']) ? 'SÍ' : 'NO'));
        if (isset($_POST['sedes'])) {
            error_log("LEXHOY DEBUG: Datos sedes recibidos: " . print_r($_POST['sedes'], true));
        }
        
        if (isset($_POST['sedes']) && is_array($_POST['sedes'])) {
            $sedes_data = array();
            
            foreach ($_POST['sedes'] as $index => $sede) {
                // Sanitizar datos
                $sede_clean = array(
                    'nombre' => sanitize_text_field($sede['nombre'] ?? ''),
                    'descripcion' => sanitize_textarea_field($sede['descripcion'] ?? ''),
                    'web' => esc_url_raw($sede['web'] ?? ''),
                    'ano_fundacion' => sanitize_text_field($sede['ano_fundacion'] ?? ''),
                    'tamano_despacho' => sanitize_text_field($sede['tamano_despacho'] ?? ''),
                    'persona_contacto' => sanitize_text_field($sede['persona_contacto'] ?? ''),
                    'email_contacto' => sanitize_email($sede['email_contacto'] ?? ''),
                    'telefono' => sanitize_text_field($sede['telefono'] ?? ''),
                    'numero_colegiado' => sanitize_text_field($sede['numero_colegiado'] ?? ''),
                    'colegio' => sanitize_text_field($sede['colegio'] ?? ''),
                    'experiencia' => sanitize_textarea_field($sede['experiencia'] ?? ''),
                    'calle' => sanitize_text_field($sede['calle'] ?? ''),
                    'numero' => sanitize_text_field($sede['numero'] ?? ''),
                    'piso' => sanitize_text_field($sede['piso'] ?? ''),
                    'localidad' => sanitize_text_field($sede['localidad'] ?? ''),
                    'provincia' => sanitize_text_field($sede['provincia'] ?? ''),
                    'codigo_postal' => sanitize_text_field($sede['codigo_postal'] ?? ''),
                    'pais' => sanitize_text_field($sede['pais'] ?? 'España'),
                    'especialidades' => sanitize_textarea_field($sede['especialidades'] ?? ''),
                    'servicios_especificos' => sanitize_textarea_field($sede['servicios_especificos'] ?? ''),
                    'estado_verificacion' => sanitize_text_field($sede['estado_verificacion'] ?? 'pendiente'),
                    'estado_registro' => sanitize_text_field($sede['estado_registro'] ?? 'activo'),
                    'foto_perfil' => esc_url_raw($sede['foto_perfil'] ?? ''),
                    'is_verified' => (sanitize_text_field($sede['estado_verificacion'] ?? 'pendiente') === 'verificado') ? true : false,
                    'observaciones' => sanitize_textarea_field($sede['observaciones'] ?? ''),
                    'es_principal' => isset($sede['es_principal']) ? true : false,
                    'activa' => isset($sede['activa']) ? true : false,
                    'horarios' => array(),
                    'redes_sociales' => array(),
                    'areas_practica' => array()
                );
                
                // Procesar areas_practica (vienen como array de checkboxes)
                if (isset($sede['areas_practica']) && is_array($sede['areas_practica'])) {
                    $sede_clean['areas_practica'] = array_map('sanitize_text_field', $sede['areas_practica']);
                } elseif (isset($sede['areas_practica']) && is_string($sede['areas_practica'])) {
                    // Fallback para formato de texto separado por comas (datos de Algolia)
                    $areas = explode(',', $sede['areas_practica']);
                    $sede_clean['areas_practica'] = array_map('trim', $areas);
                }
                
                // Sanitizar horarios
                if (isset($sede['horarios']) && is_array($sede['horarios'])) {
                    foreach ($sede['horarios'] as $dia => $horario) {
                        $sede_clean['horarios'][$dia] = sanitize_text_field($horario);
                    }
                }
                
                // Sanitizar redes sociales
                if (isset($sede['redes_sociales']) && is_array($sede['redes_sociales'])) {
                    foreach ($sede['redes_sociales'] as $red => $url) {
                        $sede_clean['redes_sociales'][$red] = esc_url_raw($url);
                    }
                }
                
                // Solo guardar si tiene nombre
                if (!empty($sede_clean['nombre'])) {
                    $sedes_data[] = $sede_clean;
                }
            }
            
            error_log("LEXHOY DEBUG: Guardando " . count($sedes_data) . " sedes");
            error_log("LEXHOY DEBUG: Datos finales: " . print_r($sedes_data, true));
            
            $result = update_post_meta($post_id, '_despacho_sedes', $sedes_data);
            error_log("LEXHOY DEBUG: Resultado update_post_meta: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            // Verificar que se guardó
            $saved_sedes = get_post_meta($post_id, '_despacho_sedes', true);
            error_log("LEXHOY DEBUG: Sedes guardadas verificación: " . count($saved_sedes) . " sedes");
        } else {
            error_log("LEXHOY DEBUG: No hay datos de sedes, eliminando meta");
            delete_post_meta($post_id, '_despacho_sedes');
        }
        
        error_log("=== LEXHOY DEBUG save_sedes TERMINADO ===");
    }
    
    /**
     * AJAX: Añadir nueva sede
     */
    public function ajax_add_sede() {
        $index = intval($_POST['sede_index'] ?? 0);
        $sede_data = $_POST['sede_data'] ?? array();
        
        // Si hay datos de sede (desde Algolia), mapearlos
        if (!empty($sede_data)) {
            $sede = array(
                'nombre' => $sede_data['nombre'] ?? '',
                'descripcion' => $sede_data['descripcion'] ?? '',
                'web' => $sede_data['web'] ?? '',
                'ano_fundacion' => $sede_data['ano_fundacion'] ?? '',
                'tamano_despacho' => $sede_data['tamano_despacho'] ?? '',
                'persona_contacto' => $sede_data['persona_contacto'] ?? '',
                'email_contacto' => $sede_data['email_contacto'] ?? '',
                'telefono' => $sede_data['telefono'] ?? '',
                'numero_colegiado' => $sede_data['numero_colegiado'] ?? '',
                'colegio' => $sede_data['colegio'] ?? '',
                'experiencia' => $sede_data['experiencia'] ?? '',
                'calle' => $sede_data['calle'] ?? '',
                'numero' => $sede_data['numero'] ?? '',
                'piso' => $sede_data['piso'] ?? '',
                'localidad' => $sede_data['localidad'] ?? '',
                'provincia' => $sede_data['provincia'] ?? '',
                'codigo_postal' => $sede_data['codigo_postal'] ?? '',
                'pais' => $sede_data['pais'] ?? 'España',
                'especialidades' => $sede_data['especialidades'] ?? '',
                'areas_practica' => $sede_data['areas_practica'] ?? array(),
                'servicios_especificos' => $sede_data['servicios_especificos'] ?? '',
                'estado_verificacion' => $sede_data['estado_verificacion'] ?? 'pendiente',
                'estado_registro' => $sede_data['estado_registro'] ?? 'activo',
                'foto_perfil' => $sede_data['foto_perfil'] ?? '',
                'is_verified' => (($sede_data['estado_verificacion'] ?? 'pendiente') === 'verificado') ? true : false,
                'observaciones' => $sede_data['observaciones'] ?? '',
                'horarios' => $sede_data['horarios'] ?? array(),
                'redes_sociales' => $sede_data['redes_sociales'] ?? array(),
                'es_principal' => $sede_data['es_principal'] ?? false,
                'activa' => $sede_data['activa'] ?? true
            );
        } else {
            $sede = array();
        }
        
        $this->render_sede_form($index, $sede, false);
        wp_die();
    }
    
    /**
     * Obtener sedes desde Algolia basado en el post ID
     */
    private function get_sedes_from_algolia($post_id) {
        try {
            // Obtener el object_id de Algolia para este despacho
            $algolia_object_id = get_post_meta($post_id, '_algolia_object_id', true);
            
            if (empty($algolia_object_id)) {
                return array();
            }
            
            // Obtener configuración de Algolia
            $app_id = get_option('lexhoy_despachos_algolia_app_id');
            $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
            $index_name = get_option('lexhoy_despachos_algolia_index_name');
            
            if (empty($app_id) || empty($admin_api_key) || empty($index_name)) {
                return array();
            }
            
            // Inicializar cliente Algolia
            require_once(dirname(__FILE__) . '/class-lexhoy-algolia-client.php');
            $client = new LexhoyAlgoliaClient($app_id, $admin_api_key, '', $index_name);
            
            // Obtener el registro desde Algolia
            $record = $client->get_object($index_name, $algolia_object_id);
            
            if ($record && isset($record['sedes']) && is_array($record['sedes'])) {
                return $record['sedes'];
            }
            
            return array();
        } catch (Exception $e) {
            // En caso de cualquier error, devolver array vacío
            return array();
        }
    }
} 
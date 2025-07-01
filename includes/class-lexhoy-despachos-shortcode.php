<?php
if (!defined('ABSPATH')) {
    exit;
}

class LexhoyDespachosShortcode {
    private $algolia_client;

    public function __construct() {
        add_shortcode('lexhoy_despachos_search', array($this, 'render_search_form'));
        add_action('wp_ajax_lexhoy_despachos_search', array($this, 'ajax_search_despachos'));
        add_action('wp_ajax_nopriv_lexhoy_despachos_search', array($this, 'ajax_search_despachos'));
        
        // Limpiar caché cuando se actualicen despachos
        add_action('save_post_despacho', array($this, 'clear_cache'));
        add_action('deleted_post', array($this, 'clear_cache'));
        add_action('wp_update_nav_menu', array($this, 'clear_cache'));
        
        // Obtener credenciales de Algolia (para futuras integraciones)
        $app_id = get_option('lexhoy_despachos_algolia_app_id');
        $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
        $search_api_key = get_option('lexhoy_despachos_algolia_search_api_key');
        $index_name = get_option('lexhoy_despachos_algolia_index_name');

        if ($app_id && $admin_api_key && $search_api_key && $index_name) {
            $this->algolia_client = new LexhoyAlgoliaClient($app_id, $admin_api_key, $search_api_key, $index_name);
        }

        // Filtros para configurar títulos de página del buscador
        add_filter('document_title_parts', array($this, 'modify_search_page_title'), 10, 1);
        add_filter('wp_title', array($this, 'modify_search_wp_title'), 10, 2);
    }

    public function render_search_form($atts) {
        // Enqueue scripts y estilos necesarios
        wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
        wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        wp_enqueue_style('lexhoy-despachos-search', LEXHOY_DESPACHOS_PLUGIN_URL . 'assets/css/search.css', array(), LEXHOY_DESPACHOS_VERSION);
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('lexhoy-despachos-search', LEXHOY_DESPACHOS_PLUGIN_URL . 'assets/js/search.js', array('jquery'), LEXHOY_DESPACHOS_VERSION, true);

        // Pasar datos a JavaScript
        wp_localize_script('lexhoy-despachos-search', 'lexhoyDespachosData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lexhoy_despachos_search'),
            'pluginUrl' => LEXHOY_DESPACHOS_PLUGIN_URL
        ));

        // Obtener datos para los filtros
        $provincias = $this->get_provincias();
        $localidades = $this->get_localidades();
        $areas = $this->get_areas_practica();

        ob_start();
        ?>
        <div class="lexhoy-despachos-search">
            <div class="search-header">
                <div class="search-title">
                    | Busca alfabéticamente o por nombre en nuestra lista de abogados: |
                </div>

                <div class="alphabet-container">
                    <?php
                    $letters = range('A', 'Z');
                    foreach ($letters as $letter) {
                        echo '<div class="alphabet-letter" data-letter="' . $letter . '">' . $letter . '</div>';
                    }
                    ?>
                </div>

                <div class="search-box-container">
                    <input type="text" id="searchbox" class="search-input" placeholder="Buscar despachos..." />
                    <button type="button" id="search-button" class="search-button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>

            <div class="search-content">
                <div class="filters-sidebar">
                    <div class="filters-tabs">
                        <div class="filters-tab-header">
                            <button class="filter-tab-btn active" data-tab="province">Provincias</button>
                            <button class="filter-tab-btn" data-tab="location">Localidades</button>
                            <button class="filter-tab-btn" data-tab="practice">Áreas</button>
                        </div>
                        <div class="filters-tab-content">
                            <div id="province-list" class="filter-tab-pane active">
                                <div class="filter-search">
                                    <input type="text" placeholder="Buscar provincia..." class="filter-search-input" data-filter="provincia">
                                </div>
                                <div class="filter-list" id="provincias-filter">
                                    <?php foreach ($provincias as $provincia): ?>
                                        <div class="filter-item" data-value="<?php echo esc_attr($provincia); ?>">
                                            <label class="filter-label">
                                                <input type="checkbox" class="filter-checkbox" data-filter="provincia" value="<?php echo esc_attr($provincia); ?>">
                                                <span class="filter-text"><?php echo esc_html($provincia); ?></span>
                                                <span class="filter-count">(<?php echo $this->get_count_by_provincia($provincia); ?>)</span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div id="location-list" class="filter-tab-pane">
                                <div class="filter-search">
                                    <input type="text" placeholder="Buscar localidad..." class="filter-search-input" data-filter="localidad">
                                </div>
                                <div class="filter-list" id="localidades-filter">
                                    <?php foreach ($localidades as $localidad): ?>
                                        <div class="filter-item" data-value="<?php echo esc_attr($localidad); ?>">
                                            <label class="filter-label">
                                                <input type="checkbox" class="filter-checkbox" data-filter="localidad" value="<?php echo esc_attr($localidad); ?>">
                                                <span class="filter-text"><?php echo esc_html($localidad); ?></span>
                                                <span class="filter-count">(<?php echo $this->get_count_by_localidad($localidad); ?>)</span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div id="practice-list" class="filter-tab-pane">
                                <div class="filter-search">
                                    <input type="text" placeholder="Buscar área..." class="filter-search-input" data-filter="area">
                                </div>
                                <div class="filter-list" id="areas-filter">
                                    <?php foreach ($areas as $area): ?>
                                        <div class="filter-item" data-value="<?php echo esc_attr($area); ?>">
                                            <label class="filter-label">
                                                <input type="checkbox" class="filter-checkbox" data-filter="area" value="<?php echo esc_attr($area); ?>">
                                                <span class="filter-text"><?php echo esc_html($area); ?></span>
                                                <span class="filter-count">(<?php echo $this->get_count_by_area($area); ?>)</span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="current-refinements" class="current-refinements"></div>
                </div>
                <div class="results-sidebar">
                    <div id="hits" class="results-container">
                        <div class="loading-message">Cargando despachos...</div>
                    </div>
                    <div id="pagination" class="pagination-container"></div>
                </div>
            </div>
        </div>

        <script type="text/html" id="hit-template">
            <div class="despacho-card hit-card">
                {{#isVerified}}
                    <div class="verification-badge">
                        <i class="fas fa-check-circle"></i>
                        <span>Verificado</span>
                    </div>
                {{/isVerified}}
                <div class="despacho-name">{{nombre}}</div>
                <div class="despacho-location">{{localidad}}, {{provincia}}</div>
                <div class="despacho-areas"><strong>Áreas:</strong> {{areas_practica}}</div>
                <a href="{{link}}" class="despacho-link">Ver más</a>
            </div>
        </script>

        <script type="text/html" id="no-results-template">
            <div class="no-results">
                <p>No se encontraron resultados para <q>{{query}}</q>.</p>
                <p>Intenta con otros términos de búsqueda o elimina los filtros.</p>
            </div>
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler para búsqueda de despachos
     */
    public function ajax_search_despachos() {
        check_ajax_referer('lexhoy_despachos_search', 'nonce');

        $search = sanitize_text_field($_POST['search'] ?? '');
        $provincia = sanitize_text_field($_POST['provincia'] ?? '');
        $localidad = sanitize_text_field($_POST['localidad'] ?? '');
        $area = sanitize_text_field($_POST['area'] ?? '');
        $page = intval($_POST['page'] ?? 1);
        $per_page = 10;

        // Optimizar consulta con índices específicos
        $args = array(
            'post_type' => 'despacho',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'meta_query' => array(),
            'tax_query' => array(),
            'no_found_rows' => false // Necesario para paginación
        );

        // Búsqueda por texto
        if (!empty($search)) {
            // Verificar si es una búsqueda por letra del alfabeto (una sola letra)
            if (strlen($search) === 1 && ctype_alpha($search)) {
                // Búsqueda por letra inicial usando consulta SQL personalizada
                global $wpdb;
                $letter = strtoupper($search);
                $post_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} 
                    WHERE post_type = 'despacho' 
                    AND post_status = 'publish' 
                    AND UPPER(post_title) LIKE %s",
                    $letter . '%'
                ));
                
                if (!empty($post_ids)) {
                    $args['post__in'] = $post_ids;
                } else {
                    // Si no hay resultados, forzar que no devuelva nada
                    $args['post__in'] = array(0);
                }
            } else {
                // Búsqueda normal por texto
                $args['s'] = $search;
            }
        }

        // Filtro por provincia
        if (!empty($provincia)) {
            $provincias = explode(',', $provincia);
            $args['meta_query'][] = array(
                'key' => '_despacho_provincia',
                'value' => $provincias,
                'compare' => 'IN'
            );
        }

        // Filtro por localidad
        if (!empty($localidad)) {
            $localidades = explode(',', $localidad);
            $args['meta_query'][] = array(
                'key' => '_despacho_localidad',
                'value' => $localidades,
                'compare' => 'IN'
            );
        }

        // Filtro por área de práctica
        if (!empty($area)) {
            $areas = explode(',', $area);
            $args['tax_query'][] = array(
                'taxonomy' => 'area_practica',
                'field' => 'name',
                'terms' => $areas,
                'operator' => 'IN'
            );
        }

        // Ejecutar consulta optimizada
        $query = new WP_Query($args);
        $despachos = array();

        if ($query->have_posts()) {
            // Cargar los posts directamente sin optimización adicional
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $despachos[] = array(
                    'id' => $post_id,
                    'nombre' => get_post_meta($post_id, '_despacho_nombre', true) ?: get_the_title(),
                    'localidad' => get_post_meta($post_id, '_despacho_localidad', true),
                    'provincia' => get_post_meta($post_id, '_despacho_provincia', true),
                    'areas_practica' => $this->get_areas_for_post($post_id),
                    'isVerified' => get_post_meta($post_id, '_despacho_is_verified', true) === '1',
                    'link' => get_permalink($post_id),
                    'slug' => get_post_field('post_name', $post_id)
                );
            }
        }

        wp_reset_postdata();

        // Generar HTML de los despachos
        $html = '';
        if (empty($despachos)) {
            $html = '<div class="no-results">
                        <p>No se encontraron resultados' . 
                        (!empty($search) ? ' para <q>' . esc_html($search) . '</q>' : '') . 
                        '.</p>
                        <p>Intenta con otros términos de búsqueda o elimina los filtros.</p>
                    </div>';
        } else {
            $html = '<div class="despachos-grid">';
            foreach ($despachos as $despacho) {
                $html .= '<div class="despacho-card hit-card">';
                if ($despacho['isVerified']) {
                    $html .= '<div class="verification-badge">
                                <i class="fas fa-check-circle"></i>
                                <span>Verificado</span>
                            </div>';
                }
                $html .= '<div class="despacho-name">' . esc_html($despacho['nombre']) . '</div>';
                $html .= '<div class="despacho-location">' . esc_html($despacho['localidad']) . ', ' . esc_html($despacho['provincia']) . '</div>';
                $html .= '<div class="despacho-areas"><strong>Áreas:</strong> ' . esc_html($despacho['areas_practica']) . '</div>';
                $html .= '<a href="' . esc_url($despacho['link']) . '" class="despacho-link">Ver más</a>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        // Generar datos de paginación
        $pagination = array(
            'total_pages' => $query->max_num_pages,
            'current_page' => $page,
            'total_results' => $query->found_posts
        );

        wp_send_json_success(array(
            'html' => $html,
            'pagination' => $pagination,
            'total' => $query->found_posts
        ));
    }

    /**
     * Obtener provincias únicas con caché
     */
    private function get_provincias() {
        $cache_key = 'lexhoy_despachos_provincias';
        $provincias = wp_cache_get($cache_key);
        
        if (false === $provincias) {
            global $wpdb;
            $provincias = $wpdb->get_col(
                "SELECT DISTINCT pm.meta_value 
                FROM {$wpdb->postmeta} pm 
                JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                WHERE pm.meta_key = '_despacho_provincia' 
                AND pm.meta_value != '' 
                AND p.post_type = 'despacho' 
                AND p.post_status = 'publish'
                ORDER BY pm.meta_value ASC"
            );
            wp_cache_set($cache_key, $provincias, '', 3600); // Cache por 1 hora
        }
        
        return $provincias ?: array();
    }

    /**
     * Obtener localidades únicas con caché
     */
    private function get_localidades() {
        $cache_key = 'lexhoy_despachos_localidades';
        $localidades = wp_cache_get($cache_key);
        
        if (false === $localidades) {
            global $wpdb;
            $localidades = $wpdb->get_col(
                "SELECT DISTINCT pm.meta_value 
                FROM {$wpdb->postmeta} pm 
                JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                WHERE pm.meta_key = '_despacho_localidad' 
                AND pm.meta_value != '' 
                AND p.post_type = 'despacho' 
                AND p.post_status = 'publish'
                ORDER BY pm.meta_value ASC"
            );
            wp_cache_set($cache_key, $localidades, '', 3600); // Cache por 1 hora
        }
        
        return $localidades ?: array();
    }

    /**
     * Obtener áreas de práctica únicas con caché
     */
    private function get_areas_practica() {
        $cache_key = 'lexhoy_despachos_areas';
        $areas = wp_cache_get($cache_key);
        
        if (false === $areas) {
            $terms = get_terms(array(
                'taxonomy' => 'area_practica',
                'hide_empty' => true,
                'orderby' => 'name',
                'order' => 'ASC'
            ));
            
            $areas = wp_list_pluck($terms, 'name');
            wp_cache_set($cache_key, $areas, '', 3600); // Cache por 1 hora
        }
        
        return $areas ?: array();
    }

    /**
     * Obtener áreas para un post específico con caché
     */
    private function get_areas_for_post($post_id) {
        $cache_key = 'lexhoy_despachos_areas_' . $post_id;
        $areas = wp_cache_get($cache_key);
        
        if (false === $areas) {
            $terms = wp_get_post_terms($post_id, 'area_practica', array('fields' => 'names'));
            $areas = $terms ? implode(', ', $terms) : '';
            wp_cache_set($cache_key, $areas, '', 3600); // Cache por 1 hora
        }
        
        return $areas;
    }

    /**
     * Obtener todos los conteos de una vez para optimizar
     */
    private function get_all_counts() {
        $cache_key = 'lexhoy_despachos_counts';
        $counts = wp_cache_get($cache_key);
        
        if (false === $counts) {
            global $wpdb;
            
            // Conteos por provincia
            $provincia_counts = $wpdb->get_results(
                "SELECT pm.meta_value as provincia, COUNT(*) as count
                FROM {$wpdb->postmeta} pm 
                JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                WHERE pm.meta_key = '_despacho_provincia' 
                AND pm.meta_value != '' 
                AND p.post_type = 'despacho' 
                AND p.post_status = 'publish'
                GROUP BY pm.meta_value",
                ARRAY_A
            );
            
            // Conteos por localidad
            $localidad_counts = $wpdb->get_results(
                "SELECT pm.meta_value as localidad, COUNT(*) as count
                FROM {$wpdb->postmeta} pm 
                JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                WHERE pm.meta_key = '_despacho_localidad' 
                AND pm.meta_value != '' 
                AND p.post_type = 'despacho' 
                AND p.post_status = 'publish'
                GROUP BY pm.meta_value",
                ARRAY_A
            );
            
            $counts = array(
                'provincias' => array_column($provincia_counts, 'count', 'provincia'),
                'localidades' => array_column($localidad_counts, 'count', 'localidad')
            );
            
            wp_cache_set($cache_key, $counts, '', 3600); // Cache por 1 hora
        }
        
        return $counts;
    }

    /**
     * Contar despachos por provincia (optimizado)
     */
    private function get_count_by_provincia($provincia) {
        $counts = $this->get_all_counts();
        return isset($counts['provincias'][$provincia]) ? $counts['provincias'][$provincia] : 0;
    }

    /**
     * Contar despachos por localidad (optimizado)
     */
    private function get_count_by_localidad($localidad) {
        $counts = $this->get_all_counts();
        return isset($counts['localidades'][$localidad]) ? $counts['localidades'][$localidad] : 0;
    }

    /**
     * Contar despachos por área (optimizado)
     */
    private function get_count_by_area($area) {
        $cache_key = 'lexhoy_despachos_area_count_' . sanitize_title($area);
        $count = wp_cache_get($cache_key);
        
        if (false === $count) {
            $term = get_term_by('name', $area, 'area_practica');
            $count = $term ? $term->count : 0;
            wp_cache_set($cache_key, $count, '', 3600); // Cache por 1 hora
        }
        
        return $count;
    }

    /**
     * Limpiar caché cuando se actualicen despachos
     */
    public function clear_cache() {
        wp_cache_delete('lexhoy_despachos_provincias');
        wp_cache_delete('lexhoy_despachos_localidades');
        wp_cache_delete('lexhoy_despachos_areas');
        wp_cache_delete('lexhoy_despachos_counts');
        
        // Limpiar caché de áreas por post
        global $wpdb;
        $post_ids = $wpdb->get_col(
            "SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE '_despacho_%'"
        );
        
        foreach ($post_ids as $post_id) {
            wp_cache_delete('lexhoy_despachos_areas_' . $post_id);
        }
        
        // Limpiar caché de conteos por área
        $areas = get_terms(array(
            'taxonomy' => 'area_practica',
            'hide_empty' => false,
            'fields' => 'names'
        ));
        
        foreach ($areas as $area) {
            wp_cache_delete('lexhoy_despachos_area_count_' . sanitize_title($area));
        }
    }

    /**
     * Filtro para configurar títulos de página del buscador
     */
    public function modify_search_page_title($title) {
        if ($this->is_search_page()) {
            $title['title'] = 'Buscador de despachos';
            $title['site'] = 'Lexhoy';
        }
        return $title;
    }

    /**
     * Filtro para configurar títulos de página del buscador
     */
    public function modify_search_wp_title($title, $sep) {
        if ($this->is_search_page()) {
            return 'Buscador de despachos ' . $sep . ' Lexhoy';
        }
        return $title;
    }

    /**
     * Verificar si estamos en una página que contiene el shortcode del buscador
     */
    private function is_search_page() {
        global $post;
        
        if (!is_object($post)) {
            return false;
        }

        // Verificar si el contenido de la página contiene nuestro shortcode
        return has_shortcode($post->post_content, 'lexhoy_despachos_search');
    }
} 
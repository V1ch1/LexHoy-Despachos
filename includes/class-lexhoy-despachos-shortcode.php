<?php
if (!defined('ABSPATH')) {
    exit;
}

class LexhoyDespachosShortcode {
    private $algolia_client;

    public function __construct() {
        add_shortcode('lexhoy_despachos_search', array($this, 'render_search_form'));
        
        // Obtener credenciales de Algolia
        $app_id = get_option('lexhoy_despachos_algolia_app_id');
        $admin_api_key = get_option('lexhoy_despachos_algolia_admin_api_key');
        $search_api_key = get_option('lexhoy_despachos_algolia_search_api_key');
        $index_name = get_option('lexhoy_despachos_algolia_index_name');

        if ($app_id && $admin_api_key && $search_api_key && $index_name) {
            $this->algolia_client = new LexhoyAlgoliaClient($app_id, $admin_api_key, $search_api_key, $index_name);
        }
    }

    public function render_search_form($atts) {
        // Enqueue scripts y estilos necesarios
        wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
        wp_enqueue_style('lexhoy-despachos-search', LEXHOY_DESPACHOS_PLUGIN_URL . 'assets/css/search.css', array(), LEXHOY_DESPACHOS_VERSION);
        
        wp_enqueue_script('algoliasearch', 'https://cdn.jsdelivr.net/npm/algoliasearch@4.22.1/dist/algoliasearch-lite.umd.js', array(), '4.22.1', true);
        wp_enqueue_script('instantsearch', 'https://cdn.jsdelivr.net/npm/instantsearch.js@4.65.0/dist/instantsearch.production.min.js', array('algoliasearch'), '4.65.0', true);
        wp_enqueue_script('lexhoy-despachos-search', LEXHOY_DESPACHOS_PLUGIN_URL . 'assets/js/search.js', array('jquery', 'algoliasearch', 'instantsearch'), LEXHOY_DESPACHOS_VERSION, true);

        // Pasar datos a JavaScript
        $settings = get_option('lexhoy_despachos_settings');
        wp_localize_script('lexhoy-despachos-search', 'lexhoyDespachosData', array(
            'appId' => $settings['algolia_app_id'] ?? '',
            'searchApiKey' => $settings['algolia_search_api_key'] ?? '',
            'indexName' => 'lexhoy_despachos_formatted',
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lexhoy_despachos_search')
        ));

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

                <div id="searchbox"></div>
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
                            <div id="province-list" class="filter-tab-pane active"></div>
                            <div id="location-list" class="filter-tab-pane"></div>
                            <div id="practice-list" class="filter-tab-pane"></div>
                        </div>
                    </div>
                    <div id="current-refinements"></div>
                </div>
                <div class="results-sidebar">
                    <div id="hits" class="results-container"></div>
                    <div id="pagination"></div>
                </div>
            </div>
        </div>

        <script type="text/html" id="hit-template">
            <div class="despacho-card hit-card" data-hit='{{{json this}}}'>
                {{#estado_verificacion}}
                    {{#isVerified}}
                        <div class="verification-badge">
                            <i class="fas fa-check-circle"></i>
                            <span>Verificado</span>
                        </div>
                    {{/isVerified}}
                {{/estado_verificacion}}
                <div class="despacho-name">{{nombre}}</div>
                <div class="despacho-location">{{localidad}}, {{provincia}}</div>
                <div class="despacho-areas"><strong>Áreas:</strong> {{areas_practica}}</div>
                <button class="despacho-link" onclick="window.navigateToDespacho('{{slug}}')">Ver más</button>
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
} 
<?php
if (!defined('ABSPATH')) {
    exit;
}

// Agregar página de shortcode
function lexhoy_despachos_add_shortcode_page() {
    add_submenu_page(
        'edit.php?post_type=despacho',
        'Shortcode',
        'Shortcode',
        'manage_options',
        'lexhoy-despachos-shortcode',
        'lexhoy_despachos_shortcode_page'
    );
}
add_action('admin_menu', 'lexhoy_despachos_add_shortcode_page');

// Página de shortcode
function lexhoy_despachos_shortcode_page() {
    ?>
    <div class="wrap lexhoy-despachos-admin">
        <h1>Shortcode del Plugin</h1>

        <div class="card">
            <h2>Uso del Plugin</h2>
            <p>Para mostrar el buscador de despachos, usa el siguiente shortcode en cualquier página o post:</p>
            <code>[lexhoy_despachos_search]</code>

            <h3>Instrucciones</h3>
            <p>1. Crea una nueva página o post donde quieras mostrar el buscador</p>
            <p>2. Inserta el shortcode <code>[lexhoy_despachos_search]</code> en el contenido</p>
            <p>3. Publica la página</p>
            <p>4. El buscador se mostrará en la ubicación donde hayas colocado el shortcode</p>
        </div>
    </div>
    <?php
} 
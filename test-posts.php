<?php
require_once('../../../wp-load.php');

$args = array(
    'post_type' => 'post',
    'posts_per_page' => -1,
    'post_status' => 'publish'
);

$query = new WP_Query($args);

echo "Total de posts encontrados: " . $query->found_posts . "\n\n";

if ($query->have_posts()) {
    while ($query->have_posts()) {
        $query->the_post();
        echo "Post ID: " . get_the_ID() . "\n";
        echo "Título: " . get_the_title() . "\n";
        echo "Meta datos: " . print_r(get_post_meta(get_the_ID()), true) . "\n";
        echo "------------------------\n";
    }
} else {
    echo "No se encontraron posts";
}

wp_reset_postdata(); 
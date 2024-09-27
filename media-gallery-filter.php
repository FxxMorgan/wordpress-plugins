<?php
/**
 * Plugin Name: Media Gallery Filter
 * Description: Muestra las fotos de la biblioteca de medios filtradas por categorías.
 * Version: 1.1
 * Author: FxxMorgan
 */

function mgf_enqueue_scripts() {
    wp_enqueue_style('mgf-style', plugins_url('style.css', __FILE__));
    wp_enqueue_script('mgf-script', plugins_url('script.js', __FILE__), array('jquery'), null, true);
    wp_localize_script('mgf-script', 'ajaxurl', array('ajaxurl' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'mgf_enqueue_scripts');

function mgf_display_media_gallery() {
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    // Obtener las categorías
    $categories = get_categories(array(
        'taxonomy' => 'category',
        'hide_empty' => true,
    ));

    ob_start(); ?>

    <div class="mgf-filter">
        <select id="category-filter">
            <option value="">Todas las categorías</option>
            <?php foreach ($categories as $category) : ?>
                <option value="<?php echo esc_attr($category->term_id); ?>"><?php echo esc_html($category->name); ?></option>
            <?php endforeach; ?>
        </select>

        <input type="text" id="search-filter" placeholder="Buscar por nombre">

        <select id="sort-filter">
            <option value="date">Más recientes</option>
            <option value="comment_count">Más comentadas</option>
            <option value="rating">Mejor calificadas</option>
        </select>
    </div>

    <div class="mgf-gallery" data-page="1">
        <?php mgf_load_images($paged); ?>
    </div>

    <div class="mgf-pagination">
        <?php
        $total_pages = mgf_get_total_pages();
        for ($i = 1; $i <= $total_pages; $i++) {
            echo '<a href="#" class="mgf-page" data-page="' . $i . '">' . $i . '</a>';
        }
        ?>
    </div>

    <?php
    return ob_get_clean();
}
add_shortcode('media_gallery', 'mgf_display_media_gallery');

function mgf_load_images() {
    error_log('Iniciando mgf_load_images'); // Log para depuración

    $paged = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $sort = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'date';

    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'posts_per_page' => 20,
        'paged' => $paged,
        's' => $search,
        'orderby' => $sort,
        'order' => 'DESC',
        'post_status' => 'inherit' // Asegúrate de incluir el estado del adjunto
    );

    error_log('Argumentos de WP_Query: ' . print_r($args, true)); // Log para depuración

    if ($category) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => $category,
            ),
        );
    }

    $media_query = new WP_Query($args);
    $images = $media_query->posts;

    error_log('Resultados de WP_Query: ' . print_r($images, true)); // Log para depuración

    if (empty($images)) {
        error_log('No se encontraron imágenes.'); // Log para depuración
    } else {
        foreach ($images as $image) {
            error_log('Imagen encontrada: ' . print_r($image, true)); // Log para depuración
            ?>
            <div class="mgf-item" data-category="<?php echo esc_attr(get_post_meta($image->ID, 'category', true)); ?>">
                <?php echo wp_get_attachment_image($image->ID, 'medium'); ?>
            </div>
            <?php
        }
    }
    wp_die();
}
add_action('wp_ajax_mgf_load_images', 'mgf_load_images');
add_action('wp_ajax_nopriv_mgf_load_images', 'mgf_load_images');

function mgf_get_total_pages() {
    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
    );

    $media_query = new WP_Query($args);
    $total_images = $media_query->found_posts;
    $total_pages = ceil($total_images / 20);

    error_log('Total de páginas: ' . $total_pages); // Log para depuración

    return $total_pages;
}

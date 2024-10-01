<?php
/*
Plugin Name: Custom Image Upload 
Description: Plugin para subir imágenes con redirección a páginas de éxito o error.
Version: 1.4
Author: FxxMorgan
*/


function custom_image_upload_shortcode() {
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;

        // Enlazar archivos CSS y JS
        wp_enqueue_style( 'custom-photo-style', plugins_url( '/css/style.css', __FILE__ ) );
        wp_enqueue_script( 'custom-photo-script', plugins_url( '/js/script.js', __FILE__ ), array('jquery'), null, true );

        ob_start();
        ?>
        <h2>Subir Fotografía</h2>
        <form id="image-upload-form" method="post" enctype="multipart/form-data">
            <label for="image-title">Título de la Imagen:</label><br>
            <input type="text" name="image-title" id="image-title" placeholder="Introduce un título" required><br><br>

            <label for="image-category">Selecciona una Categoría:</label><br>
            <select name="image-category" id="image-category" required>
                <?php
                $categories = get_categories( array(
                    'taxonomy'   => 'category',
                    'hide_empty' => false,
                ) );

                foreach ( $categories as $category ) {
                    echo '<option value="' . esc_attr( $category->term_id ) . '">' . esc_html( $category->name ) . '</option>';
                }
                ?>
            </select><br><br>

            <label for="image-upload">Subir Imagen (máximo 8MB):</label><br>
            <input type="file" name="image-upload" id="image-upload" accept="image/jpeg, image/png, image/gif" required><br><br>
            <input type="submit" name="submit-image" value="Subir Imagen">
        </form>
        <hr>

        <h2>Tus Fotografías Subidas</h2>
        <?php
        custom_display_user_images( $user_id );

        if ( isset( $_POST['submit-image'] ) && ! empty( $_FILES['image-upload']['name'] ) ) {
            custom_handle_image_upload( $user_id );
        }

        return ob_get_clean();
    } else {
        return '<p>Debes estar registrado para subir imágenes.</p>';
    }
}
add_shortcode('custom_image_upload', 'custom_image_upload_shortcode');

// Función para manejar la subida de imagen
function custom_handle_image_upload($user_id) {
    $max_file_size = 8 * 1024 * 1024;
    $allowed_mime_types = array('image/jpeg', 'image/png', 'image/gif');
    $uploadedfile = $_FILES['image-upload'];

    if ( $uploadedfile['size'] > $max_file_size || ! in_array( $uploadedfile['type'], $allowed_mime_types ) ) {
        wp_redirect( home_url('/upload-error') );
        exit;
    }

    $upload_overrides = array( 'test_form' => false );
    $movefile = wp_handle_upload( $uploadedfile, $upload_overrides );

    if ( $movefile && ! isset( $movefile['error'] ) ) {
        $filename = $movefile['file'];
        
        $post_id = wp_insert_post( array(
            'post_title'   => sanitize_text_field( $_POST['image-title'] ),
            'post_content' => '',
            'post_status'  => 'publish',
            'post_author'  => $user_id,
            'post_category' => array( intval( $_POST['image-category'] ) )
        ) );

        if ( !is_wp_error( $post_id ) ) {
            $wp_filetype = wp_check_filetype( basename( $filename ), null );
            $attachment = array(
                'guid'           => $movefile['url'],
                'post_mime_type' => $wp_filetype['type'],
                'post_title'     => sanitize_text_field( $_POST['image-title'] ),
                'post_content'   => '',
                'post_status'    => 'inherit',
                'post_parent'    => $post_id
            );

            $attach_id = wp_insert_attachment( $attachment, $filename, $post_id );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
            wp_update_attachment_metadata( $attach_id, $attach_data );

            set_post_thumbnail( $post_id, $attach_id );

            wp_redirect( home_url('/upload-success') );
            exit;
        } else {
            wp_redirect( home_url('/upload-error') );
            exit;
        }
    } else {
        wp_redirect( home_url('/upload-error') );
        exit;
    }
}

// Función para mostrar las imágenes subidas por el usuario y eliminar
function custom_display_user_images( $user_id ) {
    $args = array(
        'post_type'   => 'post',
        'post_status' => 'publish',
        'author'      => $user_id,
        'posts_per_page' => -1
    );

    $user_posts = new WP_Query( $args );

    if ( $user_posts->have_posts() ) {
        echo '<div class="user-images-grid">';
        while ( $user_posts->have_posts() ) {
            $user_posts->the_post();
            ?>
            <div class="user-image-item">
                <?php the_post_thumbnail( 'medium' ); ?>
                <h3><?php the_title(); ?></h3>
                <form method="post" class="delete-form" onsubmit="return confirm('¿Seguro que quieres eliminar esta foto?');">
                    <input type="hidden" name="delete_post_id" value="<?php echo get_the_ID(); ?>">
                    <input type="submit" name="delete-image" value="Eliminar Foto">
                </form>
            </div>
            <?php
        }
        echo '</div>';
    } else {
        echo '<p>No has subido ninguna fotografía.</p>';
    }

    wp_reset_postdata();

    // Manejo de eliminación de imágenes
    if ( isset( $_POST['delete-image'] ) && ! empty( $_POST['delete_post_id'] ) ) {
        $post_id = intval( $_POST['delete_post_id'] );
        if ( get_post_field( 'post_author', $post_id ) == $user_id ) {
            wp_delete_post( $post_id, true );
            wp_redirect( home_url() );
            exit;
        }
    }
}

// Solo el administrador puede calificar y dejar retroalimentación
function custom_add_feedback_form( $content ) {
    if ( current_user_can( 'administrator' ) && is_singular( 'post' ) ) {
        $post_id = get_the_ID();
        ob_start();
        ?>
        <h3>Retroalimentación del Administrador</h3>
        <form method="post">
            <label for="feedback">Retroalimentación:</label><br>
            <textarea name="feedback" id="feedback" required></textarea><br><br>
            <label for="rating">Calificación:</label><br>
            <select name="rating" id="rating">
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
            </select><br><br>
            <input type="submit" name="submit-feedback" value="Enviar Retroalimentación">
        </form>
        <?php
        if ( isset( $_POST['submit-feedback'] ) ) {
            $feedback = sanitize_text_field( $_POST['feedback'] );
            $rating = intval( $_POST['rating'] );

            update_post_meta( $post_id, '_admin_feedback', $feedback );
            update_post_meta( $post_id, '_admin_rating', $rating );

            // Enviar correo al autor
            $author_id = get_post_field( 'post_author', $post_id );
            $author_email = get_the_author_meta( 'user_email', $author_id );
            $subject = 'Retroalimentación para tu fotografía';
            $message = "Has recibido una nueva retroalimentación para tu fotografía:\n\n" . $feedback . "\n\nCalificación: " . $rating . "/5";
            wp_mail( $author_email, $subject, $message );

            echo '<p>Retroalimentación enviada y correo enviado al usuario.</p>';
        }
        return $content . ob_get_clean();
    }
    return $content;
}
add_filter( 'the_content', 'custom_add_feedback_form' );

// Mostrar la retroalimentación al usuario
function custom_display_feedback( $content ) {
    if ( is_singular( 'post' ) ) {
        $post_id = get_the_ID();
        $feedback = get_post_meta( $post_id, '_admin_feedback', true );
        $rating = get_post_meta( $post_id, '_admin_rating', true );

        if ( $feedback && $rating ) {
            $content .= '<h3>Retroalimentación del Administrador</h3>';
            $content .= '<p><strong>Calificación: </strong>' . $rating . '/5</p>';
            $content .= '<p>' . esc_html( $feedback ) . '</p>';
        }
    }
    return $content;
}
add_filter( 'the_content', 'custom_display_feedback' );

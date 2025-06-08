<?php
/**
 * Plugin Name: PSU Simple Booking
 * Description: A simple booking system for WordPress.
 * Version: 1.0
 * Author: Example Author
 */

// Register custom post type for bookings.
function psu_booking_post_type() {
    $labels = array(
        'name'          => __('Bookings', 'psu-simple-booking'),
        'singular_name' => __('Booking', 'psu-simple-booking'),
    );

    $args = array(
        'labels'      => $labels,
        'public'      => false,
        'show_ui'     => true,
        'supports'    => array('title'),
        'has_archive' => false,
    );

    register_post_type('psu_booking', $args);
}
add_action('init', 'psu_booking_post_type');

// Shortcode for booking form.
function psu_booking_form_shortcode() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['psu_booking_nonce'])) {
        if (wp_verify_nonce($_POST['psu_booking_nonce'], 'psu_booking')) {
            $name  = sanitize_text_field($_POST['psu_name']);
            $email = sanitize_email($_POST['psu_email']);
            $date  = sanitize_text_field($_POST['psu_date']);

            $post_id = wp_insert_post(array(
                'post_type'   => 'psu_booking',
                'post_title'  => $name,
                'post_status' => 'publish',
            ));

            if ($post_id) {
                update_post_meta($post_id, 'psu_email', $email);
                update_post_meta($post_id, 'psu_date', $date);
                echo '<p>Thank you for your booking!</p>';
            }
        }
    }

    ob_start();
    ?>
    <form method="post">
        <?php wp_nonce_field('psu_booking', 'psu_booking_nonce'); ?>
        <p>
            <label for="psu_name">Name</label><br>
            <input type="text" name="psu_name" id="psu_name" required>
        </p>
        <p>
            <label for="psu_email">Email</label><br>
            <input type="email" name="psu_email" id="psu_email" required>
        </p>
        <p>
            <label for="psu_date">Date</label><br>
            <input type="date" name="psu_date" id="psu_date" required>
        </p>
        <p>
            <button type="submit">Book</button>
        </p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('psu_booking_form', 'psu_booking_form_shortcode');

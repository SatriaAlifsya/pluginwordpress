<?php 
/**
 * Plugin Name: Auto Get Reviews Combined
 * Plugin URI: https://it.telkomuniversity.ac.id/
 * Description: A plugin to fetch reviews from Google Maps API and auto-generate posts.
 * Version: 1.0
 * Author: Feby Irmayana, Tangguh Satria
 * Author URI: 
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auto-get-reviews-combined
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Tambahkan menu plugin ke dashboard admin
function agrcr_add_admin_menu() {
    add_menu_page(
        'Google Reviews Combined Plugin',
        'Google Reviews',
        'manage_options',
        'google-reviews-combined-plugin',
        'agrcr_settings_page',
        'dashicons-thumbs-up',
        20
    );
    add_submenu_page(
        'google-reviews-combined-plugin',
        'Auto Generate Post Settings',
        'Auto Generate Post',
        'manage_options',
        'auto-generate-post-settings',
        'agrcr_display_settings_page'
    );
}
add_action('admin_menu', 'agrcr_add_admin_menu');

// Halaman pengaturan plugin utama
function agrcr_settings_page() {
    if (isset($_POST['agrcr_save_settings'])) {
        update_option('agrcr_api_key', sanitize_text_field($_POST['api_key']));
        update_option('agrcr_place_id', sanitize_text_field($_POST['place_id']));
        echo '<div class="updated"><p>Pengaturan disimpan!</p></div>';
    }

    $api_key = get_option('agrcr_api_key', '');
    $place_id = get_option('agrcr_place_id', '');

    ?>
    <div class="wrap">
        <h1>Pengaturan Google Reviews</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="api_key">Google API Key</label></th>
                    <td><input type="text" name="api_key" id="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="place_id">Google Place ID</label></th>
                    <td><input type="text" name="place_id" id="place_id" value="<?php echo esc_attr($place_id); ?>" class="regular-text"></td>
                </tr>
            </table>
            <?php submit_button('Simpan Pengaturan', 'primary', 'agrcr_save_settings'); ?>
        </form>
    </div>
    <?php
}

    // Halaman pengaturan Auto Generate Post
    function agrcr_display_settings_page() {
        ?>
        <div class="wrap">
            <h1>Pengaturan Auto Generate Post</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('agrcr_settings_group');
                do_settings_sections('agrcr_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    add_action('admin_init', 'agrcr_settings_init');

    function agrcr_settings_init() {
        register_setting('agrcr_settings_group', 'agrcr_settings');

        add_settings_section(
            'agrcr_settings_section',
            'Pengaturan Umum',
            'agrcr_settings_section_callback',
            'agrcr_settings'
        );

        add_settings_field(
            'agrcr_post_type',
            'Pilih Post Type',
            'agrcr_post_type_render',
            'agrcr_settings',
            'agrcr_settings_section'
        );
    }

    function agrcr_settings_section_callback() {
        echo 'Pilih post type untuk postingan otomatis.';
    }

    function agrcr_post_type_render() {
        $options = get_option('agrcr_settings');
        $selected_post_type = isset($options['agrcr_post_type']) ? $options['agrcr_post_type'] : 'post';
        $post_types = get_post_types(['public' => true], 'objects');

        ?>
        <select name="agrcr_settings[agrcr_post_type]">
            <?php foreach ($post_types as $post_type => $details) : ?>
                <option value="<?php echo esc_attr($post_type); ?>" <?php selected($selected_post_type, $post_type); ?>>
                    <?php echo esc_html($details->labels->singular_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Pilih post type untuk membuat postingan otomatis.</p>
        <?php
    }

 // Fungsi untuk membuat tabel database
function agrcr_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'google_review';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        nama text NOT NULL,
        foto text,
        ulasan text NOT NULL,
        rating float NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'agrcr_create_table');


    // Fungsi untuk mengecek tabel database pada plugin load
    function agrcr_check_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'google_review';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            agrcr_create_table();
        }
    }
    add_action('plugins_loaded', 'agrcr_check_table');

    // Fungsi untuk mengambil review Google
    function agrcr_get_google_reviews($max_reviews = 99, $min_rating = 1) {
        $api_key = get_option('agrcr_api_key');
        $place_id = get_option('agrcr_place_id');

        if (!$api_key || !$place_id) {
            return array();
        }

        $url = "https://maps.googleapis.com/maps/api/place/details/json?place_id=$place_id&fields=name,rating,reviews,photos&key=$api_key";
        $response = wp_remote_get($url);

        if (is_array($response) && !is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($data && isset($data['result']['reviews'])) {
                $reviews = $data['result']['reviews'];
                $filtered_reviews = array_filter($reviews, function($review) use ($min_rating) {
                    return $review['rating'] >= $min_rating;
                });

                global $wpdb;
                $table_name = $wpdb->prefix . 'google_review';

                foreach ($filtered_reviews as $review) {
                    $nama = $review['author_name'];
                    $foto = isset($review['profile_photo_url']) ? $review['profile_photo_url'] : '';
                    $ulasan = $review['text'];
                    $rating = $review['rating'];

                    $wpdb->insert(
                        $table_name,
                        array(
                            'nama' => $nama,
                            'foto' => $foto,
                            'ulasan' => $ulasan,
                            'rating' => $rating,
                        )
                    );
                }

                return array_slice($filtered_reviews, 0, $max_reviews);
            }
        }
        return array();
    }

// Shortcode untuk menampilkan Google Reviews
function agrcr_display_google_reviews($atts) {
    $atts = shortcode_atts(array(
        'min_rating' => 1,
        'max_reviews' => 1
    ), $atts);

    $reviews = agrcr_get_google_reviews((int)$atts['max_reviews'], (float)$atts['min_rating']);

    ob_start();
    ?>
    <div class="google-reviews">
        <?php if (!empty($reviews)) : ?>
            <ul>
                <?php foreach ($reviews as $review) : ?>
                    <li class="review-box">
                        <div class="review-details">
                            <strong><?php echo esc_html($review['author_name']); ?></strong>
                            <span><?php echo esc_html($review['text']); ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p>Tidak ada ulasan yang ditemukan.</p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('google_reviews', 'agrcr_display_google_reviews');
    ?>
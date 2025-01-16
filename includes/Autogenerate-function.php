<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function agrcr_get_reviews() {
    $api_key = get_option('agrcr_api_key');
    $place_id = get_option('agrcr_place_id');

    if (!$api_key || !$place_id) {
        return [];
    }

    $url = "https://maps.googleapis.com/maps/api/place/details/json?place_id=$place_id&fields=name,rating,reviews,photos&key=$api_key";
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        error_log('Google API Error: ' . $response->get_error_message());
        return [];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['result']['reviews']) && is_array($data['result']['reviews'])) {
        $reviews = $data['result']['reviews'];

        $filtered_reviews = array_filter($reviews, function ($review) {
            return $review['rating'] >= 4;
        });

        return $filtered_reviews;
    }

    return [];
}

function auto_generate_post_with_reviews() {
    $options = get_option('agrcr_settings');
    $post_type = isset($options['agrcr_post_type']) ? $options['agrcr_post_type'] : 'post';

    $reviews = agrcr_get_reviews();

    if (empty($reviews)) {
        return "Tidak ada ulasan untuk diposting.";
    }

    $content = '<ul>';
    foreach ($reviews as $review) {
        $content .= '<li><strong>' . esc_html($review['author_name']) . '</strong>: ' . esc_html($review['text']) . ' (Rating: ' . esc_html($review['rating']) . ')</li>';
    }
    $content .= '</ul>';

    $post_data = array(
        'post_type'     => $post_type,
        'post_title'    => 'Google Review Tanggal: ' . gmdate('Y-m-d'),
        'post_content'  => $content,
        'post_status'   => 'publish',
        'post_author'   => 1
    );

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        error_log('Post Creation Error: ' . $post_id->get_error_message());
        return "Gagal membuat post.";
    }

    return "Post berhasil dibuat dengan ID: $post_id";
}

function schedule_auto_generate_post() {
    if (!wp_next_scheduled('auto_generate_post_event')) {
        wp_schedule_event(time(), 'hourly', 'auto_generate_post_event');
    }
}
add_action('init', 'schedule_auto_generate_post');

function auto_generate_post_event_callback() {
    auto_generate_post_with_reviews();
}
add_action('auto_generate_post_event', 'auto_generate_post_event_callback');

function deactivate_auto_generate_post() {
    wp_clear_scheduled_hook('auto_generate_post_event');
}
register_deactivation_hook(__FILE__, 'deactivate_auto_generate_post');
?>
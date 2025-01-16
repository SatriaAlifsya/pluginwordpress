<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

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
    register_setting('agrcr_settings_group', 'agrcr_settings', array(
        'sanitize_callback' => 'agrcr_sanitize_settings'
    ));

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

function agrcr_sanitize_settings($input) {
    $output = array();
    $post_types = get_post_types(['public' => true], 'names');

    if (isset($input['agrcr_post_type']) && in_array($input['agrcr_post_type'], $post_types, true)) {
        $output['agrcr_post_type'] = sanitize_text_field($input['agrcr_post_type']);
    }

    return $output;
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
?>
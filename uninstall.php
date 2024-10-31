<?php
if( !defined( 'ABSPATH' ) && !defined('WP_UNINSTALL_PLUGIN') ) exit();

include(plugin_dir_path( __FILE__ ) . 'lib/rivio-api/rivio_api.php');

$rivio_api = new Rivio();

function rivio_get_default_settings(){
    return array( 'app_key' => '',
        'secret' => '',
        'widget_location' => 'footer',
        'language_code' => 'en',
        'widget_tab_name' => 'Reviews',
        'rating_stars_enabled_product' => true,
        'rating_stars_enabled_category' => true,
        'show_submit_past_orders' => true,
        'disable_default_review_system' => true,
        'default_star_ratings_enabled' => 'no');
}

$settings = get_option('rivio_settings', rivio_get_default_settings());

$api_key = $settings['app_key'];
$secret_key = $settings['secret'];

$response = $rivio_api->uninstall($api_key, $secret_key);

delete_option('rivio_settings');
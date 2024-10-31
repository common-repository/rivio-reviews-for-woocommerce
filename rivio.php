<?php
/*
	Plugin Name: Rivio for Woocommerce
	Description: Rivio for Woocommerce description
	Author: Nomo Bt.
	Version: 1.4.0
	Author URI: http://nomosolutions.com/
	Plugin URI: http://getrivio.com/for-woocommerce
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/*register_activation_hook(   __FILE__, 'rivio_activation' );*/
register_activation_hook(   __FILE__, 'rivio_plugin_active');
register_deactivation_hook( __FILE__, 'rivio_deactivate' );
register_uninstall_hook( __FILE__, 'rivio_uninstall' );

add_action('admin_init','load_plugin');
function load_plugin() {
    if(is_admin()&&get_option('Activated_Plugin')=='Plugin-Slug') {
        delete_option('Activated_Plugin');

        $rivio_api = new Rivio();

        $response = $rivio_api->activate();
    }
}
add_action('plugins_loaded', 'rivio_init');
add_action('init', 'rivio_redirect');

function rivio_plugin_active(){
    add_option('Activated_Plugin','Plugin-Slug');
}

function rivio_init(){
	if(is_admin()) {
        include(plugin_dir_path( __FILE__ ) . 'lib/rivio-api/rivio_api.php');
		include( plugin_dir_path( __FILE__ ) . 'templates/rivio_settings.php');
		add_action( 'admin_menu', 'rivio_admin_settings' );
	}

	$rivio_settings = get_option('rivio_settings', rivio_get_default_settings());

	if(!empty($rivio_settings['app_key']) && rivio_compatible()){
		if(!is_admin()) {
			add_action( 'wp_enqueue_scripts', 'rivio_load_js' );
			add_action( 'template_redirect', 'rivio_front_end_init' );
		}
	}
}

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

function rivio_redirect(){
	if ( get_option('rivio_just_installed', false)){
		delete_option('rivio_just_installed');
		wp_redirect( ( ( is_ssl() || force_ssl_admin() || force_ssl_login() ) ? str_replace( 'http:', 'https:', admin_url( 'admin.php?page=woocommerce-rivio-settings-page' ) ) : str_replace( 'https:', 'http:', admin_url( 'admin.php?page=woocommerce-rivio-settings-page' ) ) ) );
		exit;
	}
}

function rivio_front_end_init(){

    $settings = get_option('rivio_settings',rivio_get_default_settings());

    if(is_product()) {

        $widget_location = $settings['widget_location'];
        if($settings['disable_default_review_system']){
            add_filter( 'comments_open', 'rivio_remove_native_review_system', null, 2);
        }

        if($widget_location == 'tab'){
            add_action('woocommerce_product_tabs', 'rivio_show_widget_in_tab');
        }elseif($widget_location == 'footer'){
            add_action('woocommerce_after_single_product', 'rivio_show_widget');
        }

        if($settings['rating_stars_enabled_product']){
            add_action('woocommerce_single_product_summary', 'rivio_show_stars_widget',7);
        }
    }
    elseif ($settings['rating_stars_enabled_category']){
        add_action('woocommerce_after_shop_loop_item_title', 'rivio_show_stars_widget',7);
    }
}

function rivio_admin_settings(){
	add_action( 'admin_enqueue_scripts', 'rivio_admin_styles' );
	add_menu_page( 'Rivio', 'Rivio', 'manage_options', 'woocommerce-rivio-settings-page', 'display_rivio_admin_page', 'none', null );
}

function rivio_activation(){

	if(current_user_can( 'activate_plugins' )){

		update_option('rivio_just_installed', true);

	    $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';

    	check_admin_referer( "activate-plugin_{$plugin}" );

		$default_settings = get_option('rivio_settings', false);

		if(!is_array($default_settings)){
			add_option('rivio_settings', rivio_get_default_settings());
		}

		update_option('default_star_ratings_enabled', get_option('woocommerce_enable_review_rating'));
		update_option('woocommerce_enable_review_rating', 'no');
	}
}

function rivio_uninstall(){
	if((__FILE__ == WP_UNINSTALL_PLUGIN) && (current_user_can( 'activate_plugins' ))){

		check_admin_referer( 'bulk-plugins' );
		delete_option('rivio_settings');

	}
}

function rivio_show_widget(){

    $api_key = get_option('rivio_settings', rivio_get_default_settings());
    $api_key = $api_key['app_key'];

	$product = get_product();

	if($product->post->comment_status == 'open'){

		$product_data = rivio_get_product_data($product);
        $product_price = get_post_meta( get_the_ID(), '_regular_price');
        $product_price = $product_price[0];
        $product_category = get_the_terms( get_the_ID(), 'product_cat' );

        $rivio_embed = '<div class="reevio"
                        data-reevio-api-key="'.$api_key.'"
                        data-reevio-product-id="'.$product_data['id'].'"
                        data-reevio-name="'.$product_data['title'].'"
                        data-reevio-lang="'.$product_data['lang'].'"
                        data-reevio-url="'.$product_data['url'].'"
                        data-reevio-image-url="'.$product_data['image-url'].'"
                        data-reevio-description="'.$product_data['description'].'"
                        data-reevio-type="'.$product_category[0]->name.'"
                        data-reevio-price="'.$product_price.'">
                    </div>';

		echo $rivio_embed;
	}
}

function rivio_show_widget_in_tab($tabs){

	$product = get_product();

	if($product->post->comment_status == 'open'){
		$settings = get_option('rivio_settings', rivio_get_default_settings());
	 	$tabs['rivio_widget'] = array(
	 	'title' => $settings['widget_tab_name'],
	 	'priority' => 50,
	 	'callback' => 'rivio_show_widget'
	 	);
	}
	return $tabs;
}

function fetchUrl($url) {
    $allowUrlFopen = preg_match('/1|yes|on|true/i', ini_get('allow_url_fopen'));
    if ($allowUrlFopen) {
        return file_get_contents($url);
    } elseif (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        $contents = curl_exec($c);
        curl_close($c);
        if (is_string($contents)) {
            return $contents;
        }
    }
    return false;
}

function rivio_get_product_rating($product_id){
    // Get API key
    $rivio_settings = get_option('rivio_settings', rivio_get_default_settings());
    $rivio_api_key = $rivio_settings['app_key'];



    $result = fetchUrl("https://api.getrivio.com/api/review/product-ratings?api_key=".$rivio_api_key."&product_ids=".intval($product_id));

    $json_result = json_decode($result,true);
    if ($json_result === null) {
        throw new Exception('Server responded with invalid json format');
    } else {
        return $json_result[0];
    }
}

function rivio_load_js(){
	if(rivio_is_who_commerce_installed()){

    	wp_enqueue_script('rivio_init', plugins_url('assets/js/init.js', __FILE__) ,null,null);
		$settings = get_option('rivio_settings',rivio_get_default_settings());
		wp_localize_script('rivio_init', 'rivio_settings', array('app_key' => $settings['app_key']));
	}
}

function rivio_is_who_commerce_installed(){

	return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));

}

function rivio_get_plan() {

    $rivio_settings = get_option('rivio_settings', rivio_get_default_settings());
    $app_key = $rivio_settings['app_key'];
    $businessData =  fetchUrl("https://api.getrivio.com/api/business/widgetdata?api_key=" . $app_key);

    if(!$businessData){
        return false;
    }

    $businessData = json_decode($businessData, true);

    return $businessData['plan'];
}

function rivio_show_stars_widget(){

	$product = get_product();
	$showrating_stars = is_product() ? $product->post->comment_status == 'open' : true;

	if($showrating_stars) {


        $product_data = rivio_get_product_data($product);

        //$business_plan = rivio_get_plan();
        //if ($business_plan == 'monthly_premium' || $business_plan == 'monthly_premium_pro') {
        if(false){
            $product_rating = rivio_get_product_rating("1889760964"); //$product_data['id']

            $average = round($product_rating['avg'] * 2)/2;
            $average_mod = round($product_rating['avg'] * 2)%2;

            $count = $product_rating['count'];

            $pluralized_review = 'review';

            if ($count > 0) {
                $pluralized_review = 'reviews';
            }

            $rivio_stars = '<div data-rivio-stars-widget-gs-product-id="'. $product_data['id'].'" class="rivio-stars-widget-gs">
                                <span class="rivio-stars-widget-gs-stars">';

            $star_svgs = '';

            for ($i = 1; $i <= 5; $i++) {
                if ($average <= $i) {
                    if ($average_mod == 1){
                        //empty
                        $star_svgs .= '<span><svg viewBox="0 0 32 32" height="20" width="18" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns="http://www.w3.org/2000/svg" version="1.1"><path d="M32 12.408l-11.056-1.607-4.944-10.018-4.944 10.018-11.056 1.607 8 7.798-1.889 11.011 9.889-5.199 9.889 5.199-1.889-11.011 8-7.798zM16 23.547l-0.029 0.015 0.029-17.837 3.492 7.075 7.807 1.134-5.65 5.507 1.334 7.776-6.983-3.671z" fill="#ffd200"/></svg></span>';
                        $average_mod = 0;
                    } else {
                        //half empty
                        $star_svgs .= '<span><svg viewBox="0 0 32 32" height="20" width="18" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns="http://www.w3.org/2000/svg" version="1.1"><path d="M24.539 19.904c-0.264 0.464-0.381 1.041-0.312 1.533l1.392 8.324-7.238-3.909c-0.561-0.295-1.505-0.295-2.070 0l-7.256 3.937 1.406-8.353c0.098-0.647-0.167-1.476-0.631-1.941l-5.906-5.906 8.142-1.223c0.661-0.098 1.363-0.619 1.645-1.209l3.643-7.537 3.628 7.537c0.207 0.45 0.66 0.844 1.149 1.069 0.155 0.070 0.322 0.127 0.496 0.141l8.14 1.223-5.904 5.906c-0.128 0.113-0.228 0.253-0.325 0.408zM32.582 14.111c0.366-0.366 0.492-0.816 0.352-1.237-0.143-0.422-0.508-0.703-1.014-0.787l-9.054-1.35c-0.113-0.014-0.352-0.183-0.409-0.295l-4.048-8.437c-0.225-0.464-0.616-0.731-1.055-0.731-0.45 0-0.844 0.267-1.069 0.731l-4.050 8.437c-0.056 0.113-0.295 0.281-0.408 0.295l-9.056 1.35c-0.506 0.084-0.872 0.366-1.012 0.787s-0.014 0.872 0.352 1.237l6.553 6.553c0.098 0.098 0.183 0.366 0.167 0.506l-1.545 9.267c-0.072 0.408 0.014 0.773 0.239 1.041 0.336 0.394 0.955 0.478 1.476 0.197l8.099-4.359c0.014-0.014 0.098-0.028 0.253-0.028 0.141 0 0.225 0.014 0.239 0.028l8.099 4.359c0.211 0.113 0.439 0.183 0.661 0.183 0.322 0 0.619-0.141 0.816-0.38 0.224-0.267 0.311-0.633 0.256-1.041l-1.561-9.267c-0.014-0.141 0.072-0.408 0.17-0.506l6.548-6.553z" fill="#ffd200"/></svg></span>';
                    }
                } else {
                    //full
                    $star_svgs .= '<span><svg viewBox="0 0 32 32" height="20" width="18" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns="http://www.w3.org/2000/svg" version="1.1"><path d="M32 12.408l-11.056-1.607-4.944-10.018-4.944 10.018-11.056 1.607 8 7.798-1.889 11.011 9.889-5.199 9.889 5.199-1.889-11.011 8-7.798z" fill="#ffd200"/></svg></span>';
                }
            }

            $rivio_stars .= $star_svgs;


            $rivio_stars_bottom = '<span class="rivio-stars-widget-gs-label">
                                    <span itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating"><span class="rivio-stars-widget-gs-span-reviews_average-c" itemprop="ratingValue">' . $product_rating['avg'] . '</span>
                                        <a class="rivio-stars-widget-gs-a" href="javascript:">
                                            by <span itemprop="reviewCount">' . $count . '</span>' . $pluralized_review . '
                                        </a>
                                    </span>
                                  </span>';

            if ($product_rating['avg'] == 0 && $product_rating['count'] == 0) {
                $rivio_stars_bottom = '<span class="rivio-stars-widget-gs-label">
                                     <span><span class="rivio-stars-widget-gs-span-reviews_average-c" >0</span>
                                         <a class="rivio-stars-widget-gs-a" href="javascript:">
                                            by <span>0</span> reviews
                                         </a>
                                     </span>
                                </span>';
            }

            $rivio_stars .= $rivio_stars_bottom;

            $rivio_stars .= '</span></div>';

        } else {

            $rivio_stars = '<div class="rivio-stars-widget" data-rivio-stars-widget-product-id="' . $product_data['id'] . '"></div>';

        }

        echo $rivio_stars;

    }
}

function rivio_get_product_data($product){

	$product_data_array = array();

	$settings = get_option('rivio_settings',rivio_get_default_settings());

	$product_data_array['app_key'] = $settings['app_key'];
	$product_data_array['shop_domain'] = rivio_get_shop_domain();
	$product_data_array['url'] = get_permalink($product->id);
	$product_data_array['lang'] = $settings['language_code'];

	if($settings['rivio_language_as_site'] == true){

		$lang = explode('-', get_bloginfo('language'));

		if(strlen($lang[0]) == 2){
			$product_data_array['lang'] = $lang[0];
		}

	}

	$product_data_array['description'] = strip_tags($product->get_post_data()->post_excerpt);
	$product_data_array['id'] = $product->id;
	$product_data_array['title'] = $product->get_title();
	$product_data_array['image-url'] = rivio_get_product_image_url($product->id);

	return $product_data_array;
}

function rivio_get_shop_domain(){
	return parse_url(get_bloginfo('url'), PHP_URL_HOST);
}

function rivio_remove_native_review_system($open, $post_id){

	if(get_post_type($post_id) == 'product') {
		return false;
	}

	return $open;
}

function rivio_get_single_map_data($order_id){

	$order = new WC_Order($order_id);

	$data = null;

	if(!is_null($order->id)) {

		$data = array();
		$data['order_date'] = $order->order_date;
		$data['email'] = $order->billing_email;
		$data['customer_name'] = $order->billing_first_name.' '.$order->billing_last_name;
		$data['order_id'] = $order_id;
		$data['currency_iso'] = rivio_get_order_currency($order);

		$products_array = array();

		foreach ($order->get_items() as $product){
			$product_instance = get_product($product['product_id']);

			$description = '';

			if (is_object($product_instance)){
				$description = strip_tags($product_instance->get_post_data()->post_excerpt);
			}

			$product_data_array = array();
			$product_data_array['url'] = get_permalink($product['product_id']);
			$product_data_array['name'] = $product['name'];
			$product_data_array['image'] = rivio_get_product_image_url($product['product_id']);
			$product_data_array['description'] = $description;
			$product_data_array['price'] = $product['line_total'];

			$products_array[$product['product_id']] = $product_data_array;
		}

		$data['products'] = $products_array;
	}

	return $data;
}

function rivio_get_product_image_url($product_id){

	$url = wp_get_attachment_url(get_post_thumbnail_id($product_id));

	return $url ? $url : null;
}

function rivio_get_past_orders(){

	$result = null;
	$args = array(
		'post_type'		 => 'shop_order',
		'posts_per_page' => -1
	);

	if(defined('WC_VERSION') && (version_compare(WC_VERSION, '2.2.0') >= 0)){
		$args['post_status'] = 'wc-completed';
	} else{
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'shop_order_status',
				'field'    => 'slug',
				'terms'    => array('completed'),
				'operator' => 'IN'
			)
		);
	}

	add_filter( 'posts_where', 'rivio_past_order_time_query' );
	$query = new WP_Query( $args );
	remove_filter( 'posts_where', 'rivio_past_order_time_query' );
	wp_reset_query();
	if ($query->have_posts()) {

		$orders = array();

		while ($query->have_posts()){
			$query->the_post();
			$order = $query->post;
			$single_order_data = rivio_get_single_map_data($order->ID);
			if(!is_null($single_order_data)) {
				$orders[] = $single_order_data;
			}
		}

		if(count($orders) > 0){
			$post_bulk_orders = array_chunk($orders, 200);

            $result = array();

			foreach ($post_bulk_orders as $index => $bulk){
				$result[$index] = array();
				$result[$index]['orders'] = $bulk;
				$result[$index]['platform'] = 'woocommerce';
			}
		}
	}

	return $result;
}

function rivio_send_past_orders(){

    $rivio_settings = get_option('rivio_settings', rivio_get_default_settings());

    $rivio_api_key = $rivio_settings['app_key'];
    $rivio_secret_key = $rivio_settings['secret'];

    $rivio = new Rivio($rivio_api_key,$rivio_secret_key);

    $orders = rivio_get_past_orders();

    $orders_to_save = array();

    for ($i=0; $i < sizeof($orders[0]['orders']); $i++){

        $order_id = $orders[0]['orders'][$i]['order_id'];
        $order_date = $orders[0]['orders'][$i]['order_date'];
        $customer_email = $orders[0]['orders'][$i]['email'];
        $customer_name = $orders[0]['orders'][$i]['customer_name'];

        foreach($orders[0]['orders'][$i]['products'] as $value){

            $product_id = key($orders[0]['orders'][$i]['products']);
            $product_name = $value['name'];
            $product_url = $value['url'];
            $product_image_url = $value['image'];
            $product_description = $value['description'];
            $product_barcode = '';
            $product_category = '';
            $product_brand = '';
            $product_price = $value['price'];
            break;

        }

        $orders_to_save[$i] = array();
        $orders_to_save[$i]['order_id'] = $order_id;
        $orders_to_save[$i]['ordered_date'] = $order_date;
        $orders_to_save[$i]['customer_email'] = $customer_email;
        $orders_to_save[$i]['customer_first_name'] = $customer_name;
        $orders_to_save[$i]['shopitem_id'] = $product_id;
        $orders_to_save[$i]['product_id'] = $product_id;
        $orders_to_save[$i]['product_url'] = $product_url;
        $orders_to_save[$i]['product_image_url'] = $product_image_url;
        $orders_to_save[$i]['product_description'] = $product_description;
        $orders_to_save[$i]['product_barcode'] = $product_barcode;
        $orders_to_save[$i]['product_name'] = $product_name;
        $orders_to_save[$i]['product_category'] = $product_category;
        $orders_to_save[$i]['product_brand'] = $product_brand;
        $orders_to_save[$i]['product_price'] = $product_price;

    }

    $result = $rivio->register_postpurchase_emails($orders_to_save);

    return $result;

}

// get orders from the last 10 days
function rivio_past_order_time_query( $where = '' ){

	$where .= " AND post_date > '" . date('Y-m-d', strtotime('-10 days')) . "'";

    return $where;
}

function rivio_admin_styles($hook){
	if($hook == 'toplevel_page_woocommerce-rivio-settings-page'){
		wp_enqueue_script( 'rivioSettingsJs', plugins_url('assets/js/settings.js', __FILE__), array('jquery-effects-core'));
		wp_enqueue_style( 'rivioSettingsStylesheet', plugins_url('assets/css/rivio.css', __FILE__));
	}

	wp_enqueue_style('rivioSideLogoStylesheet', plugins_url('assets/css/side-menu-logo.css', __FILE__));
}

function rivio_compatible(){
	return version_compare(phpversion(), '5.2.0') >= 0 && function_exists('curl_init');
}

function rivio_deactivate(){
	update_option('woocommerce_enable_review_rating', get_option('default_star_ratings_enabled'));
}

add_filter('woocommerce_tab_manager_integration_tab_allowed', 'rivio_disable_tab_manager_managment');

function rivio_disable_tab_manager_managment($allowed, $tab = null) {

	if($tab == 'rivio_widget') {

		$allowed = false;
		return false;
	}

}

function rivio_get_order_currency($order){

	if(is_null($order) || !is_object($order)){
		return '';
	}

	if(method_exists($order,'get_order_currency')) {
		return $order->get_order_currency();
	}

	if(isset($order->order_custom_fields) && isset($order->order_custom_fields['_order_currency'])){
 		if(is_array($order->order_custom_fields['_order_currency'])){
 			return $order->order_custom_fields['_order_currency'][0];
 		}
	}
	return '';
}
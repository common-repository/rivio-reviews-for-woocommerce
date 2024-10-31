<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function display_rivio_admin_page() {

	if ( function_exists('current_user_can') && !current_user_can('manage_options') ) {
		die(__(''));
	}

	if(rivio_compatible()) {
		if (isset($_POST['log_in_button']) ) {
			display_rivio_settings();
		}
        elseif (isset($_POST['rivio_past_orders'])) {
            rivio_send_past_orders();
            display_rivio_settings();
        }
        elseif (isset($_POST['rivio_settings'])) {
			check_admin_referer( 'rivio_settings_form' );
			proccess_rivio_settings();
			display_rivio_settings();
		}
		elseif (isset($_POST['rivio_register'])) {
			check_admin_referer( 'rivio_registration_form' );
			$success = proccess_rivio_register();
			if($success) {			
				display_rivio_settings($success);
			}
			else {
				display_rivio_register();
			}
			
		}
		else {
			$rivio_settings = get_option('rivio_settings', rivio_get_default_settings());
			if(empty($rivio_settings['app_key']) && empty($rivio_settings['secret'])) {
				display_rivio_register();
			}
			else {
				display_rivio_settings();
			}
		}
	}
	else {
		if(version_compare(phpversion(), '5.2.0') < 0) {
			echo '<h1>Rivio plugin requires PHP 5.2.0 above.</h1><br>';
		}	
		if(!function_exists('curl_init')) {
			echo '<h1>Rivio plugin requires cURL library.</h1><br>';
		}			
	}
}

function display_rivio_settings($success_type = false) {
	$rivio_settings = get_option('rivio_settings', rivio_get_default_settings());
	$app_key = $rivio_settings['app_key'];
	$secret = $rivio_settings['secret'];
	$widget_tab_name = $rivio_settings['widget_tab_name'];

	if(empty($rivio_settings['app_key'])) {
	    if ($success_type == 'b2c') {
            rivio_display_message('We have sent you a confirmation email. Please check and click on the link to get your app key and secret token to fill out below.', true);
        } else {
		rivio_display_message('Set your API key and SECRET key in order the Rivio plugin to work correctly', false);
		}
	}

	if (!empty($rivio_settings['app_key']) && !empty($rivio_settings['secret'])) {
		$dashboard_link = '<a href="http://dashboard.getrivio.com" target="_blank">Rivio Dashboard.</a></div>';
	}
	else {
		$dashboard_link = "<a href='http://dashboard.getrivio.com/registration' target='_blank'>Rivio Dashboard.</a></div>";
	}
	$read_only = isset($_POST['log_in_button']) || $success_type == 'b2c' ? '' : 'readonly';
	$cradentials_location_explanation = isset($_POST['log_in_button']) 	? "<tr valign='top'>  	
		             														<th scope='row'><p class='description'>To get your api key and secret token <a href='https://www.rivio.com/?login=true' target='_blank'>log in here</a> and go to your account settings.</p></th>
	                 		                  							   </tr>" : '';

	$submit_past_orders_button = $rivio_settings['show_submit_past_orders'] ? "<input type='submit' name='rivio_past_orders' value='Submit past orders' class='rivio-btn rivio-btn-default' ".disabled(true,empty($app_key) || empty($secret), false).">" : '';

    $loggedInTemplate = ' <div class="col col-8">
                              <div class="rivio-form-value">
                                  <a target="javascript:" class="rivio-btn rivio-btn-default rivio-show-secret">
                                      <i>&#x1f512;</i> Show secret key
                                  </a>
                                  <input  class="rivio-secret"  type="text" name="rivio_oauth_token" value="{{secret_key}}" {{read_only}}>
                              </div>
                          </div>';

    $notLoggedInTemplate = '<div class="col col-8">
                                <div class="rivio-form-value">
                                    <input type="text" name="rivio_oauth_token" value="{{secret_key}}" {{read_only}}>
                                </div>
                            </div>';

    $settings_html = file_get_contents(dirname(__FILE__)."/rivio_settings.partial.html");

    if (!empty($rivio_settings['app_key']) && !empty($rivio_settings['secret'])) {
        $settings_html = preg_replace('/\{\{secret_key_block\}\}/',$loggedInTemplate,$settings_html);
    } else{
        $settings_html = preg_replace('/\{\{secret_key_block\}\}/',$notLoggedInTemplate,$settings_html);
    }

    $settings_html = preg_replace('/\{\{widget_tab_name\}\}/',$widget_tab_name,$settings_html);
    $settings_html = preg_replace('/\{\{wp_nonce_field\}\}/',wp_nonce_field('rivio_settings_form'),$settings_html);
    $settings_html = preg_replace('/\{\{api_key\}\}/',$app_key,$settings_html);
    $settings_html = preg_replace('/\{\{secret_key\}\}/',$secret,$settings_html);
    $settings_html = preg_replace('/\{\{submit_past_orders\}\}/',$submit_past_orders_button,$settings_html);
    $settings_html = preg_replace('/\{\{location_footer\}\}/',checked('footer', $rivio_settings['widget_location'], false),$settings_html);
    $settings_html = preg_replace('/\{\{location_tab\}\}/',checked('tab', $rivio_settings['widget_location'], false),$settings_html);
    $settings_html = preg_replace('/\{\{location_other\}\}/',checked('other', $rivio_settings['widget_location'], false),$settings_html);
    $settings_html = preg_replace('/\{\{plugin_url\}\}/',WP_PLUGIN_URL."/rivio-reviews-for-woocommerce",$settings_html);

    if(isset($_POST['rivio_settings'])){
        $settings_html = preg_replace('/\{\{update\}\}/','active',$settings_html);
    } else{
        $settings_html = preg_replace('/\{\{update\}\}/','',$settings_html);
    }

    if($rivio_settings['widget_location'] == 'footer'){
        $settings_html = preg_replace('/\{\{active_footer\}\}/','active',$settings_html);
    } else{
        $settings_html = preg_replace('/\{\{active_footer\}\}/','',$settings_html);
    }

    if($rivio_settings['widget_location'] == 'tab'){
        $settings_html = preg_replace('/\{\{active_tab\}\}/','active',$settings_html);
    } else{
        $settings_html = preg_replace('/\{\{active_tab\}\}/','',$settings_html);
    }

    if($rivio_settings['widget_location'] == 'other'){
        $settings_html = preg_replace('/\{\{active_other\}\}/','active',$settings_html);
    } else{
        $settings_html = preg_replace('/\{\{active_other\}\}/','',$settings_html);
    }

    $settings_html = preg_replace('/\{\{show_product\}\}/',checked(1, $rivio_settings['rating_stars_enabled_product'], false),$settings_html);
    $settings_html = preg_replace('/\{\{show_category\}\}/',checked(1, $rivio_settings['rating_stars_enabled_category'], false),$settings_html);
    $settings_html = preg_replace('/\{\{disable_default\}\}/',checked(1, $rivio_settings['disable_default_review_system'], false),$settings_html);

    $settings_html = preg_replace('/\{\{read_only\}\}/',$read_only,$settings_html);
	echo $settings_html;
}

function proccess_rivio_settings() {
	$current_settings = get_option('rivio_settings', rivio_get_default_settings());
	$new_settings = array('app_key' => $_POST['rivio_app_key'],
						 'secret' => $_POST['rivio_oauth_token'],
						 'widget_location' => $_POST['rivio_widget_location'],
						 'language_code' => $_POST['rivio_widget_language_code'],
						 'widget_tab_name' => $_POST['rivio_widget_tab_name'],
						 'rating_stars_enabled_product' => isset($_POST['rivio_rating_stars_enabled_product']) ? true : false,
						 'rating_stars_enabled_category' => isset($_POST['rivio_rating_stars_enabled_category']) ? true : false,
						 'rivio_language_as_site' => isset($_POST['rivio_language_as_site']) ? true : false,
						 'disable_default_review_system' => isset($_POST['disable_default_review_system']) ? true : false,
						 'show_submit_past_orders' => $current_settings['show_submit_past_orders']);
	update_option( 'rivio_settings', $new_settings );
	if($current_settings['disable_default_review_system'] != $new_settings['disable_default_review_system']) {
		if($new_settings['disable_default_review_system'] == false) {
			update_option( 'woocommerce_enable_review_rating', get_option('default_star_ratings_enabled'));
		}			
		else {
			update_option( 'woocommerce_enable_review_rating', 'no');
		}
	}

    if(($current_settings['app_key'] != $new_settings['app_key']) && ($current_settings['secret'] != $new_settings['secret'])){
        $rivio_api = new Rivio();
        $rivio_api->reinstall($new_settings['app_key'], $new_settings['secret']);
    }
}

function display_rivio_register() {
	$email = isset($_POST['rivio_user_email']) ? $_POST['rivio_user_email'] : '';
	$user_name = isset($_POST['rivio_user_name']) ? $_POST['rivio_user_name'] : '';

  $register_html = file_get_contents(dirname(__FILE__)."/rivio_registration.partial.html");

  $register_html = preg_replace('/\{\{user_name\}\}/',$user_name, $register_html);
  $register_html = preg_replace('/\{\{email\}\}/',$email,$register_html);
  $register_html = preg_replace('/\{\{wp_nonce_field\}\}/',wp_nonce_field('rivio_registration_form'),$register_html);

  echo $register_html;		 
}

function proccess_rivio_register() {

	$errors = array();
	if ($_POST['rivio_user_email'] === '') {
		array_push($errors, 'Enter a valid email address');
	}		
	if (strlen($_POST['rivio_user_password']) < 6 || strlen($_POST['rivio_user_password']) > 128) {
		array_push($errors, 'Password must be at least 6 characters');
	}			
	if ($_POST['rivio_user_password'] != $_POST['rivio_user_confirm_password']) {
		array_push($errors, 'Passwords are mismatch');
	}
	if ($_POST['rivio_user_name'] === '') {
		array_push($errors, 'Enter a username');
	}		
	if(count($errors) == 0) {		
		$rivio_api = new Rivio();
		$shop_url = get_bloginfo('url');
        $currency = get_woocommerce_currency();

        $user = array(
            'name' => $_POST['rivio_user_name'],
            'email' => $_POST['rivio_user_email'],
            'password' => $_POST['rivio_user_password'],
            'retypepassword' => $_POST['rivio_user_password'],
            'website_name' => $shop_url,
            'business_platform' => 'Woocommerce',
            'privacy' => '1',
            'business_domain' => $shop_url,
            'currency' => $currency);
        try {        	        	
        	$response = $rivio_api->user_registration($user, true);

            if($response){
                $app_key = $response['api_key'];
                $secret = $response['secret'];

                $current_settings = get_option('rivio_settings', rivio_get_default_settings());
                $current_settings['app_key'] = $app_key;
                $current_settings['secret'] = $secret;
                update_option('rivio_settings', $current_settings);
                return true;
            }

        }
        catch (Exception $e) {
        	rivio_display_message($e->getMessage(), true);
        }         		
	}
	else {
		rivio_display_message($errors, false);
	}	
	return false;		
}

function rivio_display_message($messages = array(), $is_error = false) {
	$class = $is_error ? 'error' : 'updated fade';
	if(is_array($messages)) {
		foreach ($messages as $message) {
			echo "<div id='message' class='$class'><p><strong>$message</strong></p></div>";
		}
	}
	elseif(is_string($messages)) {
		echo "<div id='message' class='$class'><p><strong>$messages</strong></p></div>";
	}
}
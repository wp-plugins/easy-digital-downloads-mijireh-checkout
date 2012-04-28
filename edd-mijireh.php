<?php
/*
Plugin Name: Easy Digital Downloads - Mijireh Checkout
Plugin URL: http://easydigitaldownloads.com/extension/mijireh-checkout
Description: Adds an integration for mijireh.com
Version: 1.0.1
Author: Benjamin Rojas
Author URI: http://benjaminrojas.net
Contributors: benjaminprojas
*/

if(!defined('EMIJ_PLUGIN_DIR')) {
	define('EMIJ_PLUGIN_DIR', dirname(__FILE__));
}

if(!defined('EMIJ_PLUGIN_URL')) {
	define('EMIJ_PLUGIN_URL', WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)));
}

// Register activation hook to install page slurp page
function emij_install_slurp_page() {
  if(!get_page_by_path('purchase-download/mijireh-secure-checkout')) {
    $parent = get_page_by_path('purchase-download');
    $page = array(
      'post_title' => 'Mijireh Secure Checkout',
      'post_name' => 'mijireh-secure-checkout',
      'post_parent' => $parent->ID,
      'post_status' => 'private',
      'post_type' => 'page',
      'comment_status' => 'closed',
      'ping_status' => 'closed',
      'post_content' => "<h1>Checkout</h1>\n\n{{mj-checkout-form}}",
    );
    wp_insert_post($page);
  }
}
register_activation_hook(__FILE__, 'emij_install_slurp_page');

function emij_remove_slurp_page() {
  $force_delete = true;
  $post = get_page_by_path('purchase-download/mijireh-secure-checkout');
  wp_delete_post($post->ID, $force_delete);
}
register_uninstall_hook(__FILE__, 'emij_remove_slurp_page');

// registers the gateway
function emij_register_mijireh_gateway($gateways) {
	// Format: ID => Name
	$gateways['mijireh'] = array('admin_label' => __('Mijireh', 'emij'), 'checkout_label' => __('Credit Card', 'emij'));
	return $gateways;
}
add_filter('edd_payment_gateways', 'emij_register_mijireh_gateway');

function emij_mijireh_remove_cc_form() {
	// we only register the action so that the default CC form is not shown
}
add_action('edd_mijireh_cc_form', 'emij_mijireh_remove_cc_form');

function emij_process_payment($purchase_data) {
  global $edd_options;
	
	require(EMIJ_PLUGIN_DIR . '/mijireh/MijirehGateway.php');
	
	$credentials = emij_api_credentials();
	foreach($credentials as $cred) {
    if(is_null($cred)) {
      edd_set_error(0, __('You must enter your Mijireh Access Key in settings', 'emij'));
      edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
    }
  }
	
	$payment_data = array( 
		'price' => $purchase_data['price'], 
		'date' => $purchase_data['date'], 
		'user_email' => $purchase_data['post_data']['edd_email'],
		'purchase_key' => $purchase_data['purchase_key'],
		'currency' => $edd_options['currency'],
		'downloads' => $purchase_data['downloads'],
		'cart_details' => $purchase_data['cart_details'],
		'user_info' => $purchase_data['user_info'],
		'status' => 'pending'
	);
	
	// record the pending payment
	$payment = edd_insert_payment($payment_data);
	//$payment = true;
	if($payment) {
	  $mijireh = new MijirehGateway();
	  $listener_url = add_query_arg('order', 'mijireh', get_permalink($edd_options['success_page']));
	  $mijireh->purchase_data(array(
		  'credentials' => array(
			'mijireh_access_key' => $credentials['mijireh_access_key'],
			'listener_url' => $listener_url
		  ),
		  'price' => $purchase_data['price'],
		  'currency_code' => $edd_options['currency'],
		  'cart_details' => $purchase_data['cart_details'],
		  'discount' => $purchase_data['user_info']['discount'],
		  'post_data' => $purchase_data['post_data'],
		  'payment_id' => $payment
		));
		try {
		  $redirect = $mijireh->get_redirect_url();
		}
		catch(Mijireh_Exception $e) {
		  echo $e->getMessage();
		}
		// Redirect to Mijireh
		wp_redirect($redirect);
		exit;
		
	} else {
		// if errors are present, send the user back to the purchase page so they can be corrected
		edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
	}
}
add_action('edd_gateway_mijireh', 'emij_process_payment');

// adds the settings to the Payment Gateways section
function emij_add_settings($settings) {
  
  $emij_settings = array(
		array(
			'id' => 'mijireh_settings',
			'name' => '<strong>' . __('Mijireh Settings', 'emij') . '</strong>',
			'desc' => __('Configure your Mijireh Settings', 'emij'),
			'type' => 'header'
		),
		array(
			'id' => 'mijireh_access_key',
			'name' => __('Mijireh Access Key', 'emij'),
			'desc' => __('Enter your Mijireh Access Key', 'emij'),
			'type' => 'text',
			'size' => 'regular'
		)
	);
	
	return array_merge($settings, $emij_settings);
}
add_filter('edd_settings_gateways', 'emij_add_settings');

function emij_api_credentials() {
  global $edd_options;
  
  $mijireh_access_key = isset($edd_options['mijireh_access_key']) ? $edd_options['mijireh_access_key'] : null;
	
	$data = array(
	 'mijireh_access_key' => $mijireh_access_key
	);
	
	return $data;
}

// listens for a Mijireh order number and then processes the order information
function emij_listen_for_mijireh_order() {
	global $edd_options;
	$mijireh_access_key = isset($edd_options['mijireh_access_key']) ? $edd_options['mijireh_access_key'] : null;
	Mijireh::$access_key = $mijireh_access_key;
	
	if(isset($_GET['order']) && $_GET['order'] == 'mijireh' && isset($_GET['order_number'])) {
		
		$mj_order = new Mijireh_Order($_GET['order_number']);
		$payment_id = $mj_order->get_meta_value('payment_id');
		
		edd_update_payment_status($payment_id, 'publish');
		
	}
		
}
add_action('init', 'emij_listen_for_mijireh_order');

function emij_mijireh_page_slurp() {
  require(EMIJ_PLUGIN_DIR . '/mijireh/MijirehSlurp.php');
  $mijireh_slurp = new MijirehPageSlurp();
}
add_action('plugins_loaded', 'emij_mijireh_page_slurp', 0);

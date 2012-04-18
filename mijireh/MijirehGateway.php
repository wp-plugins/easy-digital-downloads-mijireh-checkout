<?php

class MijirehGateway extends Mijireh {
  
  protected $_purchase_data;
  
  public function __construct() {
    global $edd_options;
    Mijireh::$access_key = $edd_options['mijireh_access_key'];
  }
  
  public function purchase_data($data) {
    $this->_purchase_data = $data;
  }
  
  public function get_redirect_url() {
    global $edd_options;
        
    $mj_order = new Mijireh_Order();
    
    $cart_details = $this->_purchase_data['cart_details'];
    // add items to order
    $number = 0;
    $item_amount = array();
    foreach($cart_details as $c) {
      $mj_order->add_item($c['name'], $c['price'], 1, $c['item_number']);
      $item_amount[] = $c['price'];
      $number++;
    }
        
    $item_amount = array_sum($item_amount);
    $total_amount = $this->_purchase_data['price'];
    $discount = 0;
    if($item_amount > $total_amount) {
      $discount = $item_amount - $total_amount;
    }
    
    // set order name 
    $mj_order->first_name = $this->_purchase_data['post_data']['edd_first'];
    $mj_order->last_name = $this->_purchase_data['post_data']['edd_last'];
    $mj_order->email = $this->_purchase_data['post_data']['edd_email'];
    
    // set order totals
    $mj_order->total = $total_amount;
    $mj_order->tax = 0;
    $mj_order->discount = $discount;
    
    // add meta data to identify woocommerce order
    $mj_order->add_meta_data('payment_id', $this->_purchase_data['payment_id']);
    
    // Set URL for mijireh payment notificatoin
    $mj_order->return_url = $this->_purchase_data['credentials']['listener_url'];
    
    $mj_order->partner_id = 'edd';
    $mj_order->create();

    return $mj_order->checkout_url;
    
  }
  
}
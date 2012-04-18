<?php
class MijirehPageSlurp {
  
  public function __construct() {
    
    if(!class_exists('Mijireh')) {
      require 'Mijireh.php';
    }
    
    add_action('add_meta_boxes', array(&$this, 'add_page_slurp_meta'));
		add_action('wp_ajax_page_slurp', array(&$this, 'page_slurp'));
    global $edd_options;
    Mijireh::$access_key = $edd_options['mijireh_access_key'];
  }
  
  public function page_slurp() {
    $page = get_page($_POST['page_id']);
    $url = get_permalink($page->ID);
    wp_update_post(array('ID' => $page->ID, 'post_status' => 'publish'));
    try {
      $job_id = Mijireh::slurp($url);
    }
    catch(Mijireh_Exception $e) {
      echo $e->getMessage();
    }
    wp_update_post(array('ID' => $page->ID, 'post_status' => 'private'));
    echo $job_id;
    die;
  }
  
  public function add_page_slurp_meta() { 
    if($this->is_slurp_page()) {
      wp_enqueue_style('mijireh_css', EMIJ_PLUGIN_URL . '/mijireh/slurp/mijireh.css');
      wp_enqueue_script('pusher', 'https://d3dy5gmtp8yhk7.cloudfront.net/1.11/pusher.min.js', null, false, true);
      wp_enqueue_script('page_slurp', EMIJ_PLUGIN_URL . '/mijireh/slurp/page_slurp.js', array('jquery'), false, true);
      
      add_meta_box(  
        'slurp_meta_box', // $id  
        'Mijireh Page Slurp', // $title  
        array(&$this, 'draw_page_slurp_meta_box'), // $callback  
        'page', // $page  
        'normal', // $context  
        'high'); // $priority  
    }
  }

  public function is_slurp_page() {
    global $post;
    $is_slurp = false;
    if(isset($post) && is_object($post)) {
      $content = $post->post_content;
      if(strpos($content, '{{mj-checkout-form}}') !== false) {
        $is_slurp = true;
      }
    }
    return $is_slurp;
  }

  public function draw_page_slurp_meta_box($post) {
    echo "<div id='mijireh_notice' class='mijireh-info alert-message info' data-alert='alert'>";
    echo  "<div class='mijireh-logo'><img src='" . EMIJ_PLUGIN_URL . "/mijireh/images/mijireh-checkout-logo.png' alt='Mijireh Checkout Logo'></div>";
    echo  "<div class='mijireh-blurb'>";
    echo    "<h2>Slurp your custom checkout page!</h2>";
    echo    "<p>Get the page designed just how you want and when you're ready, click the button below and slurp it right up.</p>";
    echo    "<div id='slurp_progress' class='meter progress progress-info progress-striped active' style='display: none;'><div id='slurp_progress_bar' class='bar' style='width: 20%;'>Slurping...</div></div>";
    echo    "<p class='aligncenter'><a href='#' id='page_slurp' rel=". $post->ID ." class='button-primary'>Slurp This Page!</a></p>";
    echo    '<p class="aligncenter"><a class="nobold" href="' . Mijireh::preview_checkout_link() . '" id="view_slurp" target="_new">Preview Checkout Page</a></p>';
    echo  "</div>";
    echo  "</div>";
  }
  
}
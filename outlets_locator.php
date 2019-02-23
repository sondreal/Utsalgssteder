<?php
/*
Plugin Name: Utsalgssteder
Plugin URI: https://www.nettfrihet.no
Description: Store locator for Wordpress
Author: Sondre Dyrnes
Author URI: https://www.nettfrihet.no
Version: 0.21
Requires at least: 4.9
Tested up to: 5.1
Textdomain: utsalggssteder
*/

if ( ! defined( 'WPINC' ) ) { die("You wish"); }

// Enable database for all my sub stuff
global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$table_name = $wpdb->prefix . 'ol_outlets';




/* 
  *
  * INIT
  *
 */
add_action('admin_init', 'outlet_locator_settings_init' );
register_activation_hook( __FILE__, 'my_plugin_create_db' );
function my_plugin_create_db() {

  $sql = "CREATE TABLE $table_name (
  	`id` MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
  	`name` VARCHAR(255) NOT NULL,
  	`address` VARCHAR(255) NOT NULL,
  	`postalplace` VARCHAR(255) NOT NULL,
  	`country` VARCHAR(255) NOT NULL,
  	`formatted_address` VARCHAR(255) NOT NULL,
  	`geocoding` VARCHAR(20) NOT NULL,
  	`latitude` VARCHAR(10) NOT NULL,
  	`longitude` VARCHAR(10) NOT NULL,
  	`last_updated` DATETIME NOT NULL,
  	`products` TEXT NOT NULL,
  	`views` SMALLINT(5) NOT NULL,
  	`clicks` SMALLINT(5) NOT NULL,
  	UNIQUE KEY id (id)
  ) $charset_collate;";
  
  require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
  dbDelta( $sql );
}


// Load shortcode thingy 
require_once(__DIR__."/shortcode.php");


/* 
  *
  * AJAX
  *
 */
add_action("wp_ajax_ol_geolocate_id", "ol_geolocate_id");
add_action("wp_ajax_ol_geolocate_outlet", "ol_geolocate_outlet");
add_action("wp_ajax_nopriv_ol_map_search", "ol_map_search");
add_action("wp_ajax_ol_map_search", "ol_map_search");
add_action("wp_ajax_ol_detect_location", "ol_detect_location");
add_action("wp_ajax_ol_detect_location_save", "ol_detect_location_save");


function ol_detect_location_save() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'ol_outlets';
  
  $id = (int) $_POST['id'];
  $lat = floatval($_POST['lat']);
  $lng = floatval($_POST['lng']);
  $addr = esc_attr($_POST['addr']);
  
  $sql = "UPDATE $table_name SET latitude = '$lat', longitude = '$lng', formatted_address='$addr', last_updated=NOW(), geocoding='OK_MANUAL' WHERE id=".$id;
  echo "OK";
  $wpdb->query($sql);
  wp_die();
}

function ol_detect_location() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'ol_outlets';
  
  $search = $_POST['kw'];
  $return = new stdClass();
  
  $url = 'https://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($search).'&key='.esc_attr(get_option('google_api_key'));
  $return->url= $url;
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $api_response = json_decode(curl_exec($ch));  
  $return->status = $api_response->status;
  $return->response = $api_response->results;


  echo json_encode($return);
  wp_die();
}

function ol_geolocate_id() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'ol_outlets';

  $id = (int) $_POST['id'];
  $return = new stdClass();
  
  $sql = "SELECT name, address, postalplace, country FROM $table_name WHERE id=".$id;
  $res = $wpdb->get_results($sql);
  if($wpdb->num_rows != 1) {
    $return->status = "DB_ERROR_".$id;
    echo json_encode($return);;
    wp_die();
  }
  
  $name = $res[0]->name;
  $name = preg_replace("/avd\..\d*/i", "", $res[0]->name);
  $name = preg_replace("/kiwi \d*/i", "KIWI", $name);
  
  $search = $name." ".$res[0]->address." ".$res[0]->postalplace.", ".$res[0]->country;
  
  $url = 'https://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($search).'&key='.esc_attr(get_option('google_api_key'));

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $api_response = json_decode(curl_exec($ch));  
  $return->status = $api_response->status;
    
  if($api_response->status == "OK") {
    $lat = floatval($api_response->results[0]->geometry->location->lat);
    $lng = floatval($api_response->results[0]->geometry->location->lng);
    $addr = esc_attr($api_response->results[0]->formatted_address);
    $return->location = $api_response->results[0]->geometry->location;
    
    $sql = "UPDATE $table_name SET latitude = '$lat', longitude = '$lng', formatted_address='$addr', last_updated=NOW(), geocoding='OK' WHERE id=".$id;
    $wpdb->query($sql);
    
  }
  else {
    $sql = "UPDATE $table_name SET geocoding='".esc_attr($api_response->status)."' WHERE id=".$id;
    $wpdb->query($sql);
  }
  
  $return->datetime = date("Y-m-d H:i:s");
  echo json_encode($return);
  wp_die();
}


function ol_geolocate_outlet() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'ol_outlets';
  
  $sql = "SELECT id, name, address, postalplace, country FROM $table_name WHERE geocoding = '' LIMIT 5";
  $res = $wpdb->get_results($sql);
  
  $return = new stdClass();
  $return->failed = 0;
  $return->done = 0;
  
  if($wpdb->num_rows == 0) {
    $return->done = -1;
    echo json_encode($return);
    wp_die();
  }
  
  foreach($res as $k=>$v) {
    $id          = $v->id;
    $name        = $v->name;
    $address     = $v->address;
    $postalplace = $v->postalplace;
    $country     = $v->country;
    
    $name = preg_replace("/avd\..\d*/i", "", $name);
    $name = preg_replace("/kiwi \d*/i", "KIWI", $name);

    $search = $name." ".$address." ".$postalplace.", ".$country;  
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($search).'&key='.esc_attr(get_option('google_api_key'));
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $api_response = json_decode(curl_exec($ch));   
        
    if($api_response->status == "OK") {
      $lat = floatval($api_response->results[0]->geometry->location->lat);
      $lng = floatval($api_response->results[0]->geometry->location->lng);
      $addr = esc_attr($api_response->results[0]->formatted_address);
      $return->location = $api_response->results[0]->geometry->location;
      
      $sql = "UPDATE $table_name SET latitude = '$lat', longitude = '$lng', formatted_address='$addr', last_updated=NOW(), geocoding='OK' WHERE id=".$id;
      $wpdb->query($sql);
      $return->done ++;
      
    }
    else {
      $sql = "UPDATE $table_name SET geocoding='".esc_attr($api_response->status)."' WHERE id=".$id;
      $wpdb->query($sql);
      $return->failed ++;
    }
  }
  
  echo json_encode($return);
  wp_die();
}

function ol_map_search() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'ol_outlets';

  $kw = esc_sql($_POST['kw']);
  
  $return = new stdClass();
  $return->data = $kw;
  
  
  $url = 'https://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($kw.", Norway").'&key='.esc_attr(get_option('google_api_key'));
  $return->url = $url;
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $api_response = json_decode(curl_exec($ch)); 
  
  if($api_response->status == "ZERO_RESULTS") {
    $return->status = "no_results";
  }
  else if($api_response->status == "OK") {
    $return->status = "ok";
    
    $best_match = false;
    foreach($api_response->results as $result) {
      $lat = $result->geometry->location->lat;
      $lng = $result->geometry->location->lng;

      $sql = "SELECT name, latitude, longitude, ".$lat." AS orign_lat, ".$lng." AS orign_lng, 111.045 * DEGREES(ACOS(COS(RADIANS(".$lat."))
              * COS(RADIANS(latitude))
              * COS(RADIANS(longitude) - RADIANS(".$lng."))
              + SIN(RADIANS(".$lat."))
              * SIN(RADIANS(latitude))))
              AS distance_in_km
              FROM $table_name
              ORDER BY distance_in_km ASC
              LIMIT 0,5;";
      $res = $wpdb->get_results($sql);
      $return->latitude = $res[0]->latitude;
      $return->longitude = $res[0]->longitude;
      
      if($res[0]->distance_in_km < 5) {      
        if($best_match == false) $best_match = $res;
        else if($best_match[0]->distance_in_km > $res[0]->distance_in_km) $best_match = $res;
      }
    }
    
    if($best_match != false) {
      $return->latitude = $best_match[0]->orign_lat;
      $return->longitude = $best_match[0]->orign_lng;
      $return->dist = $best_match[0]->distance_in_km;
      $return->name = $best_match[0]->name;
      $return->match = array();
      
      foreach($best_match as $match) {
        if($match->distance_in_km < 5) $return->match[] = $match;
      }
      
    }
    
    else $return->status = "no_stores_close";
  }
  else {
    $return->status = "unknown_error";
  }

  echo json_encode($return);
  wp_die();
}

/* 
  *
  * BUILD MENU / MENU FUNCTIONS
  *
 */
add_action('admin_menu', 'ol_build_menu');
function ol_build_menu() {
	add_menu_page('Outlet Locator', 'Outlet Locator', 'manage_options', 'ol_menu', 'ol_request_page', 'dashicons-store');
  add_submenu_page("ol_menu", "Manage outlets", "Manage outlets", "manage_options", "ol_menu_outlets", 'ol_request_page' );
  add_submenu_page("ol_menu", "Upload outlets CSV", "Upload outlets CSV", "manage_options", "ol_menu_upload", 'ol_request_page' );
}

function ol_request_page() {
  $page = $_GET['page'];
  $page = end(explode("_", $page));
  if($page == "menu") $page = "settings";
  $page = __DIR__."/admin/".$page.".php";
  if(!is_file($page)) die("Non exsisting page?");
  else require_once($page);
}

function outlet_locator_settings_init() {
	register_setting( 'outlet_locator_settings', 'gps_location_ask' );
	register_setting( 'outlet_locator_settings', 'google_api_key' );
	register_setting( 'outlet_locator_settings', 'url_replace' );
}













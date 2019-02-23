<?php if ( ! defined( 'WPINC' ) ) { die("You wish"); } ?>

<?php
  global $wpdb;
  $table_name = $wpdb->prefix . 'ol_outlets';

  $no_coords = $wpdb->get_results("SELECT id FROM $table_name WHERE geocoding = ''");
  $has_coords = $wpdb->get_results("SELECT id FROM $table_name WHERE geocoding = 'OK' OR geocoding = 'OK_MANUAL'");
  $failed_coords = $wpdb->get_results("SELECT id FROM $table_name WHERE geocoding NOT LIKE '%OK%' AND geocoding != ''");
 
?>  
  

<div class="wrap">
<h2>Outlet Locator &raquo; Settings</h2>

<form method="post" action="options.php">
    <?php settings_fields( 'outlet_locator_settings' ); ?>
    <?php do_settings_sections( 'outlet_locator_settings' ); ?>
    <table class="form-table">        
        <tr valign="top">
          <th scope="row">Enable geolocation button</th>
          <td><input type="checkbox" name="gps_location_ask" <?php echo esc_attr( get_option('gps_location_ask') ) == "on" ? "checked='1'" : ""; ?> /></td>
        </tr>
        <tr valign="top">
          <th scope="row">Google Apis Key</th>
          <td><input type="text" name="google_api_key" value="<?php echo esc_attr( get_option('google_api_key') ); ?>" /></td>
        </tr>
        <tr valign="top">
          <th scope="row">Product replace text</th>
          <td><textarea name="url_replace" cols="70" rows="10"><?php echo esc_attr( get_option('url_replace') ); ?></textarea>
          <p>
            Fill out searchword|new text|link. <br />
            E.g.: panc|Pancakes|portfolio/pancakes/
          </p>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">Outlet status</th>
          <td>
            Has geocode: <?php echo sizeof($has_coords); ?><br />
            Misses geocode: <?php echo sizeof($no_coords); ?><br />
            Failed geocode: <?php echo sizeof($failed_coords); ?><br />
            <button id="ol_geocode" type="button">Start geocoding the missing ones</button>
            <span id="ol_geocode_status">&nbsp;</span><br />
            <p>When pressing "Stop" wait until the button changes to "Start" to move on.</p>
          </td>
        </tr>
    </table>
    
    <?php submit_button(); ?>
</form>


<script type="text/javascript">
  var missing = <?php echo sizeof($no_coords); ?>;
  var success = 0;
  var failed = 0;
  var tryToSTop = true;
  jQuery("#ol_geocode").click(function(e) {
    e.preventDefault();
    if(jQuery(this).html().substr(0,3) == "Sta") {
      jQuery("#ol_geocode_status").html("<img src='<?php echo plugins_url( '../images/ajax-loader.gif', __FILE__ ); ?>' /> Starting to geocode "+missing+" outlets.");
      tryToSTop = false;
      setTimeout(tryToGeocode, 1000);
      jQuery(this).html("Stop geocoding");
    }
    else {
      tryToSTop = true;
    }
  });
  
  
  function tryToGeocode() {
    if(tryToSTop == true) {
      jQuery("#ol_geocode").html("Start geocoding the missing ones");
      jQuery("#ol_geocode_status").html("Stopped. Refresh to update statistics.");
      return;
    }
    
    jQuery.post(ajaxurl, {action:'ol_geolocate_outlet'}, function(response) {
      json = jQuery.parseJSON(response);
      
      if(json.done == "-1") {
        jQuery("#ol_geocode").html("Start geocoding the missing ones");
        jQuery("#ol_geocode_status").html("Done, no more to do?");
        return;
      }
          
      missing -= json.done-json.failed;
      success += json.done;
      failed += json.failed;
      
    
      jQuery("#ol_geocode_status").html("<img src='<?php echo plugins_url( '../images/ajax-loader.gif', __FILE__ ); ?>' /> Starting to geocode "+missing+" outlets. Total failed: "+failed+", total success: "+success);
  
      setTimeout(tryToGeocode, 1000);
    });

  }
  
</script>
<?php 
  
  if ( ! defined( 'WPINC' ) ) { die("You wish"); }
  
	wp_register_style( 'ol_shortcode_styles', plugins_url( 'mapstyles.css', __FILE__ ) );


  /* 
  *
  * SHORTCODE
  *
 */
add_shortcode('outlet_locator', 'outlet_locator_frontend');
add_shortcode('outlet_locator_map', 'outlet_locator_frontend_map');

function outlet_locator_frontend() {
  wp_enqueue_style("ol_shortcode_styles");
?>
<div id="ol_search">
  <h1 class="ols_header">Finn&nbsp;butikk</h1>
  <div class="ols_searchfield_container ols_shadow">
    <div class="ols_searchfield">
      <input type="text" placeholder="Sted, postnummer eller by" />
      <a href="#">Søk</a>
    </div>
    <div class="ols_legend">Du kan søke etter sted, postnummer eller butikknavn</div>
  </div>
</div>

<?php if(esc_attr( get_option('gps_location_ask') ) == "on") { ?>
<div class="ols_jslocator"><a href="#" class="ols_shadow" id="ol_geolocate">Vis min posisjon</a></div>
<?php } ?>

<hr class="ols_hr" />

<script type="text/javascript">
  
  
  jQuery('#ol_geolocate').click(function(e) {
    e.preventDefault();
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(success) { 
          console.log(success);
          var coords = {lat: success.coords.latitude, lng: success.coords.longitude};
          posmarker.setVisible(true);
          posmarker.setPosition(coords);
          map.setCenter(coords);
          map.setZoom(13);

        },
                                                 function(failure) {
                                                   console.log(failure);
                                                   if(failure.message.indexOf("Only secure origins are allowed") == 0) { /*S ERROR*/ }
                                                });
    } else {
        console.log("Geolocation is not supported by this browser.");
    }
  });
  
  jQuery(".ols_searchfield input").keypress(function(e) { if(e.which == 13) { jQuery(".ols_searchfield a").click(); }});
  
  jQuery(".ols_searchfield a").click(function(e) {
    e.preventDefault();
    jQuery.post(ajaxurl, {action:'ol_map_search', kw:jQuery(".ols_searchfield input").val()}, function(response) {
      json = jQuery.parseJSON(response);
      
      if(json.status == "ok") {
        var coords = {lat: parseFloat(json.latitude), lng:parseFloat(json.longitude)};
        var bounds = new google.maps.LatLngBounds();
        bounds.extend(coords);
        
        posmarker.setVisible(true);
        posmarker.setPosition(coords);
        
        for(i in json.match) {
          var c = {lat: parseFloat(json.match[i].latitude), lng: parseFloat(json.match[i].longitude)};
          console.log(c);
          bounds.extend(c);
        }
        
        map.fitBounds(bounds);
        
      }else if(json.status == "no_stores_close") {
        alert("No stores within 5km of search result.");
      }else if(json.status == "no_results") {
        alert("No result on search, please refine your search!");
      }else {
        alert("Unknown error? Please contact webdeveloper to check it out. Remember what you searched and give that to them!");
      }
    });
  });
  
</script>

<?php
}

function outlet_locator_frontend_map() {
?>
<div id="ol_mapcontainer">
  <div class="olm_map" id="olm_map"></div>
</div>


<script>
  <?php
    $data = file_get_contents("http://ip-api.com/json/".$_SERVER['REMOTE_ADDR']);
    $data = json_decode($data);
    echo "var coordlat=".$data->lat.";\n";
    echo "    var coordlng=".$data->lon.";\n";
  ?>
  var map, infoWindow, posmarker;
  function initMap() {
    map = new google.maps.Map(document.getElementById('olm_map'), {
      zoom: 10,
      center: {lat: coordlat, lng: coordlng},
      styles: [{"elementType":"geometry","stylers":[{"color":"#f5f5f5"}]},{"elementType":"labels.icon","stylers":[{"visibility":"off"}]},{"elementType":"labels.text.fill","stylers":[{"color":"#616161"}]},{"elementType":"labels.text.stroke","stylers":[{"color":"#f5f5f5"}]},{"featureType":"administrative.land_parcel","elementType":"labels.text.fill","stylers":[{"color":"#bdbdbd"}]},{"featureType":"poi","elementType":"geometry","stylers":[{"color":"#eeeeee"}]},{"featureType":"poi","elementType":"labels.text.fill","stylers":[{"color":"#757575"}]},{"featureType":"poi.business","stylers":[{"visibility":"on"}]},{"featureType":"poi.park","elementType":"geometry","stylers":[{"color":"#e5e5e5"}]},{"featureType":"poi.park","elementType":"labels.text.fill","stylers":[{"color":"#9e9e9e"}]},{"featureType":"road","elementType":"geometry","stylers":[{"color":"#ffffff"}]},{"featureType":"road.arterial","elementType":"labels.text.fill","stylers":[{"color":"#757575"}]},{"featureType":"road.highway","elementType":"geometry","stylers":[{"color":"#dadada"}]},{"featureType":"road.highway","elementType":"labels.text.fill","stylers":[{"color":"#616161"}]},{"featureType":"road.local","elementType":"labels.text.fill","stylers":[{"color":"#9e9e9e"}]},{"featureType":"transit.line","elementType":"geometry","stylers":[{"color":"#e5e5e5"}]},{"featureType":"transit.station","elementType":"geometry","stylers":[{"color":"#eeeeee"}]},{"featureType":"water","elementType":"geometry","stylers":[{"color":"#c9c9c9"}]},{"featureType":"water","elementType":"labels.text.fill","stylers":[{"color":"#9e9e9e"}]}]
    });
    
    infowindow = new google.maps.InfoWindow({});
    
    var markers = [];
        
<?php
  global $wpdb;
  $table_name = $wpdb->prefix . 'ol_outlets';
  
  $sql = "SELECT * FROM $table_name WHERE latitude != ''";
  $res = $wpdb->get_results($sql);
  
  $image = "unknown";
  foreach($res as $k=>$data) {
    $name = $data->name;
    $name = preg_replace("/avd\..\d*/i", "", $name);
    $name = preg_replace("/kiwi \d*/i", "KIWI", $name);
    
    if(preg_match("/joker/i", $name)) { $image = "joker"; }
    if(preg_match("/extra/i", $name)) { $image = "coop_extra"; }
    if(preg_match("/marked/i", $name)) { $image = "coop_marked"; }
    if(preg_match("/mega/i", $name)) { $image = "coop_mega"; }
    if(preg_match("/prix/i", $name)) { $image = "coop_prix"; }
    if(preg_match("/kiwi/i", $name)) { $image = "kiwi"; }
    if(preg_match("/matkroken/i", $name)) { $image = "matkroken"; }
    if(preg_match("/meny/i", $name)) { $image = "meny"; }
    if(preg_match("/obs/i", $name)) { $image = "obs"; }
    if(preg_match("/rema/i", $name)) { $image = "rema1000"; }
    if(preg_match("/spar/i", $name)) { $image = "spar"; }
    
    $address = str_replace("Norway", "Norge", $data->formatted_address);
    $address = str_replace("--, ", "", $address);
    
    $search   = get_option('url_replace');
    $search   = explode(PHP_EOL, $search);
    $bilde = $data->bilde;
    
    $products = $data->products;
    foreach($prod as $k=>$v) {
      $match = false;
      foreach($search as $line) {
        $line = explode("|", $line);
        if(preg_match("/".$line[0]."/i", $v)) {
          $url = trim($line[2]);
          $name = trim($line[1]);
          
          
          $match = true;
          break;
        }
      }
      if($match == false) $products .= $v."<br />";
    }
?>
    markers.push(createMarker({lat: <?php echo $data->latitude; ?>, lng: <?php echo $data->longitude; ?>}, "<?php echo $name; ?>", "<?php echo $image; ?>", "<?php echo $products; ?>", "<?php echo $address; ?>", "<?php echo $bilde; ?>"));
<?php
  }
?>
    posmarker = new google.maps.Marker({ 
        icon: '<?php echo plugins_url( 'images/pin_', __FILE__ ); ?>you.png',      
        position: {lat:58, lng:9}, 
        title: "you",
        map: map,
    }); 
    posmarker.setVisible(false);

    var clusterStyles = [{
        fontFamily: "'Open Sans', 'Trebuchet MS', Helvetica, sans-serif",
        textColor: 'black',
        textSize: '15px;',
        url: '<?php echo plugins_url( 'images/cluster.png', __FILE__ ); ?>',
        height: 60,
        width: 60
    }];
      
    // Add a marker clusterer to manage the markers.
    var markerCluster = new MarkerClusterer(map, markers, { gridSize: 30, styles: clusterStyles, maxZoom: 15 });      
  }
  
function generateContent(title,bilde,products,address) {
  return '<div class="ol_ip_header">'+title+'</div>'+
         '<hr class="ol_ip_hr" />'+
         '<div class="ol_ip_content">'+
	     '<img src="https://oiko.no/2019/dritforbanna/wp-content/uploads/2019/02/'+bilde+'">'+
         '<p>'+products+
         '</p>'+
         '<h2>Butikkens adresse:</h2><p>'+address+'</p></div>';
}
      
function createMarker(pos, t, image, prods, addr, bilder) {
    var marker = new google.maps.Marker({ 
        icon: '', 
		bilde: bilder,
        position: pos, 
        title: t,
        products: prods,
        address: addr
    }); 
    google.maps.event.addListener(marker, 'click', function() { 
      infowindow.setContent(generateContent(marker.title, marker.bilde, marker.products, marker.address));
      infowindow.open(map, marker);
    }); 
    return marker;  
}
</script>
<script src="https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/markerclusterer.js"></script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr( get_option('google_api_key') ); ?>&callback=initMap" type="text/javascript"></script>
<?php
}
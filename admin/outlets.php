<?php if ( ! defined( 'WPINC' ) ) { die("You wish"); } ?>

<div class="wrap">
<h2>Outlet Locator &raquo; Manage outlets</h2>


<?php
// WP_List_Table is not loaded automatically so we need to load it in our application
if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Create a new table class that will extend the WP_List_Table
 */
class Outlets_table extends WP_List_Table
{
    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $data = $this->table_data();
        usort( $data, array( &$this, 'sort_data' ) );

        $perPage = 15;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);

        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ) );

        $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);

        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }
    
    /**
     * Override the parent columns_cb method. Creats the checkboxes for columns.
     *
     * @return none
     */
    function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="outlet[]" value="%s" />', $item['ID']
        );    
    }
    
    
    /**
     * Override the parent columns_cb method. Creats the checkboxes for columns.
     *
     * @return none
     */
    function get_bulk_actions() {
      $actions = array(
        'delete'    => 'Delete'
      );
      return $actions;
    }

    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */
    public function get_columns()
    {
        $columns = array(
            'cb'            => 'cb',
            'name'          => 'Name',
            'address'       => 'Address',
            'postalplace'   => 'Postal place',
            'coords'        => 'Coordinates',
            'last_updated'  => 'Last update',
            'geocoding'     => 'Geocoding',
        );

        return $columns;
    }

    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns()
    {
        return array();
    }

    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns()
    {
        return array('name' => array('name', false),
                     'address' => array('address', false),
                     'last_updated' => array('last_updated', false),
                     'coords' => array('coords', false),
                     'postalplace' => array('postalplace', false),
                     'geocoding' => array('geocoding', false));
    }

    /**
     * Get the table data
     *
     * @return Array
     */
    private function table_data()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ol_outlets'; 
        $where = "";
        if(isset($_POST['s'])) {
          // Search
          $kw = $_POST['s'];
          
          $where = "WHERE name LIKE '%$kw%' OR address LIKE '%$kw%' OR postalplace LIKE '%$kw%' OR geocoding LIKE '%$kw%' OR products LIKE '%$kw%'";
        }
        
        $sql = "SELECT * FROM ".$table_name." $where ORDER BY id ASC";
        $res = $wpdb->get_results($sql);
        
        
        $data = array();
        
        foreach($res as $ol) {
        $data[] = array(
                    'id'           => (int) $ol->id,
                    'name'         => $ol->name,
                    'address'      => $ol->address,
                    'postalplace'  => $ol->postalplace,
                    'coords'       => $ol->latitude == "" ? "None defined" : $ol->latitude. ", ". $ol->longitude,
                    'last_updated' => $ol->last_updated,
                    'geocoding'    => $ol->geocoding,
                    );
        }
        return $data;
    }

    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default( $item, $column_name )
    {
        switch( $column_name ) {
            case 'cb':
              return 'cb';
            case 'name':
              $actions = array(
                               'edit'      => sprintf('<a data-page="%s" data-action="%s" data-id="%s" href="#" class="action">Edit</a>',$_REQUEST['page'],'edit',$item['id']),
                               'geolocate' => sprintf('<a data-page="%s" data-action="%s" data-id="%s" data-search="%s" href="#" class="action">Get location</a>',$_REQUEST['page'],'geolocate',$item['id'], $item['name']." ".$item['address']. " ".$item['postalplace']." ".$item['country']),
                               //'delete'    => sprintf('<a data-page="%s" data-action="%s" data-id="%s" data-outlet="%s" href="#" class="action">Delete</a>',$_REQUEST['page'],'delete',$item['id'], $item['name']),
                              );

              return sprintf("<span class='".$column_name."_id_".$item['id']."'>".'%1$s</span> %2$s', $item['name'], $this->row_actions($actions) );

            case 'coords':
            case 'address':
            case 'postalplace':
            case 'last_updated':
            case 'geocoding':
                return "<span class='".$column_name."_id_".$item['id']."'>".$item[ $column_name ]."</span>";
            default:
                return print_r( $item, true ) ;
        }
    }

    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    private function sort_data( $a, $b )
    {
        // Set defaults
        $orderby = 'name';
        $order = 'asc';

        // If orderby is set, use this as the sort column
        if(!empty($_GET['orderby']))
        {
            $orderby = $_GET['orderby'];
        }

        // If order is set use this as the order
        if(!empty($_GET['order']))
        {
            $order = $_GET['order'];
        }


        $result = strcmp( $a[$orderby], $b[$orderby] );

        if($order === 'asc')
        {
            return $result;
        }

        return -$result;
    }
}


$outlets_table = new Outlets_table();

?>
<form method="post">
  <input type="hidden" name="page" value="ol_menu_outlets" />
<?php
$outlets_table->prepare_items();
$outlets_table->search_box('search', 'search_id'); 
$outlets_table->display();
?>
</form>
</div>
<style type="text/css">

#manuallocate {
  position: absolute;
  margin: 0 auto;
  padding: 20px;
  left: -150%;
  top: 20%;
  margin-left: -400px;
  width: 800px;
  height: 600px;
  background-color: white;
  border: 1px solid #ccc;
  -webkit-box-shadow: 1px 1px 10px 0px rgba(50, 50, 50, 0.3);
  -moz-box-shadow:    1px 1px 10px 0px rgba(50, 50, 50, 0.3);
  box-shadow:         1px 1px 10px 0px rgba(50, 50, 50, 0.3);
}

#manuallocate input[type=text] {
  width: 100%;
}

#manuallocate b {
  font-size: 15px;
}
#manuallocate img.s, #manuallocate img.s2 {
  display: none;
}
#manuallocate .olm_map {
  width: 100%;
  height: 300px;
}

</style>

<div id="manuallocate">
  <h1>Manual locate</h1>
  <p><b>Outlet </b> <span class="store_data">Hey</span></p>
  <p><b>Custom keyword:</b> <input type="text" autocomplete="off" value="EXTRA SKI AVD.094 KJEPPESTADV 2 SKI, Norway" class="sf" name="ol_manual_search" /></p>
  <p><button class="doSrc">Search</button> <img class="s" src="<?php echo plugins_url( '../images/ajax-loader.gif', __FILE__ ); ?>" alt="" /></p>
  <div id="formatted"></div>
  <div class="olm_map" id="olm_map"></div>
  <p><button class="doSave">Save current coordinates</button><img class="s2" src="<?php echo plugins_url( '../images/ajax-loader.gif', __FILE__ ); ?>" alt="" /><button class="doClose">CANCEL</button></p>
</div>


<script type="text/javascript">
  var map, infoWindow, posmarker;
  function initMap() {
    map = new google.maps.Map(document.getElementById('olm_map'), {
      zoom: 15,
      center: {lat: 62.911491, lng: 10.757933}
    });

    posmarker = new google.maps.Marker({ 
        icon: '<?php echo plugins_url( '../images/pin_you.png', __FILE__ ); ?>',      
        position: {lat:58, lng:9}, 
        title: "you",
        map: map,
    }); 
    posmarker.setVisible(true);
  }
  
  jQuery("#manuallocate button.doSrc").click(function() {
    var searc = jQuery("#manuallocate input").val();
    jQuery("#manuallocate img.s").fadeIn();
    jQuery.post(ajaxurl, {action:'ol_detect_location', kw:searc}, function(response) {
      json = jQuery.parseJSON(response);
      console.log(json);
      
      if(json.status != "OK") { alert("Error, status: "+json.status); return; }
      var coord = json.response[0].geometry.location;
      map.setCenter(coord);
      map.setZoom(18);
      posmarker.setPosition(coord);
      posmarker.setTitle(json.response[0].formatted_address);
      posmarker.setVisible(true);
      jQuery("#manuallocate img.s").fadeOut();      
      jQuery("#manuallocate #formatted").html(json.response[0].formatted_address);      
      
    });
    
  });
  
  jQuery("#manuallocate button.doSave").click(function() {
    var outlet_id = posmarker.id;
    var lat = posmarker.position.lat();
    var lng = posmarker.position.lng();
    jQuery("#manuallocate img.s").fadeIn();

    jQuery.post(ajaxurl, {action:'ol_detect_location_save', id:outlet_id, lat:lat, lng:lng, addr:posmarker.title}, function(response) {
      if(response == "OK") {
        jQuery(".coords_id_"+outlet_id).html(lat+", "+lng);          
        jQuery(".geocoding_id_"+outlet_id).html("OK_MANUAL");
        jQuery(".last_updated_id_"+outlet_id).html("NOW" );
        jQuery("#manuallocate button.doClose").click();
      }else {
       alert("Error: "+response);
      }
      jQuery("#manuallocate img.s").fadeOut();
    });
  });

  jQuery("#manuallocate button.doClose").click(function() {
    jQuery("#manuallocate").animate({left:"+150%"}, 1000, function() {
      jQuery("#manuallocate").css("left", "-150%");
    });
  });
  
  
  jQuery("a.action").click(function(e) {
    e.preventDefault();

    if(jQuery(this).data('action') == "delete") {
      if(confirm("Are you sure you want to delete: "+jQuery(this).data('outlet')+"?")) {
        alert("Todo!");
      }
    }
    else if(jQuery(this).data('action') == "edit") {
      var outlet_id = jQuery(this).data('id');
      var name = jQuery(".name_id_"+outlet_id).html();
      var address = jQuery(".address_id_"+outlet_id).html();
      var postalplace = jQuery(".postalplace_id_"+outlet_id).html();
      posmarker.id = outlet_id;
      jQuery("#manuallocate .store_data").html("<b>"+outlet_id+"</b>: "+name+" - "+address+" - "+postalplace);
      jQuery("#manuallocate input").val(name + " " + address + " " + postalplace + ", NORWAY");
      jQuery("#manuallocate").animate({left:"50%"}, 1000, function() {
        google.maps.event.trigger(map, 'resize')
        map.setCenter({lat: 62.911491, lng: 10.757933});
        map.setZoom(5);
      });


      
    }    
    else if(jQuery(this).data('action') == "geolocate") {
      var outlet_id = jQuery(this).data('id');

      jQuery.post(ajaxurl, {action:'ol_geolocate_id', id:outlet_id}, function(response) {
        json = jQuery.parseJSON(response);
        console.log(json);
        if(json.status == "OK") 
          jQuery(".coords_id_"+outlet_id).html(json.location.lat+", "+json.location.lng);
        else 
          alert("Could not find, got error: "+json.status);

        jQuery(".geocoding_id_"+outlet_id).html(json.status);
        jQuery(".last_updated_id_"+outlet_id).html(json.datetime);

      });

    }    
  });
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr( get_option('google_api_key') ); ?>&callback=initMap" type="text/javascript"></script>


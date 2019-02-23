<?php if ( ! defined( 'WPINC' ) ) { die("You wish"); } ?>


<?php
$step = isset($_GET['step']) ? $_GET['step'] : 0;
if(isset($_POST["submit"])) {
  if($_FILES['ol_csv']['type'] != "text/csv") {
    $error = '<div class="error notice"><p>Error: File is not an CSV. </p></div>';
  }
  else {
    $ul_dir = wp_upload_dir(); 
    $file = $ul_dir['basedir']."/ol_csv.csv";
    move_uploaded_file($_FILES['ol_csv']['tmp_name'], $file);
    $str = file_get_contents($file);
    $csv = explode(PHP_EOL, $str);
    $step = 1;
    $max = sizeof($csv);
  }
}

if($step == 2) {
  $ul_dir = wp_upload_dir(); 
  $file = $ul_dir['basedir']."/ol_csv.csv";

  if(!is_file($file)) { $step = 0; $error = '<div class="error notice"><p>Error: Something went wrong, try again.. </p></div>'; }

  $str = file_get_contents($file);
  $csv = explode(PHP_EOL, $str);
  $max = sizeof($csv);
}

?>

<h2>Outlet Locator &raquo; Upload outlets CSV</h2>


<div class="card">
	<h2>Importent! Read this before continue</h2>
	<p>When uploading CSV it is importent that it has the same structure as shown below.</p>
	<p>Column 4 have to be "OUTLET NAME"</p>
	<p>Column 7 have to be "PRODUCT NAME"</p>
	<p>Column 10 have to be "ADDRESS"</p>
	<p>Column 11 have to be "POSTAL ADDRESS"</p>
	<p>Column 12 have to be a number above 0 to include the store in the script.</p>
	<p>All other columns will be ignored. Stores will autodetect locations but you must manually go into Manage Outlets to fix stores which did not find its geo location.</p>
	<p style="color: red;">Notice: Exsisting entries will deleted!</p>
</div>

<?php if($step == 0) { ?>
<div class="card">
  <h2>Select CSV to upload</h2>
    <form action="" method="post" enctype="multipart/form-data">
    Select image to upload:
    <input type="file" name="ol_csv" id="fileToUpload">
    <input type="submit" value="Upload CSV" name="submit">
  </form>
  <?php if(isset($error)) echo $error; ?>
</div>
<?php } elseif($step == 1) { ?>
<div class="card">
  <h2>Validate that content is correct:</h2>
  <p>Showing 5 random rows, confirm that it shows the data wanted:</p>
  
  <table border=1>
    <tr>
      <th>Outlet name</th>
      <th>Product Name</th>
      <th>Address</th>
      <th>Postal Address</th>
      <th>Have products?</th>
    </tr>
<?php 
  for($i = 0; $i<5;$i++) { 
    $numb = rand(0,$max);
    $line = str_getcsv($csv[$numb],";");
?>
    <tr>
      <td><?php echo reset(explode(" ",$line[3])); ?></td>
      <td><?php echo reset(explode(" ",$line[6])); ?></td>
      <td><?php echo $line[9]; ?></td>
      <td><?php echo $line[10]; ?></td>
      <td><?php echo (int)$line[11]>0?"Yes":"No"; ?></td>
    </tr>
<?php } ?>
  </table>
  
  <p><a href="?page=ol_menu_upload&step=2">Click here</a> if everything looks ok!</p>
</div>
<?php } elseif($step == 2) { ?>
<div class="card">
  <h1>Importing to database</h1>
  
  <p>Starting, this might take a while!</p>


  <p>1. <b>Ceching old database locations</b><br />
<?php 
  global $wpdb;
  $table_name = $wpdb->prefix . 'ol_outlets'; 
  $sql = "SELECT name, address, postalplace, formatted_address, longitude, latitude, last_updated, geocoding FROM $table_name WHERE geocoding LIKE '%OK%'";
  $res = $wpdb->get_results($sql);
  
  $cached_outlets = array();
  foreach($res as $o) {
    $key = $o->name."-".$o->address;
    $cached_outlets[$key] = $o;
  }
  echo sizeof($cached_outlets);
?>
   old positions fetched</p>
  
  <p>2. <b>Deleting old database:</b><br />
<?php
  $sql = "TRUNCATE $table_name;";
  $wpdb->query($sql);
  
?>
  OK</p>
  
  <p>3. <b>Parsing new data:</b><br />
<?php
  $outlets_with_sales = array();
  foreach($csv as $line) {
    $data = str_getcsv($line, ";");
 
    $store = trim($data[3]);
    $address = trim($data[9]);
    $postalplace = trim($data[10]);
    $country = "Norway";
    $dpack = (int) trim($data[11]);
    $product = trim($data[6]);
    if($dpack > 0) {
      if(!isset($outlets_with_sales[$store]))
        $outlets_with_sales[$store] = array($address, $postalplace, $country, array($product=>$dpack));
      else 
        $outlets_with_sales[$store][3][$product] = $dpack;
    }
  }

  echo sizeof($outlets_with_sales);
  
  $sqls = array();
  foreach($outlets_with_sales as $k=>$v) {
    $products = "";
    foreach($v[3] as $prod=>$dpak) $products .= $prod.",";
    $products = substr($products,0,-1);      

    $longitude = $latitude = $last_updated = $formatted_address = $geocoding = "";

    // Check if cached outlets is saved for this store, to get lat + long
    if(isset($cached_outlets[$k."-".$v['0']])) {
      $tmp = $cached_outlets[$k."-".$v['0']];
      $longitude = $tmp->longitude;
      $latitude = $tmp->latitude;
      $last_updated = $tmp->last_updated;
      $formatted_address = $tmp->formatted_address;
      $geocoding = $tmp->geocoding;
    }
    
    // Generate SQL and query
    $sqls[] = 'INSERT INTO '.$table_name.'(`name`, `address`, `postalplace`, `country`, `products`, `longitude`, `latitude`, `last_updated`, `formatted_address`, `geocoding`) VALUES("'.$k.'", "'.$v['0'].'", "'.$v['1'].'", "'.$v['2'].'", "'.$products.'", "'.$longitude.'", "'.$latitude.'", "'.$last_updated.'", "'.$formatted_address.'", "'.$geocoding.'")';
  }  

?>
  Outlets detected</p>
  
  <p>4. <b>Adding to database:</b><br />
<?php
  echo sizeof($sqls);
  foreach($sqls as $k=>$sql) {
    $wpdb->query($sql); 
  }
?>
  Outlets added</p>
  
  <p>5. <b>Transferring old locations to new entries who match the old:</b><br />
  <?php /* delete database */ ?>
  Outlets updated with new locations</p>

  <p>6. <b>Deleting CSV file for security reasons:</b><br />
<?php 
unlink($file);
?>
  OK</p>
  
  
  <p>Done.... Press <a href="?page=ol_menu_outlets">here</a> to show the new outlets.</p>
  
</div>
<?php } ?>
<?php
function p($v = '') {
  echo "<pre>";
  print_r($v);
  echo "</pre>";
}
$db = new Database();

function get_db_curl_content($url = '1') {
  global $db;
  echo $sql = "SELECT * FROM site_info WHERE url = '" . $url . "'";
  $db->query($sql);
  if ($db->numRows() > 0) {
    while($db->nextRecord()){
      $content = stripslashes($db->Record['page_html']);
    }
  }
  else {
    $cc = new cURL();
    $content = $cc->get($url);
    $data = array();
    $data['url'] = $url;
    $data['page_html'] = $content;
    insert_content($data);
  }

  return $content;
}

function update_site_extracted_content($url, $node) {
  global $db;
  $sql = "SELECT * FROM site_info WHERE url = '" . $url . "' and extracted_content IS NULL";
  $db->query($sql);
  if ($db->numRows() > 0) {
    $node = add_slash(serialize($node));
    $sql = "UPDATE site_info set extracted_content = '" . $node . "' WHERE url = '".$url."'";
    $db->query($sql);
  }
  else {
    // do nothing  
  }

}

function find_recipe_url_content($url) {
  global $db;
  $sql = "SELECT * FROM site_info WHERE url = '" . $url . "' and extracted_content IS NOT NULL";
  $db->query($sql);
  if ($db->numRows() > 0) {
    return TRUE;
  }
  
  return FALSE;

}

function insert_content($data = array()) {
	global $db;
  $data = add_slash($data);
  $sql = "INSERT INTO site_info (url, page_html, status) values ('".$data['url']."','".$data['page_html']."','1' )";
  $db->query($sql);
}

function get_url_content($url = '') {
  global $db;
  $sql = "SELECT * FROM site_info WHERE url_id = '1' ";
  $db->query($sql);
  while($db->nextRecord()){
    $content = stripslashes($db->Record['page_html']);
  }
  return $content;
}

function add_slash($data) {
	if (is_array($data)) {
		foreach ($data as $k => $v) {
			$data[$k] = addslashes($v);
		}
	}
	else {
		$data = addslashes($data);
	}

	return $data;
}

?>
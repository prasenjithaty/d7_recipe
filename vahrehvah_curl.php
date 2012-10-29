<?php
include_once('includes/curl.php');
include_once('includes/logging.inc');
include_once('includes/db_class.php');
include_once('includes/functions.inc');

set_time_limit(360000);

$url = 'http://www.vahrehvah.com/';
$main_content = get_content($url);
$node_results = array();


$recipe_types = "";
$recipe_types = extract_content('%<li><a href="indianrecipes.php" class="dir".*?</ul>.*?</li>%sim', $main_content, 0);
$recipe_types = preg_replace('/<li><a href="indianrecipes.php".*?<ul>/sim', '', $recipe_types);
$recipe_types = preg_replace('%</ul>.*%sim', '', $recipe_types);

$recipe_cat_urls = extract_content_all('/href=".+?"/sim', $recipe_types, 0);

$recipe_cat_titles = extract_content_all('%">.+?</a>%sim', $recipe_types, 0);
$recipe_cat_titles = preg_replace('/">/sim', '', $recipe_cat_titles);
$recipe_cat_titles = preg_replace('%</a>%sim', '', $recipe_cat_titles);
p($recipe_cat_urls);
exit;
$break_count = 5;
$index_title = 0;

foreach ($recipe_cat_urls as $key => $recipe_cat_url) {
    $index_title++;
    $recipe_cat_url_final = preg_replace('/href="/sim', 'http://www.vahrehvah.com/', $recipe_cat_url);
    $recipe_cat_url_final = preg_replace('/"/sim', '', $recipe_cat_url_final);

    $recipe_cat_content = get_content($recipe_cat_url_final);
    $recipe_cat_content = extract_content('%<table align="center" width="730" border="0" >.*?</table>%sim', $recipe_cat_content, 0);

    $single_recipe_title_url = extract_content_all('%<a href=".*?</a>%sim', $recipe_cat_content, 0);
    $inner_index = 0;
    foreach ($single_recipe_title_url as $key => $single_recipe_url) {
        $inner_index++;

        $node_results[] = (object) getVahRehVahRecipeNode($single_recipe_url, $recipe_cat_titles[$index_title - 1]);
        if ($inner_index == $break_count) {
          break;
        }
    }
}

p($node_results);


function  getVahRehVahRecipeNode($single_recipe_url, $category) {
    $single_recipe_url = extract_content_all('/\b(?:(?:http?):\/\/|www\.)[-A-Z0-9+&@#\/%=~_|$?!:,.]*[A-Z0-9+&@#\/%=~_|$]/sim', $single_recipe_url, 0);
    $single_recipe_url = $single_recipe_url[0];

    $recipe_content = get_content($single_recipe_url);
    $recipe_details = extract_content_all('%<fieldset id="Category - Parent Category_fieldset" class="admin_fieldset" style="text-align:center;width:560px;" record_number="34">.*?</fieldset>%sim', $recipe_content, 0);
    $recipe_metadata = $recipe_details[0];
    $recipe_details = $recipe_details[1];

    $recipe_title = extract_content('%<h1[^>]*>(.*?)</h1>%sim', $recipe_metadata, 1);
        
    $recipe_details_filtered = preg_replace('%<tr class="ingredient"[^>]*>(.*?)</tr>%sim', '', $recipe_details);
    $ingredients = extract_content_all('%<td align="left"  style="width:[^>]*>(.*?)<br />%sim', $recipe_details_filtered, 1);
    $ingredients = array_chunk($ingredients, 3, TRUE);

    $recipe_instructions = extract_content('%<td[^>]*class="instructions">(.*?)</td>%sim', $recipe_details_filtered, 1);
    $recipe_instructions = preg_replace('%<br />%sim', '', $recipe_instructions);

    $recipe_metadata_filtered = extract_content('%<table cellpadding="2"[^>]*>(.*?)</table>%sim', $recipe_metadata, 0);
    $recipe_metadata_filtered = preg_replace('%<table[^>]*>(.*?)</span></td>.+?</tr>%sim', '', $recipe_metadata_filtered);
    $recipe_final_metadata = get_prep_cook_time($recipe_metadata_filtered);

    $recipe_summary = extract_content('%<span itemprop="summary"[^>]*>(.*?)</span>%sim', $recipe_metadata, 1);

    $node = new stdClass();
    $node->category = $category;
    $node->recipe_type = $recipe_final_metadata['recipe_type'];
    $node->single_recipe_url = $single_recipe_url;
    $node->recipe_title = $recipe_title;
    $node->preptime = $recipe_final_metadata['preptime'];
    $node->standing_time = $recipe_final_metadata['standing_time'];
    $node->cooktime = $recipe_final_metadata['cooktime'];
    $node->yield = $recipe_final_metadata['yield'];
    $node->main_ingredient = $recipe_final_metadata['main_ingredient'];
    $node->ingredients = (array) $ingredients;
    $node->recipe_instructions = trim($recipe_instructions);
    $node->recipe_summary = trim($recipe_summary);

    return $node;
}
function get_prep_cook_time($recipe_metadata_filtered) {
  preg_match_all('%<td[^>]*>(.*?)</td>%sim', $recipe_metadata_filtered, $metadata, PREG_PATTERN_ORDER);
  $metadata = $metadata[0];
  $recipe_meta = array();
  foreach ($metadata as $key => $value) {
    if (preg_match('/preptime/sim', $value)) {
      $recipe_meta['preptime'] = get_recipe_meta_value($value);
    } elseif (preg_match('/cooktime/sim', $value)) {
      $recipe_meta['cooktime'] = get_recipe_meta_value($value);
    } elseif (preg_match('/Yield/sim', $value)) {
      $recipe_meta['yield'] = get_yield($value);
    } elseif (preg_match('/Type/sim', $value)) {
      preg_match('%</b>(.*?)</td>%sim', $value, $regs);
      $recipe_type = str_replace("&nbsp;", "", $regs[1]);
      $recipe_type = str_replace("\n", "", $recipe_type);
      $recipe_meta['recipe_type'] = $recipe_type;
    } elseif (preg_match('/Standing/sim', $value)) {
      preg_match('%</b[^>]*>(.*?)</td>%sim', $value, $regs);
      $standing_time = str_replace("&nbsp;", "", $regs[1]);
      $recipe_meta['standing_time'] = $standing_time;
    } elseif (preg_match('/Ingredient/sim', $value)) {
      preg_match('%</b[^>]*>(.*?)</td>%sim', $value, $regs);
      $recipe_meta['main_ingredient'] = $regs[1];
    }
  }
  
  return $recipe_meta;
}

function get_yield($subject) {
  if (preg_match('%<span[^>]*>(.*?)</span>%sim', $subject, $regs)) {
    $yield = $regs[1];
  } else {
    $yield = "Yield not found.";
  }

  return $yield;
}



function get_recipe_meta_value($subject) {
  if (preg_match('%<span class="value-title"[^>]*>(.*?)</span>%sim', $subject, $regs)) {
    $result = $regs[1];
  } else {
    $result = "";
  }
  
  return $result;
}

function extract_content_all($reg_exp, $content, $index) {
  preg_match_all($reg_exp, $content, $extracted_content, PREG_PATTERN_ORDER);
  
  return $extracted_content[$index];
}

function extract_content($reg_exp, $content, $extracted_content, $flag = PREG_PATTERN_ORDER) {
  preg_match_all($reg_exp, $content, $extracted_content, $flag);
  
  return $extracted_content;
}

function get_content($url) {
  
  $content = get_db_curl_content($url);
  return $content;
}


?>
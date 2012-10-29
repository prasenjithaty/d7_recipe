<?php
include_once('curl.php');
include_once('includes/logging.inc');
include_once('includes/db_class.php');
include_once('includes/functions.inc');

set_time_limit(360000);

$url = 'http://www.vahrehvah.com/';
$content = getContent($url);
echo "<pre>";
$node_results = array();

$recipe_types = "";
if (preg_match('%<li><a href="indianrecipes.php" class="dir".*?</ul>.*?</li>%sim', $content, $regs)) {
  $recipe_types = $regs[0];
}

$recipe_types = preg_replace('/<li><a href="indianrecipes.php".*?<ul>/sim', '', $recipe_types);
$recipe_types = preg_replace('%</ul>.*%sim', '', $recipe_types);

preg_match_all('/href=".+?"/sim', $recipe_types, $recipe_cat_urls, PREG_PATTERN_ORDER);
$recipe_cat_urls = $recipe_cat_urls[0];

preg_match_all('%">.+?</a>%sim', $recipe_types, $recipe_cat_title, PREG_PATTERN_ORDER);
$recipe_cat_title = $recipe_cat_title[0];
$recipe_cat_title = preg_replace('/">/sim', '', $recipe_cat_title);
$recipe_cat_title = preg_replace('%</a>%sim', '', $recipe_cat_title);
$break_count = 5;

$count_title=0;
foreach ($recipe_cat_urls as $key => $recipe_cat_url) {
    $recipe_cat_url_final = preg_replace('/href="/sim', 'http://www.vahrehvah.com/', $recipe_cat_url);
    $recipe_cat_url_final = preg_replace('/"/sim', '', $recipe_cat_url_final);
    $count_title++;

    $content = getContent($recipe_cat_url_final);
    if (preg_match('%<table align="center" width="730" border="0" >.*?</table>%sim', $content, $regs)) {
        $content = $regs[0];
    }

    //preg_match_all('%<a href=".*?</a>%sim', $content, $single_recipe_title_url, PREG_PATTERN_ORDER);
    //$single_recipe_title_url = 'PREG_PATTERN_ORDER';
    $single_recipe_title_url = extractContent('%<a href=".*?</a>%sim', $content, PREG_PATTERN_ORDER);
    $count_outer = 0;
    foreach ($single_recipe_title_url[0] as $key => $value) {
        preg_match_all('/\b(?:(?:http?):\/\/|www\.)[-A-Z0-9+&@#\/%=~_|$?!:,.]*[A-Z0-9+&@#\/%=~_|$]/sim', $value, $single_recipe_url, PREG_PATTERN_ORDER);
        $single_recipe_url = $single_recipe_url[0];
        $count = 0;
        foreach ($single_recipe_url as $key => $value) {
            
            // check if the url is already stored in the db then skip it.
            if (find_recipe_url_content($value)) {
              continue;
            }
            //if ($count == $break_count) {
            //  break;
            //}
            //echo '<p>single_recipe_url: '. $value.'</p>';
            $node = new stdClass();
            $node->category = $recipe_cat_title[$count_title - 1];
            $node->single_recipe_url = $value;
            $content = getContent($value);
            preg_match_all('%<fieldset id="Category - Parent Category_fieldset" class="admin_fieldset" style="text-align:center;width:560px;" record_number="34">.*?</fieldset>%sim', $content, $recipe_details, PREG_PATTERN_ORDER);
            $recipe_details = $recipe_details[0];
            $recipe_metadata = $recipe_details[0];
            $recipe_details = $recipe_details[1];

            if (preg_match('%<h1[^>]*>(.*?)</h1>%sim', $recipe_metadata, $regs)) {
                $recipe_title = $regs[1];
            } else {
                $recipe_title = "";
            }
            $node->title = $recipe_title;
            $node->single_recipe_url = $value;
            
            $recipe_details_filtered = preg_replace('%<tr class="ingredient"[^>]*>(.*?)</tr>%sim', '', $recipe_details);
            preg_match_all('%<td align="left"  style="width:[^>]*>(.*?)<br />%sim', $recipe_details_filtered, $ingredients, PREG_PATTERN_ORDER);
            $ingredients = $ingredients[1];
            $ingredients = array_chunk($ingredients, 3, TRUE);
            //print_r($unit_quantity);
            $node->ingredients = (array) $ingredients;
            if (preg_match('%<td[^>]*class="instructions">(.*?)</td>%sim', $recipe_details_filtered, $regs)) {
                $recipe_instructions = $regs[1];
            } else {
                $recipe_instructions = "No instructions found for recipe. Please check back again shortly.";
            }

            $recipe_instructions = preg_replace('%<br />%sim', '', $recipe_instructions);
            //echo '<p>'.trim($recipe_instructions).'</p>';
            $node->recipe_instructions = trim($recipe_instructions);

            if (preg_match('%<table cellpadding="2"[^>]*>(.*?)</table>%sim', $recipe_metadata, $regs)) {
                $recipe_metadata_filtered = $regs[0];
            } else {
                $recipe_metadata_filtered = "";
            }
            $recipe_metadata_filtered = preg_replace('%<table[^>]*>(.*?)</span></td>.+?</tr>%sim', '', $recipe_metadata_filtered);
            $recipe_final_metadata = get_prep_cook_time($recipe_metadata_filtered);
            // print_r($recipe_final_metadata);

            if (preg_match('%<span itemprop="summary"[^>]*>(.*?)</span>%sim', $recipe_metadata, $regs)) {
                $recipe_summary = $regs[1];
            } else {
                $recipe_summary = "No recipe_summary found for recipe.";
            }
            // echo '</br>Recipe Description:: '. $recipe_summary.'</br>';
            $count++;            //p($node);
            update_site_extracted_content($node->single_recipe_url, $node);
            $node_results[] = (object) $node;
        }
        $count_outer++;
        //if ($count_outer == $break_count) {
        //  break;
        // }
    }
}

//p($node_results);

function get_prep_cook_time($recipe_metadata_filtered) {
  preg_match_all('%<span[^>]*>(.*?)</span>%sim', $recipe_metadata_filtered, $metadata, PREG_PATTERN_ORDER);
  $metadata = $metadata[0];
  $recipe_meta = array();
  foreach ($metadata as $key => $value) {
    if (preg_match('/preptime/sim', $value)) {
      $recipe_meta['preptime'] = get_recipe_meta_value($value);
    } 
    elseif (preg_match('/cooktime/sim', $value)) {
      $recipe_meta['cooktime'] = get_recipe_meta_value($value);
      } elseif (preg_match('%\A<span[^>]*>(.*?)</span>\Z%sim', $value)) {
          $recipe_meta['yield'] = get_yield($value);
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

function extractContent($reg_exp, $content, $extracted_content, $flag = PREG_PATTERN_ORDER) {
  preg_match_all($reg_exp, $content, $extracted_content, $flag);
  
  return $extracted_content;
}

function getContent($url) {
  
  $content = get_db_curl_content($url);
  return $content;
}


?>
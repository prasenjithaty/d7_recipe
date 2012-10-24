<?php
include_once('curl.php');
include_once('functions.inc');

//implementation
$cc = new cURL();
$content = $cc->get('http://www.vahrehvah.com/');
$node = new stdClass();

$recipe_types = "";
if (preg_match('%<li><a href="indianrecipes.php" class="dir".*?</ul>.*?</li>%sim', $content, $regs)) {
  $recipe_types = $regs[0];
}

$recipe_types = preg_replace('/<li><a href="indianrecipes.php".*?<ul>/sim', '', $recipe_types);
$recipe_types = preg_replace('%</ul>.*%sim', '', $recipe_types);

preg_match_all('/href=".+?"/sim', $recipe_types, $recipe_cat_url, PREG_PATTERN_ORDER);
$recipe_cat_url = $recipe_cat_url[0];
$recipe_cat_url = preg_replace('/href="/sim', 'http://www.vahrehvah.com/', $recipe_cat_url);
$recipe_cat_url = preg_replace('/"/sim', '', $recipe_cat_url);



preg_match_all('%">.+?</a>%sim', $recipe_types, $recipe_cat_title, PREG_PATTERN_ORDER);
$recipe_cat_title = $recipe_cat_title[0];
$recipe_cat_title = preg_replace('/">/sim', '', $recipe_cat_title);
$recipe_cat_title = preg_replace('%</a>%sim', '', $recipe_cat_title);


foreach ($recipe_cat_url as $key => $value) {
    $content = '';
    $content = getContent($value);
    if (preg_match('%<table align="center" width="730" border="0" >.*?</table>%sim', $content, $regs)) {
        $content = $regs[0];
    }

    //preg_match_all('%<a href=".*?</a>%sim', $content, $single_recipe_title_url, PREG_PATTERN_ORDER);
    $single_recipe_title_url = 'PREG_PATTERN_ORDER';
    $single_recipe_title_url = extractContent('%<a href=".*?</a>%sim', $content, $single_recipe_title_url);
    foreach ($single_recipe_title_url[0] as $key => $value) {
        preg_match_all('/\b(?:(?:http?):\/\/|www\.)[-A-Z0-9+&@#\/%=~_|$?!:,.]*[A-Z0-9+&@#\/%=~_|$]/sim', $value, $single_recipe_url, PREG_PATTERN_ORDER);
        $single_recipe_url = $single_recipe_url[0];
        foreach ($single_recipe_url as $key => $value) {
            //echo '<p>single_recipe_url: '. $value.'</p>';
            $node->single_recipe_url = $value;
            $content = getContent($value);
            preg_match_all('%<fieldset id="Category - Parent Category_fieldset" class="admin_fieldset" style="text-align:center;width:560px;" record_number="34">.*?</fieldset>%sim', $content, $recipe_details, PREG_PATTERN_ORDER);
            $recipe_details = $recipe_details[0];
            $recipe_metadata = $recipe_details[0];
            $recipe_details = $recipe_details[1];

            $recipe_details_filtered = preg_replace('%<tr class="ingredient"[^>]*>(.*?)</tr>%sim', '', $recipe_details);
            preg_match_all('%<td align="left"  style="width:600px[^>]*>(.*?)<br />%sim', $recipe_details_filtered, $result, PREG_PATTERN_ORDER);
            $recipe_ingredients = $result[1];
            //print_r($recipe_ingredients);
            $node->recipe_ingredients = (array) $recipe_ingredients;
            preg_match_all('%<td align="left"  style="width:100px[^>]*>(.*?)<br />%sim', $recipe_details_filtered, $unit_quantity, PREG_PATTERN_ORDER);
            $unit_quantity = $unit_quantity[1];
            $unit_quantity = array_chunk($unit_quantity, 2, TRUE);
            //print_r($unit_quantity);
            $node->unit_quantity = (array) $unit_quantity;
            if (preg_match('%<td[^>]*class="instructions">(.*?)</td>%sim', $recipe_details_filtered, $regs)) {
                $recipe_instructions = $regs[1];
            } else {
                $recipe_instructions = "No instructions found for recipe. Please check back again shortly.";
            }

            $recipe_instructions = preg_replace('%<br />%sim', '', $recipe_instructions);
            //echo '<p>'.trim($recipe_instructions).'</p>';
            $node->recipe_instructions = trim($recipe_instructions);

            if (preg_match('%<h1[^>]*>(.*?)</h1>%sim', $recipe_metadata, $regs)) {
                $recipe_title = $regs[1];
            } else {
                $recipe_title = "";
            }

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
        }
        break;
    }
    break;
}

p($node);

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
  $cc = new cURL();
  $content = $cc->get($url);
  
  return $content;
}


?>
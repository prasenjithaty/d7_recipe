<?php
include_once('includes/curl.php');
include_once('includes/logging.inc');
include_once('includes/db_class.php');
include_once('includes/functions.inc');

set_time_limit(360000);

$url = 'http://cooks.ndtv.com/recipes';
$main_content = get_content($url);
$node_results = array();

if (preg_match('%<div id="insidetab"[^>]*>(.*?)</div>%sim', $main_content, $regs)) {
  $main_content = $regs[0];
} else {
  $main_content = "";
}

preg_match_all('%<a[^>]*>(.*?)</a>%sim', $main_content, $category_base_urls, PREG_PATTERN_ORDER);
$category_base_urls = $category_base_urls[0];
array_splice($category_base_urls, 4);

foreach ($category_base_urls as $key => $single_category_base_url) {
  if (preg_match('/\b(https?):\/\/[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/sim', $single_category_base_url, $regs)) {
    $single_category_base_url = $regs[0];
  } else {
    $single_category_base_url = "";
  }
  $single_category_base_content = get_content($single_category_base_url);
  
  preg_match_all('%<li class="main_image"[^>]*>(.*?)</li>%sim', $single_category_base_content, $single_sub_category_url, PREG_PATTERN_ORDER);
  $single_sub_category_url = $single_sub_category_url[0];

  foreach ($single_sub_category_url as $key => $single_sub_filtered_category_url) {
    preg_match_all('%(http)://.[a-zA-Z0-9-./]*?/"%sim', $single_sub_filtered_category_url, $single_sub_filtered_category_url, PREG_PATTERN_ORDER);
    $single_sub_filtered_category_url = $single_sub_filtered_category_url[0];
    $single_sub_filtered_category_url = str_replace("\"", "", $single_sub_filtered_category_url);
    
    $pagination = 1;
    while (true) {
      $single_category_content = get_content($single_sub_filtered_category_url[0].'page/'.$pagination);
      $pagination++;
      if (preg_match('%<li>No Record Found</li>%sim', $single_category_content, $regs)) {
        break;
      }
      if (preg_match('/<div class="lhs_cont" >.*<div class="wrappagination" id="pagination">/sim', $single_category_content, $regs)) {
        $single_category_filtered_content = $regs[0];
      } else {
        $single_category_filtered_content = "";
      }
      if (preg_match('%<span class="h_r_span"[^>]*>(.*?)</span>%sim', $single_category_filtered_content, $regs)) {
        $single_category_title = $regs[1];
      } else {
        $single_category_title = "";
      }
      preg_match_all('/<a href="http:\/\/cooks.ndtv.com\/recipe\/show\/(.*?)" title/sim', $single_category_filtered_content, $single_recipe_urls, PREG_PATTERN_ORDER);
      $single_recipe_urls = $single_recipe_urls[1];
      foreach ($single_recipe_urls as $key => $single_recipe_url) {
        $single_recipe_content = get_content('http://cooks.ndtv.com/recipe/show/'.$single_recipe_url);

        getNdtvRecipeNode($single_recipe_content, $single_category_title, $single_recipe_url);
      }
    }
  }
  break;
}

// Return $node from here and insert extracted_content into DB.
function getNdtvRecipeNode($single_recipe_content, $single_category_title, $single_recipe_url){
  if (preg_match('%<h1 class="fn"[^>]*>(.*?)</h1>%sim', $single_recipe_content, $regs)) {
    $single_recipe_title = $regs[1];
  } else {
    $single_recipe_title = "";
  }
  if (preg_match('%<h2 class="summary"[^>]*>(.*?)</h2>%sim', $single_recipe_content, $regs)) {
    $single_recipe_summary = $regs[1];
  } else {
    $single_recipe_summary = "";
  }

  echo 'Recipe Category: '.$single_category_title.'<br>';
  echo 'Recipe URL: http://cooks.ndtv.com/recipe/show/'.$single_recipe_url.'<br>';
  echo 'Recipe Title: '.$single_recipe_title.'<br>';
  echo 'Recipe Summary: '.$single_recipe_summary.'<br>';

  echo 'Servings: '.get_recipe_info('%Recipe Servings.*?<span>(.*?)</span>%sim', $single_recipe_content).'<br>';
  echo 'Cook Time: '.get_recipe_info('%Recipe Cook Time.*?<span>(.*?)</span>%sim', $single_recipe_content).'<br>';

  $recipe_ingredients = get_recipe_ingredients('%<div class="ingredient-cont"[^>]*>(.*?)</div>%sim', $single_recipe_content);
  echo 'Ingredients: '.print_r($recipe_ingredients).'<br>';

  echo 'Recipe Method: '.get_recipe_method('%<p class="instructions"[^>]*>(.*?)</p>%sim', $single_recipe_content).'<p>';
}

// This function has to be re-visited. It breaks when there are different ingredients for different thing.
// e.g. http://cooks.ndtv.com/recipe/show/christmas-cake-with-royal-icing-218189 and http://cooks.ndtv.com/recipe/show/apple-and-cream-cheese-stuffed-french-toast-281287
function get_recipe_ingredients($reg_exp, $content) {
  if (preg_match($reg_exp, $content, $regs)) {
    $recipe_ingredients = $regs[1];
    $recipe_ingredients = preg_replace('/<li class="ingredient">/sim', '<br />', $recipe_ingredients);
    $recipe_ingredients = preg_replace('%</li>%sim', '<br />', $recipe_ingredients);

    preg_match_all('%<br /[^>]*>(.*?)<br />%sim', $recipe_ingredients, $recipe_ingredients, PREG_PATTERN_ORDER);
    $recipe_ingredients = $recipe_ingredients[1];
    $recipe_ingredients = strip_tags_deep($recipe_ingredients);
  } else {
    $recipe_ingredients = "";
  }

  return $recipe_ingredients;
}

// This function needs to be re-visited. It gets related recipes as hyperlinks inside a sentence mostly in the last line of the recipe method.
// The whole last line where <a href=".....">.......</a> is found, has to be ripped off.
// e.g. http://cooks.ndtv.com/recipe/show/coronation-chicken-251689
// http://cooks.ndtv.com/recipe/show/dough-for-pizza-230843
// http://cooks.ndtv.com/recipe/show/passata-230845
function get_recipe_method($reg_exp, $content) {
  if (preg_match($reg_exp, $content, $regs)) {
    $recipe_method = $regs[1];
    $recipe_method = preg_replace("/&#?[a-z0-9]{2,8};/i","",$recipe_method);
  } else {
    $recipe_method = "";
  }
  return $recipe_method;
}

function strip_tags_deep($value) {
  return is_array($value) ? array_map('strip_tags_deep', $value) : strip_tags($value);
}

function get_recipe_info($reg_exp, $content) {
  preg_match($reg_exp, $content, $regs);
  return $regs[1];
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
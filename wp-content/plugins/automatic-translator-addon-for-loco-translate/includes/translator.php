<?php

add_filter('loco_api_translate_loco_auto','loco_auto_translator_process_batch',0,3);
/**
 * Hook fired as a filter for the "loco_auto" translation api
 * @param string[] input strings
 * @param Loco_Locale target locale for translations
 * @param array our own api configuration
 * @return string[] output strings
 */

function loco_auto_translator_process_batch( array $sources, Loco_Locale $Locale, array $config ){
$targets = array();
    $url_data=parse_query($_SERVER['HTTP_REFERER']);
    if(isset($url_data['domain'])&& !empty($url_data['domain']))
    { 
         $domain=$url_data['domain'];
    }else{
        $domain='temp';
    }
      $lang = $Locale->lang;
      $region = $Locale->region;
    $project_id=$domain.'-'.$lang.'_'.$region;
    $totalStrings=count($sources);
   
                $first_part= get_transient($project_id.'-first');
       
                
                $second_part=get_transient($project_id.'-second');
                $third_part= get_transient($project_id.'-third');

                if(!empty($first_part) && !empty($second_part) && !empty($third_part)){
                    $allString = array_merge($first_part,$second_part,$third_part);
                }
                elseif(!empty($first_part) && !empty($second_part)){
                    $allString = array_merge($first_part,$second_part);   
                }
                else{
                    $allString = get_transient($project_id);   
                }
               
        
    if($allString!==false){
    foreach( $sources as $i => $source ){
          $index = array_search($source, array_column($allString, 'source')); 
        if( is_numeric($index)){
       if(isset($allString[$index]['target']))
            {
                 $targets[$i] =$allString[$index]['target'];
            }
        }else{
            $targets[$i] ='';
        }
    }
    return $targets;
}else{
    throw new Loco_error_Exception('Please translate strings using Auto Translate addon button first.');
}
}




function parse_query($var)
{
  /**
   *  Use this function to parse out the query array element from
   *  the output of parse_url().
   */
  $var  = parse_url($var, PHP_URL_QUERY);
  $var  = html_entity_decode($var);
  $var  = explode('&', $var);
  $arr  = array();

  foreach($var as $val)
   {
    $x          = explode('=', $val);
    $arr[$x[0]] = $x[1];
   }
  unset($val, $x, $var);
  return $arr;
}

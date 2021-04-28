<?php
namespace LocoAutoTranslateAddon\Helpers;
/**
 * @package Loco Automatic Translate Addon
 */
class Helpers{
    public static function proInstalled(){
        if (defined('ATLT_PRO_FILE')) {
            return true;
        }else{
            return false;
        }
    }
   
    // return user type
    public static function userType(){
        $type='';
      if(get_option('atlt-type')==false || get_option('atlt-type')=='free'){
            return $type='free';
        }else if(get_option('atlt-type')=='pro'){
            return $type='pro';
        }  
    }

    // validate key
    public static function validKey($key){
    if (preg_match("/^([A-Z0-9]{8})-([A-Z0-9]{8})-([A-Z0-9]{8})-([A-Z0-9]{8})$/",$key)){
         return true;
        }else{
            return false;
        }
    }
    //grab key
    public static function getLicenseKey(){
        $licenseKey=get_option("LocoAutomaticTranslateAddonPro_lic_Key","");
        if($licenseKey==''||$licenseKey==false){
            return false;
        }else{
            return $licenseKey;
          }
    }
    

   
}

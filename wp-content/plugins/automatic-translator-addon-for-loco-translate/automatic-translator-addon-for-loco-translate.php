<?php
/*
Plugin Name:Automatic Translate Addon For Loco Translate
Description:Auto language translator add-on for Loco Translate official plugin version 2.4.0 to translate plugins and themes translation files into any language via fully automatic machine translations via Yandex Translate Widget.
Version:2.1.3
License:GPL2
Text Domain:automatic-translator-addon-for-loco-translate
Domain Path:languages
Author:Cool Plugins
Author URI:https://coolplugins.net/
*/
namespace LocoAutoTranslateAddon;
use LocoAutoTranslateAddon\Helpers\Helpers;
use LocoAutoTranslateAddon\Helpers\Atlt_downloader;
 /**
 * @package Loco Automatic Translate Addon
 * @version 2.1.3
 */
if (!defined('ABSPATH')) {
    die('WordPress Environment Not Found!');
}

define('ATLT_FILE', __FILE__);
define('ATLT_URL', plugin_dir_url(ATLT_FILE));
define('ATLT_PATH', plugin_dir_path(ATLT_FILE));
define('ATLT_VERSION', '2.1.3');

class LocoAutoTranslate
{
    public function __construct()
    { 
        register_activation_hook( ATLT_FILE, array( $this, 'atlt_activate' ) );
        register_deactivation_hook( ATLT_FILE, array( $this, 'atlt_deactivate' ) );
        if(is_admin()){
           add_action('admin_notices', array($this,'atlt_plugins_compatibility_check') );
            // Only loged in user can perform this AJAX request
            add_action('plugins_loaded', array($this, 'atlt_check_required_loco_plugin'));
            /*** Template Setting Page Link inside Plugins List */
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this,'atlt_settings_page_link'));
             add_action( 'admin_enqueue_scripts', array( $this,'atlt_enqueue_scripts') );
            add_action('init',array($this,'checkStatus'));
            add_action('init',array($this,'updateSettings'));
            //add notice to use latest loco translate addon
            add_action('init',array($this,'useLatestVersionNotice'));
            add_action('plugins_loaded', array($this,'include_files'));

         /*
            since version 2.0
            Yandex translate widget integration
        */
        // add no translate attribute in html tag
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'file-edit')
        {
            add_action('admin_footer', array($this,'load_ytranslate_scripts'),100);
        }

        /* since version 2.1 */
        add_filter('loco_api_providers',array($this,'register_loco_auto_translate_api'),10,1);
        add_action('loco_api_ajax',array($this,'loco_auto_translate_ajax_init'),0,0);
        add_action( 'wp_ajax_save_translations',array($this, 'save_all_translations') );

    }   
    }

    // since version 2.1
    function loco_auto_translate_ajax_init(){
        require __DIR__.'/includes/translator.php';
    }
    function register_loco_auto_translate_api( array $apis ){
        $apis[] = array (
            'id' => 'loco_auto',
            'key' => '122343',
            'url' => '234324',
            'name' => 'Automatic Translate Addon',
        );
        return $apis;
    }

 /*
   |----------------------------------------------------------------------
   | Yandex Translate Widget Integartions
   |----------------------------------------------------------------------
   */
    // load google translate widget scripts
    function load_ytranslate_scripts() {
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'file-edit')
        {
          echo"<script>document.getElementsByTagName('html')[0].setAttribute('translate', 'no');</script>";
        }
    }

    // update settings
    public function updateSettings(){
        if(get_option( 'atlt-ratingDiv')){
            update_option('atlt-already-rated',get_option( 'atlt-ratingDiv'));
            delete_option( 'atlt-ratingDiv');
        }
    }
    /**
     * create 'settings' link in plugins page
     */
    public function atlt_settings_page_link($links){
        $links[] = '<a style="font-weight:bold" href="'. esc_url( get_admin_url(null, 'admin.php?page=loco-atlt-register') ) .'">License</a>';
        return $links;
    }

   /*
   |----------------------------------------------------------------------
   | required php files
   |----------------------------------------------------------------------
   */
   public function include_files()
   {
  
      if ( is_admin() ) {
            include_once ATLT_PATH .'includes/Helpers/Helpers.php';
            include_once ATLT_PATH . "includes/ReviewNotice/class.review-notice.php";
            new ALTLReviewNotice\ALTLReviewNotice(); 
            include_once ATLT_PATH . 'includes/Feedback/class.feedback-form.php';
            new FeedbackForm\FeedbackForm();
            include_once ATLT_PATH . 'includes/Register/LocoAutomaticTranslateAddonPro.php';
        } 
        
   }

   /*
   |----------------------------------------------------------------------
   | check User Status
   |----------------------------------------------------------------------
   */
   public function checkStatus(){
   
    $key=Helpers::getLicenseKey();
    if(Helpers::validKey( $key) && Helpers::proInstalled()==false){
      add_action('admin_notices', array($this, 'atlt_pro_install_notice'));
    }
   }

    /*
   |----------------------------------------------------------------------
   | check User Status
   |----------------------------------------------------------------------
   */
  public function useLatestVersionNotice(){
    if(function_exists('loco_plugin_version')){
         $locoV=loco_plugin_version();
         if(version_compare($locoV,'2.4.0', '<'))
            {
                add_action('admin_notices', array($this, 'atlt_use_latest_admin_notice'));   
            }
      }
   }
   
    /*
   |----------------------------------------------------------------------
   | Notice to use latest version of Loco Translate plugin
   |----------------------------------------------------------------------
   */
  public function atlt_use_latest_admin_notice()
  {
     if (current_user_can('activate_plugins')) {
        $url = 'plugin-install.php?tab=plugin-information&plugin=loco-translate&TB_iframe=true';
        $title = "Loco Translate";
        $plugin_info = get_plugin_data(__FILE__, true, true);
        echo '<div class="error"><p>' . 
        sprintf(__('In order to use <strong>%s</strong> (version <strong>%s</strong>), Please update <a href="%s" class="thickbox" title="%s">%s</a> official plugin to a latest version (2.4.0 or upper)', 
        'automatic-translator-addon-for-loco-translate'),
         $plugin_info['Name'], $plugin_info['Version'], esc_url($url),
          esc_attr($title), esc_attr($title)) . '.</p></div>';

         }
  }
   /*
   |----------------------------------------------------------------------
   | check if required "Loco Translate" plugin is active
   | also register the plugin text domain
   |----------------------------------------------------------------------
   */
   public function atlt_check_required_loco_plugin()
   {
      if (!function_exists('loco_plugin_self')) {
         add_action('admin_notices', array($this, 'atlt_plugin_required_admin_notice'));
      }
      load_plugin_textdomain('automatic-translator-addon-for-loco-translate', false, basename(dirname(__FILE__)) . '/languages/');
   }

    /*
   |----------------------------------------------------------------------
   | Install Loco Automatic Translate Addon Pro notice
   |----------------------------------------------------------------------
   */
  public function atlt_pro_install_notice()
  {
     if (current_user_can('activate_plugins')) {
        $key=Helpers::getLicenseKey();
        $url =esc_url( add_query_arg( 'license-key',$key , 'https://locoaddon.com/data/download-plugin.php' ) );
        $title = "Loco Automatic Translate Addon Pro";

        if( class_exists( 'LocoAutoTranslateAddonPro' ) ){
            // no further execution required
            return;
        }
        
        if( false == file_exists( WP_PLUGIN_DIR . '/loco-automatic-translate-addon-pro') ){
            echo '<div class="error loco-pro-missing" style="border:2px solid;border-color:#dc3232;"><p>' . 
            sprintf('You are using <strong>%s</strong> license. Please also install and activate <strong>%s</strong> plugin to enjoy all premium featues and automatic premium updates.</p>
            <p><a href="%s" target="_blank" title="%s" class="button button-primary"><strong>Download %s plugin</strong></a> and install it, you can also download it from <a href="https://locoaddon.com/my-account/downloads/" target="_blank">https://locoaddon.com/my-account/downloads/</a>', 
            esc_attr($title),esc_attr($title),esc_url($url),esc_attr($title),esc_attr($title)) . '.</p></div>';
        }else{
            echo '<div class="error loco-pro-missing" style="border:2px solid;border-color:#dc3232;"><p>' . 
            sprintf('You are using <strong>%s</strong> license. Please also activate <strong>%s</strong> plugin to enjoy all premium featues and automatic premium updates.</p>', 
            esc_attr($title),esc_attr($title)) . '</p></div>';
        }

     }
  }

  /*
   |----------------------------------------------------------------------
   | Notice to 'Admin' if "Loco Translate" is not active
   |----------------------------------------------------------------------
   */
  public function atlt_plugin_required_admin_notice()
  {
     if (current_user_can('activate_plugins')) {
        $url = 'plugin-install.php?tab=plugin-information&plugin=loco-translate&TB_iframe=true';
        $title = "Loco Translate";
        $plugin_info = get_plugin_data(__FILE__, true, true);
        echo '<div class="error"><p>' . 
        sprintf(__('In order to use <strong>%s</strong> plugin, please install and activate the latest version  of <a href="%s" class="thickbox" title="%s">%s</a>', 
        'automatic-translator-addon-for-loco-translate'),
         $plugin_info['Name'], esc_url($url),
          esc_attr($title), esc_attr($title)) . '.</p></div>';

        deactivate_plugins(__FILE__);
     }
  }

  /*
   |------------------------------------------------------------------------
   |  Enqueue required JS file
   |------------------------------------------------------------------------
   */
    function atlt_enqueue_scripts(){
    
        // load assets only on editor page
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'file-edit')
        {
           
           if(Helpers::userType()=="pro"){
                // if PRO version is installed then load assets
                if(Helpers::proInstalled())
                {
                    $key=Helpers::getLicenseKey();
                    if(Helpers::validKey( $key)){
                        wp_register_script( 'loco-addon-custom', ATLT_PRO_URL.'assets/js/custom.min.js',
                         array('loco-translate-admin'),ATLT_PRO_VERSION, true);
                        wp_enqueue_script('loco-addon-custom');
                        wp_register_style('loco-addon-custom-css', ATLT_PRO_URL.'assets/css/custom.min.css',null, 
                        ATLT_PRO_VERSION,'all');
                    }
                }     

            }else{
                wp_register_script( 'loco-addon-custom', ATLT_URL.'assets/js/custom.js', array('loco-translate-admin'),ATLT_VERSION, true);
                wp_enqueue_script('loco-addon-custom');
                wp_register_style('loco-addon-custom-css', ATLT_URL.'assets/css/custom.min.css',null, 
                ATLT_VERSION,'all');
            }
         

          wp_register_script( 'loco-addon-custom', ATLT_URL.'assets/js/custom.min.js', array('loco-translate-admin'),ATLT_VERSION, true);
          wp_enqueue_script('loco-addon-custom');
          wp_register_style('loco-addon-custom-css', ATLT_URL.'assets/css/custom.min.css',null, 
          ATLT_VERSION,'all');
            // load yandex widget 
            wp_register_script( 'atlt-yandex-widget', ATLT_URL.'assets/js/widget.js?widgetId=ytWidget&pageLang=en&widgetTheme=light&autoMode=false',array('loco-translate-admin'),ATLT_VERSION, true);
            wp_enqueue_script('atlt-yandex-widget');
            wp_enqueue_style('loco-addon-custom-css');
        
                $extraData['preloader_path']=ATLT_URL.'/assets/images/preloader.gif';
                $extraData['gt_preview']=ATLT_URL.'/assets/images/powered-by-google.png';
                $extraData['dpl_preview']=ATLT_URL.'/assets/images/powered-by-deepl.png';
                $extraData['yt_preview']=ATLT_URL.'/assets/images/powered-by-yandex.png';
                $extraData['ajax_url']=admin_url( 'admin-ajax.php' );
                $extraData['nonce']=wp_create_nonce('loco-addon-nonces');
               
                wp_localize_script('loco-addon-custom', 'extradata', $extraData);
                // copy object
                wp_add_inline_script( 'loco-translate-admin', '
                var returnedTarget = JSON.parse(JSON.stringify(window.loco));
                window.locoConf=returnedTarget;' );
        }
    }


/**
 * Show Admin notice to users for Rollback if compatibility issue is raised by other plugin.
 * This function is unaccessable from non-admin users
 */
function atlt_plugins_compatibility_check(){
   
    $atlt = get_plugin_data(__FILE__, false);
    $loco = get_plugin_data(  WP_PLUGIN_DIR . '/loco-translate/loco.php',false);
    $pages = array('loco-plugin');

    if( isset( $_GET['atlt_update'] ) && $_GET['atlt_update'] == true && version_compare($loco['Version'] ,'2.4.4','<' )){

        $_POST['key'] = (isset( $_GET['hash'] ) && !empty( $_GET['hash'] ) ) ? $_GET['hash'] : null;
        // forbid direct access.
        if( $_POST['key'] == null ){
            echo sprintf(__('<div class="error" style="padding:5px;margin-top:50px;">Nonce verification failed! Click to <a href="'. admin_url('admin.php?page='.$_GET['page']) . '">refresh</a> this page.</div>','automatic-translator-addon-for-loco-translate'));
            die();
        }
        $response = wp_remote_get("https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=loco-translate", array('timeout'=>300) );
        if( is_wp_error($response)){
            return;
        }
        $plugin_info = json_decode( $response['body'] );
        $upgrade_link = $plugin_info->download_link;
        
        require_once ATLT_PATH . 'includes/Helpers/Atlt_downloader.php';
        $request = new Atlt_downloader();
        echo $request->rollback( $upgrade_link, "Loco Translate updated successful!" );

        echo sprintf(__('<br/><br/><a href="'. admin_url('admin.php?page='.$_GET['page']) . '">%s</a>','automatic-translator-addon-for-loco-translate' ),'Refresh this page' );
        die();

    }else if( (isset( $_REQUEST['action'] ) &&  $_REQUEST['action'] == 'file-edit' ) && version_compare($loco['Version'] ,'2.4.4','<' ) ){
     
        $plugin_name = $atlt['Name'];
        // create nonce
        $hash = wp_create_nonce('atlt_nounce_rollback_loco');
        $url = admin_url( "admin.php?page=".$_GET['page']."&atlt_update=true&hash=" . $hash );
        echo '<div class="notice notice-error" style="margin-top:50px;">' . 
        sprintf(__('<p style="font-size:16px;">
        <strong>Notice: %s</strong> is only compatible with Loco Translate latest version 2.4.4 or higher.
        </br><a class="button button-primary" href="'.$url.'" action="atlt_update" id="atlt_update">Update</a> Loco Translate official plugin to latest version to use this addon.</p></div>', 
        'automatic-translator-addon-for-loco-translate') ,
        $plugin_name ) ;
          
    }

}

function save_all_translations (){
    if(isset($_POST['data']) && !empty($_POST['data'])){
        $allStrings=json_decode(stripslashes($_POST['data']),true);
        if($_POST['part']=="first"){
            $projectId=$_POST['project-id'].'-first';
        }else if($_POST['part']=="second"){
            $projectId=$_POST['project-id'].'-second';
        }
        else if($_POST['part']=="third"){
            $projectId=$_POST['project-id'].'-third';
        }else{
          $projectId=$_POST['project-id'];
        }   
        $rs=set_transient($projectId,$allStrings,5* MINUTE_IN_SECONDS);
        echo json_encode(array("Saved"=>"yes","res"=>$rs));    
    }   
   exit();
}

/*
|------------------------------------------------------
|    Plugin activation
|------------------------------------------------------
*/
   public function atlt_activate(){
       $plugin_info = get_plugin_data(__FILE__, true, true);
       update_option('atlt_version', $plugin_info['Version'] );
       update_option("atlt-installDate",date('Y-m-d h:i:s') );
       update_option("atlt-already-rated","no");
       update_option("atlt-type","free");
   }
   /*
   |-------------------------------------------------------
   |    Plugin deactivation
   |-------------------------------------------------------
   */
   public function atlt_deactivate(){
   }
}
  
$atlt=new LocoAutoTranslate();
  


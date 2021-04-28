<?php

/**
 * Plugin Name:          WooCommerce PagSeguro Recorrente
 * Plugin URI:           https://wcpagsegurorecorrente.com.br/
 * Description:          Sell subscriptions products and services with PagSeguro Recorrente
 * Author:               Felipe Rinaldi
 * Author URI:           https://feliperinaldi.com
 * Version:              2.0.0
 * License:              GPLv2 or later
 * Text Domain:          woocommerce-pagseguro-assinaturas
 * Domain Path:          /languages
 * WC requires at least: 3.0.0
 * WC tested up to:      4.0.1
 *
 * @package WooCommerce_PagSeguro_Assinaturas
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Include plugin updater. */
require_once( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'includes/updater.php' );

class WC_PagSeguro_Assinaturas {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '2.0.0';

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	private function __construct() {

		// Load plugin text domain.
		add_action( 'init', array( $this, 'load_init_actions' ) );

		add_action( 'load-post.php',     array( $this, 'register_meta_boxes') );

        add_action( 'load-post-new.php', array( $this, 'register_meta_boxes') );

		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ), 99, 1 );

		if( class_exists('WC_Subscriptions_Order') ) {
			
			include_once dirname( __FILE__ ) . '/includes/pagseguro-assinaturas-xml.php';
			include_once dirname( __FILE__ ) . '/includes/pagseguro-assinaturas-api.php';
			include_once dirname( __FILE__ ) . '/includes/pagseguro-assinaturas-gateway.php';

			if ( is_admin() ) {
				
				add_action( 'admin_notices', array( $this, 'ecfb_missing_notice' ) );

				$notice = get_option( 'wc-ps-assinaturas-notice-dismissed-1-4' );
				if ( '1' != $notice ) {
					add_action( 'admin_notices', array( $this, 'version_1_4_notice' ) );
				}

				add_action('wp_ajax_wc_ps_assinaturas_dismiss_notice', array($this, 'wc_ps_assinaturas_dismiss_notice') );
				
			}

			add_filter( 'woocommerce_order_actions', 							array( $this, 'add_order_meta_box_action' ), 10, 1 );
			add_action( 'woocommerce_order_action_wc_pr_force_initial_payment', array( $this, 'process_order_meta_box_action' ) );
		
		} else {

			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );

		}

		// REMOVED: Please use pagseguro notifications
		// Register the cron hook to check pre-approval status and make initial payment.
		// if ( ! wp_next_scheduled( 'wps_pre_approval_status_cron_hook' ) ) {
		//     wp_schedule_event( time(), 'hourly', 'wps_pre_approval_status_cron_hook' );
		// }

		// Remove cron if it still exists
		// Will be removed in the future.
		woocommerce_pagseguro_assinaturas_deactivate();

		// This will be removed in the future.
		add_action( 'wps_pre_approval_status_cron_hook', array( $this, 'wps_pre_approval_status_cron_exec' ) );

	}

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Get templates path.
	 *
	 * @return string
	 */
	public static function get_templates_path() {
		return plugin_dir_path( __FILE__ ) . 'templates/';
	}

	/**
	 * Load the plugin init actions.
	 */
	public function load_init_actions() {
		
		load_plugin_textdomain( 'woocommerce-pagseguro-assinaturas', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}	

	/**
	 * Register the gateway for use
	 */
	public function register_gateway( $methods ) {
		
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {

			$methods[] = 'WC_PagSeguro_Gateway_Assinaturas';

		} 

		return $methods;
	}

	/**
	 * WooCommerce Extra Checkout Fields for Brazil notice.
	 */
	public static function ecfb_missing_notice() {
		if (  ! class_exists( 'Extra_Checkout_Fields_For_Brazil' ) ) {
			include dirname( __FILE__ ) . '/includes/admin/views/html-notice-missing-ecfb.php';
		}
	}

	/**
	 * Plugin new version requiresments notice.
	 */
	public static function version_1_4_notice() {
		include dirname( __FILE__ ) . '/includes/admin/views/html-version-1-4-notice.php';
	}
	
	/**
	 * WooCommerce missing notice.
	 */
	public static function woocommerce_missing_notice() {
		include dirname( __FILE__ ) . '/includes/admin/views/html-notice-missing-woocommerce.php';
	}

	/**
	 * Plugin new version requiresments notice dismiss functionality
	 */
	public function wc_ps_assinaturas_dismiss_notice() {
		update_option( 'wc-ps-assinaturas-notice-dismissed-1-4', '1' );
		echo 'success';
		die();
	}

	/**
	 * DEPRECATED.
	 * Will be removed in the future.
	 * Cron function to check for pending orders and make payment to activate subscription.
	 */
	public function wps_pre_approval_status_cron_exec() {
		return;
	}

	/**
	 * Register meta box(es).
	 */
	public function register_meta_boxes() {
	    add_meta_box( 'pagseguro-tracker-mb', __( 'Pagseguro Tracker', 'woocommerce-pagseguro-assinaturas' ), array( $this, 'pagseguro_tracker_display_callback' ), 'shop_subscription', 'side', 'low' );
	}
	 
	/**
	 * Meta box display callback.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function pagseguro_tracker_display_callback( $post ) {
	    // Display code/markup goes here. Don't forget to include nonces!
	    $tracker = get_post_meta( $post->ID, '_pagseguro_tracker', true);
	    if( '' != $tracker ) {
	    	echo $tracker;
	    }
	    return;
	}

	/**
	 * Add a custom action to order actions select box on edit order page
	 * Only added for paid orders that haven't fired this action yet
	 *
	 * @param array $actions order actions array to display
	 * @return array - updated actions
	 */
	public function add_order_meta_box_action( $actions ) {
	    global $theorder;

	    // bail if the order has been paid for or this action has been run
	    if ( $theorder->is_paid() || ! wcs_order_contains_subscription( $theorder, 'parent' ) || 'yes' == get_post_meta( $theorder->get_id(), '_pr_processing', true ) || 'pagseguro_assinaturas' != $theorder->get_payment_method() ) {
	        return $actions;
	    }

	    // add "mark printed" custom action
	    $actions['wc_pr_force_initial_payment'] = __( 'Force Initial Payment', 'woocommerce-pagseguro-assinaturas' );
	    return $actions;
	}

	/**
	 * Add an order note when custom action is clicked
	 * Add a flag on the order to show it's been run
	 *
	 * @param \WC_Order $order
	 */
	public function process_order_meta_box_action( $order ) {
	    // add the order note
	    // translators: Placeholders: %s is a user's display name
	    $order->add_order_note( __( 'Manually triggered intial payment.', 'woocommerce-pagseguro-assinaturas' ) );
	    $pagseguro_recorrente = WC_PagSeguro_Gateway_Assinaturas::get_instance();
	    $pagseguro_recorrente->process_initial_payment( $order );
	
	}


}

add_action( 'plugins_loaded', array( 'WC_PagSeguro_Assinaturas', 'get_instance' ), 999 );

register_deactivation_hook( __FILE__, 'woocommerce_pagseguro_assinaturas_deactivate' );
 
function woocommerce_pagseguro_assinaturas_deactivate() {
	$timestamp = wp_next_scheduled( 'wps_pre_approval_status_cron_hook' );
	if( $timestamp ) {
		wp_unschedule_event( $timestamp, 'wps_pre_approval_status_cron_hook' );
	}
}


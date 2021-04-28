<?php
/*
Plugin Name: LearnPress - Gradebook
Plugin URI: http://thimpress.com/learnpress
Description: Manage grade book for user.
Author: ThimPress
Version: 3.0.10
Author URI: http://thimpress.com
Tags: learnpress, lms
Text Domain: learnpress-gradebook
Domain Path: /languages/
*/

defined( 'ABSPATH' ) or die();

define( 'LP_ADDON_GRADEBOOK_PLUGIN_PATH', dirname( __FILE__ ) );
define( 'LP_ADDON_GRADEBOOK_PLUGIN_FILE', __FILE__ );
define( 'LP_ADDON_GRADEBOOK_VERSION', '3.0.10' );
define( 'LP_ADDON_GRADEBOOK_REQUIRE_VERSION', '3.0.7' );
define( 'LP_GRADEBOOK_VER', '3.0.10' );

/**
 * Class LP_Addon_Gradebook_Preload
 */
class LP_Addon_Gradebook_Preload {

	/**
	 * LP_Addon_Gradebook_Preload constructor.
	 */
	public function __construct() {
		add_action( 'learn-press/ready', array( $this, 'load' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Load addon
	 */
	public function load() {
		LP_Addon::load( 'LP_Addon_Gradebook', 'inc/load.php', __FILE__ );
		remove_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Admin notice
	 */
	public function admin_notices() {
		?>
        <div class="error">
            <p><?php echo wp_kses(
					sprintf(
						__( '<strong>%s</strong> addon version %s requires %s version %s or higher is <strong>installed</strong> and <strong>activated</strong>.', 'learnpress-gradebook' ),
						__( 'LearnPress Gradebook', 'learnpress-gradebook' ),
						LP_ADDON_GRADEBOOK_VERSION,
						sprintf( '<a href="%s" target="_blank"><strong>%s</strong></a>', admin_url( 'plugin-install.php?tab=search&type=term&s=learnpress' ), __( 'LearnPress', 'learnpress-gradebook' ) ),
						LP_ADDON_GRADEBOOK_REQUIRE_VERSION
					),
					array(
						'a'      => array(
							'href'  => array(),
							'blank' => array()
						),
						'strong' => array()
					)
				); ?>
            </p>
        </div>
		<?php
	}
}

new LP_Addon_Gradebook_Preload();

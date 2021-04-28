<?php
/**
 * Template for displaying content of gradebook in user profile.
 *
 * * This template can be overridden by copying it to yourtheme/learnpress/addons/gradebook/dashboard.php.
 *
 * @author  ThimPress
 * @package LearnPress/Gradebook/Templates
 * @version 3.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit(); ?>

<div id="gradbook-dashboard">

    <h3><?php _e( 'Gradebook', 'learnpress-gradebook' ); ?></h3>

	<?php do_action( 'learn-press/gradbook/dashboard' ); ?>

</div>

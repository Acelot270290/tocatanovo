<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

define( 'THIM_DIR_SHORTCODES_MAP', THIM_DIR . 'vc-shortcodes/inc-map/' );

/**
 * Mapping shortcodes
 */
function ts_map_vc_shortcodes() {

    include_once( THIM_DIR_SHORTCODES_MAP . 'accordion.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'carousel-posts.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'countdown-box.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'counters-box.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'gallery-images.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'gallery-posts.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'google-map.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'heading.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'login-form.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'timetable.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'tab.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'video.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'icon-box.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'image-box.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'single-images.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'social.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'button.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'list-post.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'carousel-categories.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'link.php' );
	include_once( THIM_DIR_SHORTCODES_MAP . 'multiple-images.php' );

	if ( class_exists( 'LearnPress' ) ) {
		include_once( THIM_DIR_SHORTCODES_MAP . 'course-categories.php' );
		include_once( THIM_DIR_SHORTCODES_MAP . 'courses.php' );
		include_once( THIM_DIR_SHORTCODES_MAP . 'courses-searching.php' );
		include_once( THIM_DIR_SHORTCODES_MAP . 'list-instructors.php' );

		if ( class_exists( 'LP_Addon_Collections_Preload' )  ) {
			include_once( THIM_DIR_SHORTCODES_MAP . 'courses-collection.php' );
		}
		if (  class_exists( 'LP_Co_Instructor_Preload' ) ) {
			include_once( THIM_DIR_SHORTCODES_MAP . 'one-course-instructors.php' );
		}
	}
	if ( class_exists( 'WPEMS' ) ) {
		include_once( THIM_DIR_SHORTCODES_MAP . 'list-events.php' );
	}
	if ( thim_plugin_active( 'thim-testimonials/thim-testimonials.php' ) ) {
		include_once( THIM_DIR_SHORTCODES_MAP . 'testimonials.php' );
	}
	if ( thim_plugin_active( 'thim-our-team/init.php' )  ) {
		include_once( THIM_DIR_SHORTCODES_MAP . 'our-team.php' );
	}
	if ( class_exists( 'Thim_Portfolio' ) ) {
		include_once( THIM_DIR_SHORTCODES_MAP . 'portfolio.php' );
	}
	if ( thim_plugin_active( 'thim-twitter/thim-twitter.php' ) ) {
		include_once( THIM_DIR_SHORTCODES_MAP . 'twitter.php' );
	}
}

add_action( 'vc_before_init', 'ts_map_vc_shortcodes' );

<?php
// add cusom field for course
remove_action( 'learn-press/course-info-right', 'thim_udemy_course_payment', 20 );
add_action( 'learn-press/course-info-right', 'thim_udemy_course_payment_v4', 20 );
remove_action( 'learn-press/course-info-right', 'thim_udemy_course_wishlist', 25 );

if ( ! function_exists( 'thim_udemy_course_payment_v4' ) ) {
	function thim_udemy_course_payment_v4() {
		?>
		<div class="course-payment">
			<?php LP()->template( 'course' )->course_buttons(); ?>
		</div>
		<?php
	}
}

if ( get_theme_mod( 'thim_layout_content_page', 'normal' ) != 'new-1' ) {
	if ( ! function_exists( 'thim_course_content_lp4' ) ) {
		function thim_course_content_lp4() {
			learn_press_get_template( 'single-course/tabs/tabs-2.php' );
		}
	}

 	add_action( 'learn-press/course-content-summary', 'thim_course_content_lp4', 40 );
	remove_action( 'learn-press/single-course-summary', 'learn_press_course_thumbnail', 2 );
	remove_all_actions( 'learn-press/course-content-summary', 60 );
}

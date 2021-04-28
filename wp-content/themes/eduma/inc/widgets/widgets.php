<?php

if ( class_exists( 'Thim_Widget' ) ) {
	include_once( THIM_DIR . '/inc/r_old_widget/button/button.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/google-map/google-map.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/heading/heading.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/icon-box/icon-box.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/image-box/image-box.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/single-images/single-images.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/social/social.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/gallery-images/gallery-images.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/empty-space/empty-space.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/tab/tab.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/twitter/twitter.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/our-team/our-team.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/testimonials/testimonials.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/counters-box/counters-box.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/countdown-box/countdown-box.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/carousel-post/carousel-post.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/accordion/accordion.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/list-post/list-post.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/video/video.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/gallery-posts/gallery-posts.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/landing-image/landing-image.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/login-popup/login-popup.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/timetable/timetable.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/link/link.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/carousel-categories/carousel-categories.php' );
	include_once( THIM_DIR . '/inc/r_old_widget/multiple-images/multiple-images.php' );

	if ( class_exists( 'LearnPress' ) ) {
		include_once( THIM_DIR . '/inc/r_old_widget/courses-searching/courses-searching.php' );
		include_once( THIM_DIR . '/inc/r_old_widget/course-categories/course-categories.php' );
		include_once( THIM_DIR . '/inc/r_old_widget/courses/courses.php' );
		include_once( THIM_DIR . '/inc/r_old_widget/list-instructors/list-instructors.php' );

		if ( class_exists( 'LP_Co_Instructor_Preload' ) ) {
			include_once( THIM_DIR . '/inc/r_old_widget/one-course-instructors/one-course-instructors.php' );
		}
		if ( class_exists( 'LP_Addon_Collections_Preload' ) ) {
			include_once( THIM_DIR . '/inc/r_old_widget/courses-collection/courses-collection.php' );
		}
	}

	if ( class_exists( 'WPEMS' ) ) {
		include_once( THIM_DIR . '/inc/r_old_widget/list-event/list-event.php' );
		include_once( THIM_DIR . '/inc/r_old_widget/tab-event/tab-event.php' );
	}
	if ( class_exists( 'Thim_Portfolio' ) ) {
		include_once( THIM_DIR . '/inc/r_old_widget/portfolio/portfolio.php' );
	}

	include_once( THIM_DIR . '/inc/r_old_widget/login-form/login-form.php' );
}
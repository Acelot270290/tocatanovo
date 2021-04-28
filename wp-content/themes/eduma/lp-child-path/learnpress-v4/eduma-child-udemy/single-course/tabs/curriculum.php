<?php
/**
 * Template for displaying curriculum tab of single course.
 *
 * This template can be overridden by copying it to yourtheme/learnpress/single-course/tabs/curriculum.php.
 *
 * @author   ThimPress
 * @package  Learnpress/Templates
 * @version  3.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();
?>

<?php global $course; ?>

<?php do_action( 'thim_begin_curriculum_button' ); ?>

    <div class="course-curriculum" id="learn-press-course-curriculum">

        <div class="curriculum-scrollable">

			<?php

			/**
			 * @since 3.0.0
			 */
			do_action( 'learn-press/before-single-course-curriculum' );
			?>
	        <div class="curriculum-heading">
		        <h3 class="curriculum-title"><?php echo esc_html__('Course Curriculum','eduma-child');?></h3>

		        <div class="meta-section">
			        <!-- Display total learning in landing course page -->
			        <?php
			        $total_lessson = $course->count_items( 'lp_lesson' );
			        $total_quiz    = $course->count_items( 'lp_quiz' );

			        if ( $total_lessson || $total_quiz ) {
				        echo '<span class="courses-lessons">' . esc_html__( 'Total learning: ', 'eduma-child' );
				        if ( $total_lessson ) {
					        echo '<span class="text">' . sprintf( _n( '%d lesson', '%d lessons', $total_lessson, 'eduma-child' ), $total_lessson ) . '</span>';
				        }

				        if ( $total_quiz ) {
					        echo '<span class="text">' . sprintf( _n( ' / %d quiz', ' / %d quizzes', $total_quiz, 'eduma-child'), $total_quiz ) . '</span>';
				        }
				        echo '</span>';
			        }
			        ?>
			        <!-- End -->

			        <!-- Display total course time in landing course page -->
			        <?php
			        $course_duration_text = thim_duration_time_calculator( $course->get_id(), 'lp_course' );
			        $course_duration_meta = get_post_meta( $course->get_id(), '_lp_duration', true );
			        $course_duration      = explode( ' ', $course_duration_meta );

			        if ( ! empty( $course_duration[0] ) && $course_duration[0] != '0' ) {
				        ?>
				        <span class="courses-time"><?php esc_html_e( 'Time: ', 'eduma-child' ); ?>
					        <span class="text"><?php echo esc_html( $course_duration_text ); ?></span></span>
				        <?php
			        }
			        ?>
			        <!-- End -->
		        </div>
	        </div>

			<?php if ( $curriculum = $course->get_curriculum() ) { ?>
				<?php do_action( 'thim_before_curiculumn_item' ); ?>

                <ul class="curriculum-sections">
	                <?php
	                $i=0;
	                foreach ( $curriculum as $section ) {
		                $i++;
		                $active = ( $i==1 ) ? true : false;
		                learn_press_get_template( 'single-course/loop-section.php', array( 'section' => $section, 'active' => $active ) );
	                } ?>
                </ul>

			<?php } else { ?>

				<?php echo apply_filters( 'learn_press_course_curriculum_empty', __( 'Curriculum is empty', 'eduma' ) ); ?>

			<?php } ?>

			<?php
			/**
			 * @since 3.0.0
			 */
			do_action( 'learn-press/after-single-course-curriculum' );

			?>

        </div>

    </div>

<?php do_action( 'thim_end_curriculum_button' ); ?>
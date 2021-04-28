<?php
/**
 * Admin View: Gradebook page.
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();
?>

<div class="wrap" id="learn-press-gradebook">
    <h2><?php _e( 'Gradebook', 'learnpress-gradebook' ); ?></h2>
	<?php
	if ( ! learn_press_gradebook_verify_nonce( $course_id ) ) {
		learn_press_display_message( __( 'Error', 'learnpress-gradebook' ) );
	} else { ?>
        <p class="course-name"><a href="<?php echo get_the_permalink(); ?>"><?php echo get_the_title(); ?></a></p>
		<?php
		$args       = array(
			'course_id' => get_the_ID(),
			's'         => ! empty( $_REQUEST['s'] ) ? $_REQUEST['s'] : '',
			'from'      => ! empty( $_REQUEST['date-from'] ) ? $_REQUEST['date-from'] : '',
			'to'        => ! empty( $_REQUEST['date-to'] ) ? $_REQUEST['date-to'] : ''
		);
		$course_id  = get_the_ID();
		$list_table = new LP_Gradebook_List_Table( $args );

		$list_table->prepare_items();
		$disabled = $list_table->has_items() ? '' : 'disabled';

		$export_url_args = array(
			'export'    => 'csv',
			's'         => $args['s'],
			'date-from' => $args['from'],
			'date-to'   => $args['to']
		); ?>
		<?php $list_table->views(); ?>
		<form id="posts-filter" method="get" action="">
			<?php $list_table->display(); ?>
			<div class="gradebook-export progress-wrap progress" data-progress-percent="25" style="display: none;">
				<div class="gradebook-export progress-bar progress">
					<strong class="process-txt" 
					data-txtcompleted="<?php echo esc_attr__( 'Completed', 'learnpress-gradebook' );?>">
					<?php echo esc_html__( 'processing' , 'learnpress-gradebook' );?>
					</strong>
				</div>
			</div>
			<p>
			    <a data-status=""
			       href="<?php echo $disabled ? 'javascript: void();' : learn_press_gradebook_nonce_url( $export_url_args ); ?>"
			       class="button button-primary <?php echo $disabled; ?> gradebook-export-to-csv"><?php _e( 'Export to CSV', 'learnpress-gradebook' ); ?></a>
			</p>
			<input type="hidden" name="page" value="course-gradebook"/>
			<input type="hidden" name="gradebook-nonce"
			       value="<?php echo wp_create_nonce( 'learn-press-gradebook-' . $course_id ); ?>"/>
			<input type="hidden" name="course_id" value="<?php echo $course_id; ?>"/>
			<?php if ( ! empty( $_REQUEST['user_id'] ) ) { ?>
			    <input type="hidden" name="user_id" value="<?php echo $_REQUEST['user_id']; ?>"/>
			<?php } ?>
        </form>
	<?php } ?>
</div>
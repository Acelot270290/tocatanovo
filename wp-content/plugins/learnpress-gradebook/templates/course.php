<?php
/**
 * Template for displaying gradebook of a course in user profile page.
 *
 * * This template can be overridden by copying it to yourtheme/learnpress/addons/gradebook/course.php.
 *
 * @author  ThimPress
 * @package LearnPress/Gradebook/Templates
 * @version 3.0.4
 */


/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

if ( ! isset( $course ) || ! is_a( $course, 'LP_Gradebook_Course' ) ) {
	return;
} ?>

    <table>
        <tr>
            <th><?php _e( 'Course', 'learnpress-gradebook' ); ?></th>
            <td>
                <a href="<?php echo $course->get_permalink(); ?>"><?php echo $course->get_title(); ?></a>
            </td>
        </tr>
        <tr>
            <th><?php _e( 'Instructor', 'learnpress-gradebook' ); ?></th>
            <td><?php echo $course->get_instructor_name(); ?></td>
        </tr>
    </table>

<?php if ( $items = $course->get_items() ) { ?>

    <div class="gradebook-top-nav">
        <form method="get">
            <input type="text" name="search" placeholder="<?php _e( 'Student name, email', 'learnpress-gradebook' ); ?>"
                   value="<?php echo esc_html( LP_Request::get( 'search' ) ); ?>">
            <button><?php _e( 'Filter', 'learnpress-gradebook' ); ?></button>
        </form>
    </div>

    <table class="gradebook-list">
        <thead>
        <tr>
            <th class="course-item-user fixed-column">
				<?php _e( 'Student', 'learnpress-gradebook' ); ?>
            </th>
            <th class="user-grade fixed-column">
				<?php _e( 'Completed', 'learnpress-gradebook' ); ?>
            </th>
            <th class="user-grade fixed-column">
				<?php _e( 'Grade', 'learnpress-gradebook' ); ?>
            </th>
			<?php foreach ( $items as $item_id ) { ?>
                <th class="course-item-header" title="<?php echo esc_attr( get_the_title( $item_id ) ); ?>">
					<?php echo get_the_title( $item_id ); ?>
                </th>
			<?php } ?>
        </tr>
        </thead>
        <tbody>
		<?php $query_user = $course->get_users();
		if ( $query_user['items'] ) {
			foreach ( $query_user['items'] as $user_id ) {
				$user        = learn_press_get_user( $user_id );
				$course_data = $user->get_course_data( $course->get_id() );
				?>
                <tr>
                    <th class="course-item-user fixed-column">
						<?php echo $user->get_display_name(); ?>
                        (<a href="mailto:<?php echo $user->get_email(); ?>"><?php echo $user->get_email(); ?></a>)
                    </th>
                    <th class="user-grade fixed-column">
						<?php echo sprintf( '%d/%d', $course_data->get_completed_items(), sizeof( $course_data->get_items() ) ); ?>
                    </th>
                    <th class="user-grade fixed-column">
						<?php echo $course_data->get_percent_result();
						learn_press_label_html( $course_data->get_status_label() ); ?>
                    </th>
					<?php foreach ( $course_data->get_items() as $item ) {
						/**
						 * @var $item LP_User_Item
						 */
						switch ( $item->get_result( 'grade' ) ) {
							case 'passed':
								$grade = 'passed';
								break;
							case 'failed':
								$grade = 'failed';
								break;
							default:
								$grade = '';
						} ?>
						<?php if ( in_array( $item->get_post_type(), array( 'lp_lesson', 'lp_quiz' ) ) ) { ?>
                            <td class="course-item-data <?php echo $grade; ?>">
								<?php echo $item->get_status() ? $item->get_percent_result() : '-'; ?>
                            </td>
						<?php } else {
							do_action( 'learn-press/gradebook/profile-item-result', $item );
						} ?>
					<?php } ?>
                </tr>
				<?php
			}
		}
		?>
        </tbody>
    </table>
    <ul class="list-table-nav">
        <li class="nav-text">
			<?php
			/**
			 * @var $query_user LP_Query_List_Table
			 */
			?>
			<?php echo $query_user->get_offset_text(); ?>
        </li>
        <li class="nav-pages">
			<?php $query_user->get_nav_numbers( true ); ?>
        </li>
    </ul>
    <p>
        <a href="<?php echo learn_press_gradebook_export_url( $course->get_id() ); ?>"><?php _e( 'Export', 'learnpress-gradebook' ); ?></a>
    </p>

<?php } else {
	learn_press_display_message( __( 'No data.', 'learnpress-gradebook' ), 'error' );
}

<?php
/**
 * Template for displaying gradebook of a courses in user profile page.
 *
 * * This template can be overridden by copying it to yourtheme/learnpress/addons/gradebook/courses.php.
 *
 * @author  ThimPress
 * @package LearnPress/Gradebook/Templates
 * @version 3.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit(); ?>

<?php
$query = LP_Gradebook_Query::query_courses();

$profile      = LP_Profile::instance();
$section_link = $profile->get_tab_link();
?>

<div class="gradebook-courses">
    <h3><?php _e( 'Gradebook - Courses', 'learnpress-gradebook' ); ?></h3>

    <table class="lp-list-table profile-list-courses profile-list-table">
        <thead>
        <tr>
            <th class="column-course"><?php _e( 'Course', 'learnpress-gradebook' ); ?></th>
            <th class="column-user"><?php _e( 'Users', 'learnpress-gradebook' ); ?></th>
        </tr>
        </thead>
        <tbody>
		<?php foreach ( $query['items'] as $item ) {
			/**
			 * @var $item LP_User_Item
			 */
		    $course = learn_press_get_course( $item->get_item_id() );?>
            <tr>
                <td>
                    <a href="<?php echo esc_url( $section_link . 'course/' . $item->get_item_id() ); ?>"><?php echo $course->get_title(); ?></a>
                </td>
                <td><?php echo $course->count_students(); ?> </td>
            </tr>
		<?php } ?>
        </tbody>
        <tfoot>
        <tr class="list-table-nav">
            <td colspan="1" class="nav-text">
				<?php echo $query->get_offset_text(); ?>
            </td>
            <td colspan="1" class="nav-pages">
				<?php $query->get_nav_numbers( true ); ?>
            </td>
        </tr>
        </tfoot>
    </table>
</div>


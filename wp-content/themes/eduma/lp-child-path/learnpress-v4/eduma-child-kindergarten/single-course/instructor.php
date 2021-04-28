<?php
/**
 * Template for displaying the instructor of a course
 *
 * @author  ThimPress
 * @package LearnPress/Templates
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>

<div class="course-author" itemscope itemtype="http://schema.org/Person">
	<?php echo get_avatar( get_the_author_meta( 'ID' ), 40 ); ?>
	<div class="author-contain">
		<label itemprop="jobTitle"><?php esc_html_e( 'Teacher', 'eduma' ); ?></label>

		<div class="value" itemprop="name">
			<?php echo get_the_author(); ?>
		</div>
	</div>
</div>
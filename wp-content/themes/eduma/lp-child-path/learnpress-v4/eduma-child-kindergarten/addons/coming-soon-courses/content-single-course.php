<?php
/**
 * The template for display the content of single course
 *
 * @author  ThimPress
 * @package LearnPress/Templates
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

global $course;

//$is_required = $course->is_required_enroll();
//$user        = LP()->user;
//$is_enrolled = $user->has( 'enrolled-course', $course->id );

do_action( 'learn_press_before_single_course' ); ?>

<article id="post-<?php the_ID(); ?>" <?php post_class('learn-press'); ?> itemscope itemtype="http://schema.org/CreativeWork">

    <?php do_action( 'learn_press_before_content_coming_soon' ); ?>

    <?php do_action( 'learn_press_before_single_course_summary' ); ?>

    <?php
    the_title( '<h1 class="entry-title" itemprop="name">', '</h1>' );
    ?>
    <?php if( 'yes' !== get_post_meta( $course->get_id(), '_lp_coming_soon_metadata', true ) ) {?>
        <div class="course-meta">
            <?php learn_press_course_instructor(); ?>
            <?php learn_press_course_categories(); ?>
        </div>

        <div class="course-payment thim-enroll-kindergarten">
            <?php
            $price = get_post_meta( get_the_ID(), 'thim_course_price', true );
            $unit_price = get_post_meta( get_the_ID(), 'thim_course_unit_price', true );
            ?>
            <div class="course-price" itemprop="offers" itemscope itemtype="http://schema.org/Offer">
                <div class="value " itemprop="price" content="<?php echo esc_attr( $price ); ?>">
                    <?php echo esc_html( $price ); ?>
                </div>
                <?php echo ( ! empty( $unit_price ) ) ? '<div class="unit-price">' . $unit_price . '</div>' : ''; ?>
            </div>
            <a class="thim-enroll-course-button" href="#"><?php esc_html_e( 'Register', 'eduma-child' ); ?></a>
        </div>

    <?php }?>

    <div class="thim-top-course<?php echo !has_post_thumbnail($course_id) ? ' no-thumbnail' : ''; ?>">
        <?php if( 'yes' !== get_post_meta( $course_id, '_lp_coming_soon_countdown', true ) ) {?>
            <?php do_action( 'learn_press_content_coming_soon_countdown' ); ?>
        <?php }?>
        <?php learn_press_get_template( 'single-course/thumbnail.php', array() ); ?>
    </div>

    <?php if( 'yes' !== get_post_meta( $course->get_id(), '_lp_coming_soon_details', true ) ) {?>

        <div class="course-summary">
            <?php learn_press_get_template( 'single-course/content-landing.php' ); ?>
        </div>

        <?php do_action( 'learn_press_after_single_course_summary' ); ?>

        <div id="contact-form-registration">
            <?php

            $thim_options = get_theme_mods();
            if ( ! empty( $thim_options['thim_learnpress_shortcode_contact'] ) ) {
                echo do_shortcode( $thim_options['thim_learnpress_shortcode_contact'] );
            }

            ?>
        </div>

        <?php do_action( 'learn_press_after_single_course_summary' ); ?>

    <?php }?>

    <div class="message message-warning learn-press-message coming-soon-message"><?php do_action( 'learn_press_content_coming_soon_message' ); ?></div>

    <?php do_action( 'learn_press_after_content_coming_soon' ); ?>

</article><!-- #post-## -->
<?php thim_related_courses(); ?>
<?php do_action( 'learn_press_after_single_course' ); ?>

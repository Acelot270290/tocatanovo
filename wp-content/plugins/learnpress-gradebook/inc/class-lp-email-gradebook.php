<?php
/**
 * Class LP_Email_Rejected_Course
 *
 * Rejected new course email.
 *
 * @author  ThimPress
 * @package LearnPress/Classes
 * @version 3.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'LP_Email_Gradebook' ) ) {

	/**
	 * Class LP_Email_Rejected_Course
	 */
	class LP_Email_Gradebook extends LP_Email {
		/**
		 * LP_Email_Rejected_Course constructor.
		 */
		public function __construct() {
			$this->id          = 'gradebook';
			$this->title       = __( 'Gradebook', 'learnpress-gradebook' );
			$this->description = __( 'Settings for email when a course is rejected.', 'learnpress-gradebook' );

			$this->default_subject = __( '[{{site_title}}] Your course {{course_name}} has been rejected', 'learnpress-gradebook' );
			$this->default_heading = __( 'Rejected course', 'learnpress-gradebook' );

			parent::__construct();
		}

		/**
		 * Trigger email.
		 *
		 * @param $course_id
		 *
		 * @return bool
		 */
		public function trigger( $course_id ) {

			if ( ! $this->enable ) {
				return false;
			}

			$this->recipient = 'example@gmail.com';

			$return = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

			return $return;
		}
	}
}

return new LP_Email_Gradebook();
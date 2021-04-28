<?php

/**
 * Class LP_Gradebook_Course_Site
 */
class LP_Gradebook_Course_Site {

	/**
	 * @var int
	 */
	protected $_limit = 10;

	/**
	 * @var int
	 */
	protected $_paged = 1;

	/**
	 * LP_Gradebook_Course constructor.
	 *
	 * @param mixed $course_id
	 */
	public function __construct() {
		add_action( 'template_include', array( $this, 'init_hook' ), 10 );
	}

	public function init_hook($template){
		if(learn_press_is_course()){
			$course 	= learn_press_get_course();
			$author_id 	= $course->get_author()->get_id();
			$user_id 	= learn_press_get_current_user_id();
			if (current_user_can('administrator') || (current_user_can ( LP_TEACHER_ROLE ) && $user_id == $author_id)) {
				add_filter ( 'learn-press/course-tabs', array (
						$this,
						'add_tab_gradebook' 
				), 15 );
			}
		}
		return $template;
	}

	public function add_tab_gradebook( $tabs ) {
		$tabs['gradebook'] = array(
				'title'    => __( 'Gradebook', 'learnpress-gradebook' ),
				'priority' => 40,
				'callback' => array($this, 'add_tab_gradebook_content')
		);
		return $tabs;
	}
	
	public function add_tab_gradebook_content( $args ) {
		learn_press_get_template( 'course-users.php', array(), learn_press_template_path() . '/addons/gradebook/', LP_ADDON_GRADEBOOK_PLUGIN_PATH.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR );
	}

}

// new LP_Gradebook_Course_Site();
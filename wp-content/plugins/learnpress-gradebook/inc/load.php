<?php

/**
 * Class LP_Addon_Gradebook
 *
 * @since 3.0.0
 */
class LP_Addon_Gradebook extends LP_Addon {
	/**
	 * Gradebook version
	 *
	 * @var string
	 */
	public $version = null;

	/**
	 * LP require version
	 *
	 * @var null|string
	 */
	public $require_version = null;

	/**
	 * LP_Addon_Gradebook constructor.
	 */
	public function __construct() {

		$this->version         = LP_ADDON_GRADEBOOK_VERSION;
		$this->require_version = LP_ADDON_GRADEBOOK_REQUIRE_VERSION;

		parent::__construct();

		add_action( 'init', array( $this, 'init' ) );
		add_filter( 'learn-press/profile-tabs', array( $this, 'profile_tabs' ) );

		// add admin course column
		add_filter( 'manage_lp_course_posts_columns', array( $this, 'manage_course_posts_columns' ) );
		add_action( 'manage_lp_course_posts_custom_column', array( $this, 'manage_course_post_column' ), 10, 2 );

		// enqueue script
// 		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// register page
		add_action( 'admin_menu', array( $this, 'register_gradebook_page' ) );

		add_action( 'learn-press/register-emails', array( $this, 'load_emails' ) );
		add_action( 'plugins_loaded', array( $this, 'backward_load_emails' ) );
	}

	public function load_emails( &$emails ) {
		//require_once LP_ADDON_GRADEBOOK_PLUGIN_PATH . "/inc/class-lp-email-gradebook.php";
		$emails['LP_Email_Gradebook'] = include( LP_ADDON_GRADEBOOK_PLUGIN_PATH . "/inc/class-lp-email-gradebook.php" );
	}

	public function backward_load_emails() {
		if ( class_exists( 'LP_Emails' ) ) {
			$emails = LP_Emails::instance()->emails;
			$this->load_emails( $emails );
		}
	}

	/**
	 * Init
	 */
	public function init() {
		if ( is_admin() ) {
			$action = LP_Request::get_string( 'action' );
			if ( is_admin() && 'gradebook_export' == $action ) {
				require_once LP_ADDON_GRADEBOOK_PLUGIN_PATH . '/inc/class-gradebook-export.php';
			}
		}
		if ( ( $course_id = LP_Request::get_int( 'export-gradebook' ) ) && wp_verify_nonce( LP_Request::get_string( 'export-nonce' ), 'gradebook-export-course-' . $course_id ) ) {
			$this->do_export( $course_id );
		}

		$this->_enqueue_assets();
	}

	public function admin_enqueue_scripts() {
		if ( is_admin() ) {
// 			wp_enqueue_style( 'learn-press-gradebook', plugins_url( '/', LP_ADDON_GRADEBOOK_PLUGIN_FILE ) . 'assets/css/gradebook.css' );
// 			wp_enqueue_script( 'learn-press-gradebook-export', plugins_url( '/', LP_ADDON_GRADEBOOK_PLUGIN_FILE ) . 'assets/js/gradebook-export.js', array(
// 				'jquery',
// 				'jquery-ui-datepicker'
// 			) );
// 			wp_register_style('jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css');
// 			wp_enqueue_style( 'jquery-ui' );
		}
	}

	/**
	 * Export list.
	 *
	 * @param $course_id
	 *
	 * @throws PHPExcel_Exception
	 * @throws PHPExcel_Writer_Exception
	 */
	public function do_export( $course_id ) {

		$course = new LP_Gradebook_Course( $course_id );

		if ( ! $course || ! $items = $course->get_items() ) {
			return;
		}

		$users = $course->get_users( array( 'limit' => - 1 ) );

		if ( ! $users['items'] ) {
			return;
		}

		if ( ! class_exists( 'PHP_Excel' ) ) {
			include_once 'PHPExcel/PHPExcel.php';
			include 'PHPExcel/PHPExcel/Writer/Excel2007.php';
		}

		$user = learn_press_get_current_user();

		PHPExcel_Cell::setValueBinder( new PHPExcel_Cell_AdvancedValueBinder() );

		$objPHPExcel = new PHPExcel();
		$activeSheet = $this->_output_excel_header( $objPHPExcel, $user, $course );

		$rowIndex = 2;

		foreach ( $users['items'] as $user_id ) {
			$course_user = learn_press_get_user( $user_id );

			// User does not exists?
			if ( ! $course_user ) {
				continue;
			}

			$course_data = $course_user->get_course_data( $course->get_id() );

			if ( ! $course_data ) {
				continue;
			}

			// Output student name in column A
			$colName = PHPExcel_Cell::stringFromColumnIndex( 0 );
			$activeSheet->setCellValue( "{$colName}{$rowIndex}", $course_user->get_display_name() );

			// Output student email in column B
			$colName = PHPExcel_Cell::stringFromColumnIndex( 1 );
			$activeSheet->setCellValue( "{$colName}{$rowIndex}", $course_user->get_email() );

			// Output completed items in column C
			$colName = PHPExcel_Cell::stringFromColumnIndex( 2 );
			$activeSheet->setCellValue( "{$colName}{$rowIndex}", sprintf( '%d/%d', $course_data->get_completed_items(), sizeof( $course_data->get_items() ) ) );

			// Output completed items in column D
			$colName = PHPExcel_Cell::stringFromColumnIndex( 3 );
			$activeSheet->setCellValue( "{$colName}{$rowIndex}", $course_data->get_percent_result() . ' ' . $course_data->get_status_label() );

			$course_items = $course_data->get_items();

			$index = 5;
			// Output item result in column D, E, F, ...
			foreach ( $course_items as $item ) {
				/**
				 * @var $item LP_User_Item
				 */
				switch ( $item->get_post_type() ) {
					case LP_LESSON_CPT:
						$result = $item->get_status() ? $item->get_percent_result() : '-';
						break;
					case LP_QUIZ_CPT:
						$result = $item->get_status() ? $item->get_percent_result() : '-';
						break;
					default:
						$result = apply_filters( 'learn-press/gradebook/export-item-result', '-', $item );
				}

				$colName = PHPExcel_Cell::stringFromColumnIndex( $index - 1 );
				$activeSheet->setCellValue( "{$colName}{$rowIndex}", $result );
				$activeSheet->getStyle( "{$colName}{$rowIndex}" )->getNumberFormat()->setFormatCode( PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE_00 );

				// Next column
				$index ++;
			}

			// Next row
			$rowIndex ++;
		}

		$filename = sanitize_file_name( 'gradebook-' . sanitize_title( $course->get_title() ) . '-' . $course->get_id() . '.xlsx' );
		$upload   = wp_upload_dir();
		$file     = $upload['basedir'] . "/{$filename}";

		// Save as Excel 2007 format in wp upload dir
		$objWriter = new PHPExcel_Writer_Excel2007( $objPHPExcel );
		$objWriter->save( $file );

		// Download
		header( 'Location: ' . $upload['baseurl'] . "/{$filename}" );
		die();
	}

	/**
	 * @param PHPExcel  $objPHPExcel
	 * @param LP_User   $user
	 * @param LP_Course $course
	 *
	 * @return PHPExcel_Worksheet
	 */
	protected function _output_excel_header( &$objPHPExcel, $user, $course ) {

		$items = $course->get_items();

		$objPHPExcel->getProperties()->setCreator( $user->get_display_name() );
		$objPHPExcel->getProperties()->setLastModifiedBy( $user->get_display_name() );
		$objPHPExcel->getProperties()->setTitle( "Gradebook - " . $course->get_title() );
		$objPHPExcel->getProperties()->setSubject( "Gradebook - " . $course->get_title() );
		$objPHPExcel->getProperties()->setDescription( "Gradebook - " . $course->get_title() );
		$objPHPExcel->setActiveSheetIndex( 0 );

		// Get active sheet
		$activeSheet = $objPHPExcel->getActiveSheet();
		$activeSheet->setTitle( 'List' );

		/*
		 * Output course items as header in row #1
		 */

		// Output student name in column A
		$colName = PHPExcel_Cell::stringFromColumnIndex( 0 );
		$activeSheet->setCellValue( "{$colName}1", __( 'Student', 'learnpress-gradebook' ) );

		// Output student email in column B
		$colName = PHPExcel_Cell::stringFromColumnIndex( 1 );
		$activeSheet->setCellValue( "{$colName}1", __( 'Email', 'learnpress-gradebook' ) );

		// Output completed items in column C
		$colName = PHPExcel_Cell::stringFromColumnIndex( 2 );
		$activeSheet->setCellValue( "{$colName}1", __( 'Completed', 'learnpress-gradebook' ) );

		// Output Grade in column D
		$colName = PHPExcel_Cell::stringFromColumnIndex( 3 );
		$activeSheet->setCellValue( "{$colName}1", __( 'Grade', 'learnpress-gradebook' ) );

		$activeSheet->getStyle( "A1" )->getFont()->setBold( true );
		$activeSheet->getStyle( "B1" )->getFont()->setBold( true );
		$activeSheet->getStyle( "C1" )->getFont()->setBold( true );
		$activeSheet->getStyle( "D1" )->getFont()->setBold( true );

		// Output item names in column D, E, F, ...
		$index = 5;
		foreach ( $items as $item ) {
			$item    = $course->get_item( $item );
			$colName = PHPExcel_Cell::stringFromColumnIndex( $index - 1 );

			$activeSheet->setCellValue( "{$colName}1", $item->get_title() );
			$activeSheet->getStyle( "{$colName}1" )->getFont()->setBold( true );
			$index ++;
		}

		$activeSheet->getColumnDimension( 'A' )->setWidth( 15 );
		$activeSheet->getColumnDimension( 'B' )->setWidth( 15 );
		$activeSheet->getColumnDimension( 'C' )->setWidth( 15 );
		$activeSheet->getColumnDimension( 'D' )->setWidth( 15 );

		return $activeSheet;
	}

	/**
	 * Enqueue assets used by Gradebook.
	 *
	 * @since 3.0
	 */
	protected function _enqueue_assets() {
		if ( is_admin() ) {
			$page = learn_press_get_request( 'page', '' );
			if ( 'course-gradebook' == $page ) {
				wp_enqueue_style( 'learn-press-gradebook', plugins_url( '/', LP_ADDON_GRADEBOOK_PLUGIN_FILE ) . 'assets/css/gradebook.css' );
				wp_enqueue_script( 'learn-press-gradebook-export', plugins_url( '/', LP_ADDON_GRADEBOOK_PLUGIN_FILE ) . 'assets/js/gradebook-export.js', array(
					'jquery',
					'jquery-ui-datepicker'
				) );
				wp_register_style( 'jquery-ui', '://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' );
				wp_enqueue_style( 'jquery-ui' );
// 		        $assets->enqueue_script( 'gradebook-export', $this->get_plugin_url( 'assets/js/gradebook-export.js' ) );
			}
		} else {
			$assets = learn_press_assets();
			$assets->enqueue_style( 'gradebook', $this->get_plugin_url( 'assets/css/gradebook.css' ) );
			$assets->enqueue_script( 'table-js', $this->get_plugin_url( 'assets/js/table-js.js' ), array( 'jquery' ) );
			$assets->enqueue_script( 'gradebook', $this->get_plugin_url( 'assets/js/gradebook.js' ) );
		}
	}

	/**
	 * Include dependencies files.
	 *
	 * @since 3.0
	 */
	protected function _includes() {

		require_once LP_ADDON_GRADEBOOK_PLUGIN_PATH . "/inc/functions.php";
		require_once LP_ADDON_GRADEBOOK_PLUGIN_PATH . "/inc/class-lp-gradebook-query.php";
		require_once LP_ADDON_GRADEBOOK_PLUGIN_PATH . "/inc/class-lp-gradebook-course.php";
		require_once LP_ADDON_GRADEBOOK_PLUGIN_PATH . "/inc/class-lp-course-gradebook.php";
	}

	/**
	 * Add custom tabs into user's profile.
	 *
	 * @since 3.0
	 *
	 * @param array $tabs
	 *
	 * @return mixed
	 */
	public function profile_tabs( $tabs ) {
		// Only admin or instructor can view
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'lp_teacher' ) ) {
			return $tabs;
		}

		$tabs['gradebook'] = array(
			'title'    => __( 'Gradebook', 'learnpress-gradebook' ),
			'slug'     => 'gradebook',
			'callback' => array( $this, 'tab_courses' ),
			'priority' => 12,
			'sections' => array(
//				'dashboard' => array(
//					'title'    => __( 'Dashboard', 'learnpress-gradebook' ),
//					'slug'     => 'dashboard',
//					'callback' => array( $this, 'tab_dashboard' ),
//					'priority' => 10
//				),
				'courses' => array(
					'title'    => __( 'Courses', 'learnpress-gradebook' ),
					'slug'     => 'courses',
					'callback' => array( $this, 'tab_courses' ),
					'priority' => 10,
					'hidden'   => true
				),
				'course'  => array(
					'title'    => __( 'Course', 'learnpress-gradebook' ),
					'slug'     => 'course',
					'callback' => array( $this, 'tab_course' ),
					'priority' => 20,
					'hidden'   => true
				),
//				'users'     => array(
//					'title'    => __( 'Users', 'learnpress-gradebook' ),
//					'slug'     => 'users',
//					'callback' => array( $this, 'tab_users' ),
//					'priority' => 10
//				)
			)
		);

		return $tabs;
	}

	/**
	 * Content of profile dashboard page.
	 *
	 * @since 3.0
	 */
	public function tab_dashboard() {
		learn_press_gradebook_get_template( 'dashboard.php' );
	}

	/**
	 * Content of profile course page.
	 *
	 * @since 3.0
	 */
	public function tab_course() {
		if ( $id = LP_Request::get_int( 'view_id', '', 'wp' ) ) {
			if ( $course = new LP_Gradebook_Course( $id ) ) {
				learn_press_gradebook_get_template( 'course.php', array( 'course' => $course ) );
			}
		}
	}

	/**
	 * Content of profile courses page.
	 *
	 * @since 3.0
	 */
	public function tab_courses() {
		if ( $id = LP_Request::get_int( 'course-id' ) ) {
			if ( $course = learn_press_get_course( $id ) ) {
				learn_press_gradebook_get_template( 'course.php' );
			}
		} else {
			learn_press_gradebook_get_template( 'courses.php' );
		}
	}

	public function tab_users() {
		learn_press_gradebook_get_template( 'users.php' );
	}

	/**
	 * Register gradebook admin page.
	 */
	public function register_gradebook_page() {
		$hook = add_submenu_page(
			'',
			__( 'Course Gradebook', 'learnpress-gradebook' ),
			'',
			'edit_published_lp_courses',
			'course-gradebook',
			array( $this, 'gradebook_page' )
		);
		add_action( "load-$hook", array( $this, 'add_options' ) );
	}

	/**
	 * Add options.
	 */
	public function add_options() {
		$args = array(
			'label'   => __( 'Number of items per page', 'learnpress-gradebook' ),
			'default' => 20,
			'option'  => 'users_per_page'
		);
		add_screen_option( 'per_page', $args );
	}

	/**
	 * Admin gradebook callback.
	 */
	public function gradebook_page() {
		$course_id = ! empty( $_REQUEST['course_id'] ) ? $_REQUEST['course_id'] : 0;

		global $post;
		$post = get_post( $course_id );
		setup_postdata( $post );
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		require_once LP_ADDON_GRADEBOOK_PLUGIN_PATH . '/inc/admin/class-gradebook-list-table.php';
		require LP_ADDON_GRADEBOOK_PLUGIN_PATH . '/inc/admin/gradebook.php';
		wp_reset_postdata();
	}

	/**
	 * Add grade book column to course page in admin.
	 *
	 * @param  array $column
	 *
	 * @return array
	 */
	function manage_course_posts_columns( $column ) {
		$date                = ! empty( $column['date'] ) ? $column['date'] : null;
		$column['gradebook'] = __( 'Gradebook', 'learnpress-gradebook' );
		if ( $date ) {
			unset( $column['date'] );
			$column['date'] = $date;
		}

		return $column;
	}

	/**
	 * Add the grade book column content.
	 *
	 * @param $column
	 * @param $post_id
	 */
	function manage_course_post_column( $column, $post_id ) {
		switch ( $column ) {
			case 'gradebook':
				printf( '<a href="%s" target="">%s</a>',
					learn_press_gradebook_nonce_url( array( 'course_id' => $post_id ) ),
					__( 'View', 'learnpress-gradebook' )
				);
				break;
		}
	}
}

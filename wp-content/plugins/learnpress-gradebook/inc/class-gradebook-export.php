<?php


class LP_Gradebook_Export {


	function __construct( $args ) {
		add_action( 'wp_ajax_gradebook_export', array( $this, 'ajax_gradebook_export' ) );
	}


	public function ajax_gradebook_export() {
		global $wpdb;

		$limit  = 5;
		$action = filter_input( INPUT_POST, 'action' );
		$step   = filter_input( INPUT_POST, 'step' );

		if ( ! $action && ! $step ) {
			$action = filter_input( INPUT_GET, 'action' );
			$step   = filter_input( INPUT_GET, 'step' );
			if ( 'done' == $step ) {
				$course_id       = filter_input( INPUT_GET, 'course_id', FILTER_SANITIZE_NUMBER_INT );
				$date_from       = filter_input( INPUT_GET, 'date_from' );
				$date_to         = filter_input( INPUT_GET, 'date_to' );
				$page            = filter_input( INPUT_GET, 'page' );
				$total           = filter_input( INPUT_GET, 'total' );
				$gradebook_nonce = filter_input( INPUT_GET, 'gradebook-nonce' );
				$type            = filter_input( INPUT_GET, 'type' );
				$args            = array(
					'action'          => $action,
					'step'            => $step,
					'course_id'       => $course_id,
					'date-from'       => $date_from,
					'date-to'         => $date_to,
					'page'            => $page,
					'total'           => $total,
					'limit'           => $limit,
					'gradebook-nonce' => $gradebook_nonce,
					'type'            => $type
				);
				$this->step_done( $course_id, $args );
				wp_die();
			}
		}


		if ( $step == 'init' ) {
			$data = $this->step_init();
			echo json_encode( $data );
		} else {
			$course_id = filter_input( INPUT_POST, 'course_id', FILTER_SANITIZE_NUMBER_INT );
			$date_from = filter_input( INPUT_POST, 'date_from' );
			$date_to   = filter_input( INPUT_POST, 'date_to' );
			$page      = filter_input( INPUT_POST, 'page' );
			$total     = filter_input( INPUT_POST, 'total' );

			$gradebook_nonce = filter_input( INPUT_POST, 'gradebook-nonce' );
			$type            = filter_input( INPUT_POST, 'type' );

			$args = array(
				'action'          => $action,
				'step'            => $step,
				'course_id'       => $course_id,
				'date-from'       => $date_from,
				'date-to'         => $date_to,
				'page'            => $page,
				'total'           => $total,
				'limit'           => $limit,
				'gradebook-nonce' => $gradebook_nonce,
				'type'            => $type
			);

			if ( 'start' == $step ) {
				$respon = $this->step_start( $course_id, $args );
				echo json_encode( $respon );
			} elseif ( 'export' == $step ) {
				$respon = $this->step_export( $course_id, $args );
				echo json_encode( $respon );
			}
		}
		wp_die(); // this is required to terminate immediately and return a proper response
	}


	public function step_done( $course_id, $args = array() ) {
		$group      = 'gradebook_export';
		$key_prefix = md5( $group . '-' . $args['course_id'] . '-' . $args['date-from'] . '-' . $args['date-to'] . '-' . $args['gradebook-nonce'] );
		$page_last  = intval( $args['total'] ) / $args['limit'];
		if ( $page_last > intval( $page_last ) ) {
			$page_last = intval( $page_last ) + 1;
		}
		$rows     = array();
		$head_row = $this->build_head_row( $course_id );
		$rows[]   = $head_row;
		for ( $i = 1; $i <= $page_last; $i ++ ) {
			$key = $key_prefix . '_' . $i;
			// $rows_page = get_transient( $key );
			$rows_page = learn_press_gradebook_get_transient( $key );
			if ( $rows_page && is_array( $rows_page ) && ! empty( $rows_page ) ) {
				$rows = array_merge( $rows, $rows_page );
			}
		}


		$filename = 'export_gradebook_course_' . $course_id;
		if ( strtolower( $args['type'] ) == 'csv' ) {
			header( "Cache-Control: public" );
			header( "Content-Type: application/octet-stream" );
			header( "Content-Type: text/csv; charset=utf-8" );
			header( 'Content-Disposition: attachment; filename=' . $filename . '.csv' );
			header( 'Pragma: no-cache' );
			foreach ( $rows as $k => $row ) {
				echo $k ? "\n" : '';
				echo join( ',', $row );
			}
			exit();
		}
	}


	public function step_export( $course_id, $args = array() ) {
		$page = $args['page'];
		if ( ! $page ) {
			$page = 1;
		}
		$students = $this->get_enrolled_students( $course_id, $args );
		$rows     = array();
		foreach ( $students as $student ) {
			$rows[] = $this->build_row( $course_id, $student->ID );
		}
		// create key
		$group = 'gradebook_export';
		$key   = md5( $group . '-' . $args['course_id'] . '-' . $args['date-from'] . '-' . $args['date-to'] . '-' . $args['gradebook-nonce'] ) . '_' . $page;
// 		$res = set_transient( $key, $rows, 60 * 60 ); // stored row in transient
		$res       = learn_press_gradebook_set_transient( $key, $rows );
		$page_last = round( $args['total'] / $args['limit'], 0, PHP_ROUND_HALF_EVEN );
		if ( $page_last < $args['total'] / $args['limit'] ) {
			$page_last ++;
		}
		if ( $page >= $page_last ) {
			$args['step'] = 'done';
			$args['type'] = 'csv';
		} else {
			$args['page'] = $page + 1;
		}

		return $args;
	}


	/**
	 *
	 */
	public function step_start( $course_id, $args = array() ) {
		# get total enrolled students
		$args['total'] = $this->get_total_enrolled_students( $course_id, $args );
		$args['step']  = 'export';

		return $args;
	}

	/**
	 *
	 */
	public function step_init() {
		# get total enrolled students
		$action = filter_input( INPUT_POST, 'action' );
		$step   = filter_input( INPUT_POST, 'step' );
		$href   = filter_input( INPUT_POST, 'href' );

		$parser_url = parse_url( $href );
		$parse_str  = array();

		parse_str( $parser_url['query'], $parse_str );

		$gradebook_nonce = $parse_str['gradebook-nonce'];// => 33baecb48d
		$course_id       = $parse_str['course_id'];// => 34
		$date_from       = $parse_str['date-from'];// =>
		$date_to         = $parse_str['date-to'];// =>
		$export          = $parse_str['export'];// => csv

		// using gradebook_nonce & course_id & date_from & date_to to create cache_id
		$parse_str['action'] = $action;
		$parse_str['step']   = 'start';
		$args                = array(
			'action'          => $action,
			'step'            => 'start',
			'course_id'       => $course_id,
			'date-from'       => $date_from,
			'date-to'         => $date_to,
			'gradebook-nonce' => $gradebook_nonce
		);

		return $args;
	}


	/**
	 *
	 * @param unknown $course_id
	 * @param array $args
	 *
	 * @return array|object|NULL
	 */
	public function get_enrolled_students( $course_id, $args = array() ) {
		global $wpdb;
		$date_from    = isset( $args['date-from'] ) ? $args['date-from'] : '';
		$date_to      = isset( $args['date-to'] ) ? $args['date-to'] : '';
		$prepare_args = array(
			LP_COURSE_CPT,
			$course_id
		);

		$sql_date = "";

		if ( $date_from ) {
			$sql_date       .= " AND lui.start_time > %s ";
			$prepare_args[] = $date_from;
		}

		if ( $date_to ) {
			$sql_date       .= " AND lui.start_time < %s ";
			$prepare_args[] = $date_to;
		}

		$prepare_args[] = LP_COURSE_CPT;
		$prepare_args[] = $course_id;

		$limit = isset( $args['limit'] ) ? $args['limit'] : 0;
		$page  = isset( $args['page'] ) ? intval( $args['page'] ) : 1;
		if ( ! $page ) {
			$page = 1;
		}

		$sql_limit = "";
		if ( $limit ) {
			$limit_start = ( $page - 1 ) * $limit;
			$sql_limit   = " LIMIT {$limit_start}, {$limit}";
		}

		$sql = "
			SELECT 
				`u`.`ID`, 
				`u`.`user_login`, 
				`u`.`display_name`, 
				`lui`.* 
			FROM 
				`{$wpdb->users}` AS `u` 
					INNER JOIN 
				`{$wpdb->prefix}learnpress_user_items` AS `lui` ON `u`.`ID` = `lui`.`user_id`
			WHERE
				`lui`.`item_type` = %s
					AND `lui`.`item_id` = %d
						" . $sql_date . "
					AND `lui`.`user_item_id` IN (
							SELECT 
								MAX(`user_item_id`)
							FROM
								`{$wpdb->prefix}learnpress_user_items`
							WHERE
								`item_type` = %s AND `item_id` = %d
							GROUP BY `user_id`
						)
			ORDER BY `lui`.`user_id` , `lui`.`start_time` " . $sql_limit;

		$query = $wpdb->prepare( $sql, $prepare_args );
		$rows  = $wpdb->get_results( $query );

		return $rows;
	}


	public function get_enrolled_students_id( $course_id, $args = array(), $force = false ) {
		global $wpdb;
		$date_from   = isset( $args['date-from'] ) ? $args['date-from'] : '';
		$date_to     = isset( $args['date-to'] ) ? $args['date-to'] : '';
		$limit       = $args['limit'];
		$page        = $args['page'] ? $args['page'] : 1;
		$limit_start = ( $page - 1 ) . $limit;

		$prepare_args = array(
			LP_COURSE_CPT,
			$course_id
		);

		$sql_date = "";
		if ( $date_from ) {
			$sql_date       .= " AND lui.start_time > %s ";
			$prepare_args[] = $date_from;
		}

		if ( $date_to ) {
			$sql_date       .= " AND lui.start_time < %s ";
			$prepare_args[] = $date_to;
		}

		$prepare_args[] = LP_COURSE_CPT;
		$prepare_args[] = $course_id;

		$sql = "
			SELECT
				`u`.`ID`
			FROM
				`{$wpdb->users}` AS `u`
					INNER JOIN
				`{$wpdb->prefix}learnpress_user_items` AS `lui` ON `u`.`ID` = `lui`.`user_id`
			WHERE
				`lui`.`item_type` = %s
					AND `lui`.`item_id` = %d
					" . $sql_date . "
					AND `lui`.`user_item_id` IN (
							SELECT 
								MAX(`user_item_id`)
							FROM
								`{$wpdb->prefix}learnpress_user_items`
							WHERE
								`item_type` = %s AND `item_id` = %d
							GROUP BY `user_id`
						)
			ORDER BY `lui`.`user_id` , `lui`.`start_time`";

		$query = $wpdb->prepare( $sql, $prepare_args );
		$ids   = $wpdb->get_col( $query );

		return $ids;
	}


	public function get_total_enrolled_students( $course_id, $args = array() ) {
		global $wpdb;
		$date_from = isset( $args['date-from'] ) ? $args['date-from'] : '';
		$date_to   = isset( $args['date-to'] ) ? $args['date-to'] : '';

		$prepare_args = array(
			LP_COURSE_CPT,
			$course_id
		);

		$sql_date = "";
		if ( $date_from ) {
			$sql_date       .= " AND lui.start_time > %s ";
			$prepare_args[] = $date_from;
		}

		if ( $date_to ) {
			$sql_date       .= " AND lui.start_time < %s ";
			$prepare_args[] = $date_to;
		}

		$prepare_args[] = LP_COURSE_CPT;
		$prepare_args[] = $course_id;

		$sql = "
			SELECT
				COUNT(*)
			FROM
				`{$wpdb->users}` AS `u`
					INNER JOIN
				`{$wpdb->prefix}learnpress_user_items` AS `lui` ON `u`.`ID` = `lui`.`user_id`
			WHERE
				`lui`.`item_type` = %s
					AND `lui`.`item_id` = %d
					" . $sql_date . "
					AND `lui`.`user_item_id` IN (
						SELECT 
							MAX(`user_item_id`)
						FROM
							`{$wpdb->prefix}learnpress_user_items`
						WHERE
							`item_type` = %s AND `item_id` = %d
						GROUP BY `user_id`
					)
			ORDER BY `lui`.`user_id` , `lui`.`start_time`";

		$query = $wpdb->prepare( $sql, $prepare_args );
		$var   = $wpdb->get_var( $query );

		return $var;
	}


	public function build_row( $course_id, $user_id ) {
		$user        = learn_press_get_user( $user_id );
		$course_data = $user->get_course_data( $course_id );
		$row         = array(
			$user->get_id(),
			$this->prepareCellContent( $user->get_data( 'user_login' ) ),
			$this->prepareCellContent( $user->get_email() ),
			$this->prepareCellContent( $user->get_display_name() )
		);
		foreach ( $course_data->get_items() as $item ) {
			$status = '';
			if ( $item->get_status() ) {
				if ( $grade && $item->get_type() == LP_QUIZ_CPT ) {
					$status .= $item->get_status_label( $grade );
					$status .= $item->get_status() ? ' - ' . $item->get_percent_result() : '-';
				} else {
					$status .= apply_filters( 'learn-press/gradebook/export-item-status', $item->get_status_label(), $item->get_type(), $item->get_status() );
				}
			} else {
				$status .= '-';
			}
			$row[] = $this->prepareCellContent( $status );
		}
		$row[] = $this->prepareCellContent( $course_data->get_percent_result() );
		$row[] = $this->prepareCellContent( $course_data->get_start_time( get_option( 'date_format' ) ) );
		$row[] = $this->prepareCellContent( $course_data->get_grade() );

		return $row;
	}


	public function build_head_row( $course_id ) {
		$course           = learn_press_get_course( $course_id );
		$curricutum_items = $course->get_curriculum_items();
		$row              = array(
			$this->prepareCellContent( __( 'User ID', 'learnpress-gradebook' ) ),
			$this->prepareCellContent( __( 'User login', 'learnpress-gradebook' ) ),
			$this->prepareCellContent( __( 'User email', 'learnpress-gradebook' ) ),
			$this->prepareCellContent( __( 'User display name', 'learnpress-gradebook' ) )
		);
		foreach ( $curricutum_items as $item ) {
			$title = get_the_title( $item );
			if ( LP_QUIZ_CPT == get_post_type( $item ) ) {
				$quiz = learn_press_get_quiz( $item->item_id );
				if ( 'percentage' == $quiz->passing_grade_type ) {
					$title .= "\n" . sprintf( __( "(Required %s%%)", 'learnpress-gradebook' ), $quiz->passing_grade );
				} elseif ( 'point' == $quiz->passing_grade_type ) {
					$title .= "\n" . sprintf( __( "(Required %s/%s point)", 'learnpress-gradebook' ), $quiz->passing_grade, $quiz->get_total_questions() );
				}
			}
			$row[] = $this->prepareCellContent( $title );
		}
		$row[] = __( 'Average', 'learnpress-gradebook' );
		$row[] = __( 'Enrolled', 'learnpress-gradebook' );
		$row[] = __( 'Status', 'learnpress-gradebook' );

		return $row;
	}

	public function prepareCellContent( $input = "" ) {
		preg_match( '/,/i', $input, $result );
		$add_quote = false;
		$output    = $input;
		if ( is_array( $result ) && ! empty( $result ) ) {
			$output    = str_replace( '"', "'", $input );
			$add_quote = true;
		}
		if ( ! $add_quote ) {
			preg_match( '/\n/i', $input, $result2 );
			$add_quote = is_array( $result2 ) && ! empty( $result2 );
		}
		if ( $add_quote ) {
			$output = '"' . $output . '"';
		}

		return $output;
	}

}


new LP_Gradebook_Export( array() );

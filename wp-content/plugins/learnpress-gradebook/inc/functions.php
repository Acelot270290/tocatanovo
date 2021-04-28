<?php
/**
 * @param string $template_name
 * @param array $args
 */
function learn_press_gradebook_get_template( $template_name, $args = array() ) {
	learn_press_get_template( $template_name, $args, learn_press_template_path() . '/addons/gradebook/', LP_ADDON_GRADEBOOK_PLUGIN_PATH . '/templates/' );
}

/**
 * @param $course_id
 *
 * @return mixed|string
 */
function learn_press_gradebook_export_url( $course_id ) {
	return add_query_arg(
		array(
			'export-gradebook' => $course_id,
			'export-nonce'     => wp_create_nonce( 'gradebook-export-course-' . $course_id )
		),
		get_site_url()
	);
}


function learn_press_gradebook_nonce_url( $args = array(), $field = 'gradebook-nonce' ) {
	$args = wp_parse_args( $args, array( 'course_id' => get_the_ID() ) );

	return wp_nonce_url( add_query_arg( $args, 'admin.php?page=course-gradebook' ), 'learn-press-gradebook-' . $args['course_id'], $field );
}

function learn_press_gradebook_verify_nonce( $course_id = 0, $nonce = 'gradebook-nonce' ) {
	if ( ! $course_id ) {
		$course_id = get_the_ID();
	}

	return ! empty( $_REQUEST[ $nonce ] ) ? wp_verify_nonce( $_REQUEST[ $nonce ], 'learn-press-gradebook-' . $course_id ) : false;
}

if ( ! function_exists( 'learn_press_gradebook_get_user_data' ) ) {
	/**
	 * Get data to show in gradebook tab in profile.
	 *
	 * @param null $user_id
	 * @param array $course_ids
	 *
	 * @return array
	 */
	function learn_press_gradebook_get_user_data( $user_id = null, $course_ids = array() ) {
		global $wpdb;
		if ( ! $user_id ) {
			$user_id = learn_press_get_current_user_id();
		}

		$user       = learn_press_get_user( $user_id );
		$course_ids = is_array( $course_ids ) ? $course_ids : array( $course_ids );
		if ( empty( $course_ids ) ) {
			$courses_enrolled = learn_press_gradebook_get_user_courses_enrolled( $user_id );
			$items            = array();
			if ( empty( $courses_enrolled ) ) {
				return $items;
			}

			$course_ids = array_keys( $courses_enrolled );
		}

		$query_course_ids = implode( ',', $course_ids );

		$types = apply_filters( 'learn-press/gradebook/query-item-types', array( 'lp_lesson', 'lp_quiz' ) );

		if ( ! $types || ! is_array( $types ) ) {
			return array();
		}

		$in_clause = join( ',', array_fill( 0, sizeof( $types ), '%s' ) );

		$sql = "SELECT p.ID, p.post_title, ui.user_id, ui.*, s.*, p.post_status, p.post_name, p.post_type, ui.item_id ";

		$sql .= "FROM {$wpdb->prefix}posts as p 
				LEFT JOIN {$wpdb->prefix}learnpress_section_items as si on p.ID = si.item_id
				LEFT JOIN {$wpdb->prefix}learnpress_sections as s on si.section_id = s.section_id
				LEFT JOIN {$wpdb->learnpress_user_items} as ui on p.ID = ui.item_id AND p.post_type = LOWER(CONVERT(ui.item_type USING latin1)) AND ui.user_id = {$user_id}
				WHERE
					p.post_status = 'publish'
					AND p.post_type IN ({$in_clause})
					AND s.section_course_id IN ({$query_course_ids})
				ORDER BY section_course_id, section_order, item_order, ui.user_item_id DESC";

		$query = $wpdb->prepare( $sql, $types );

		$rows  = $wpdb->get_results( $query );
		$items = array();
		if ( ! empty( $rows ) ) {
			for ( $i = 0, $n = sizeof( $rows ); $i < $n; $i ++ ) {
				$row       = $rows[ $i ];
				$course_id = $row->section_course_id;
				$course    = learn_press_get_course( $course_id );
				if ( ! $course ) {
					continue;
				}

				if ( isset( $items[ $course_id ]['items'][ $row->ID ] ) ) {
					continue;
				}
				if ( ! array_key_exists( $course_id, $items ) ) {
					$course              = $user->get_course_info( $course_id );
					$course_result       = get_post_meta( $course_id, '_lp_course_result', true );
					$course_start        = isset( $course['start'] ) ? $course['start'] : '';
					$course_status       = isset( $course['status'] ) ? $course['status'] : '';
					$items[ $course_id ] = array(
						'items'             => array(),//$user_lessons,
						'course_result'     => $course_result,
						'items_completed'   => 0,
						'items_started'     => 0,
						'items_number'      => 0,
						'items_pass'        => 0,
						'final_quiz_id'     => 0,
						'course_start_time' => $course_start,
						'status'            => $course_status,
						'course_completed'  => 0,
						'processed'         => 0
					);
					if ( $course_result == 'evaluate_final_quiz' ) {
						$quiz_id                              = learn_press_gradebook_get_final_quiz_id( $course_id );
						$items[ $course_id ]['final_quiz_id'] = $quiz_id;
						$quiz_res                             = $user->get_quiz_results( $quiz_id, $course_id, false );
						$items[ $course_id ]['processed']     = ( $quiz_res && $quiz_res['status'] == 'completed' ) ? 100 : 0;
					}
				}

				// - - - - - - - - - - - - - - - - - - - - - - - -
				if ( ! isset( $items[ $course_id ]['items'][ $row->ID ] ) ) {
					$items[ $course_id ]['items'][ $row->ID ] = $row;
				} else {
					continue;
				}

				if ( $row->post_type == 'lp_quiz' && $row->status == 'completed' ) {
					$quiz_id                                            = $row->ID;
					$quiz_res                                           = $user->get_quiz_results( $quiz_id, $course_id, false );
					$items[ $course_id ]['items'][ $row->ID ]->quiz_res = $quiz_res;
				}
				if ( $items[ $course_id ]['course_result'] == 'evaluate_lesson' && $row->post_type == 'lp_lesson' ) {

					if ( $row->status == 'completed' ) {
						$items[ $course_id ]['items_completed'] ++;
					}
					if ( $row->status == 'started' ) {
						$items[ $course_id ]['items_started'] ++;
					}
					$items[ $course_id ]['items_number'] ++;
				} elseif ( $items[ $course_id ]['course_result'] == 'evaluate_quizzes' && $row->post_type == 'lp_quiz' ) {
					if ( isset( $items[ $course_id ]['items'][ $row->ID ]->quiz_res )
					     && $items[ $course_id ]['items'][ $row->ID ]->quiz_res['grade'] == 'passed' ) {
						$items[ $course_id ]['items_completed'] ++;
					}
					$items[ $course_id ]['items_number'] ++;
				}

				if ( $i > 0 && ( $course_id != $rows[ $i - 1 ]->section_course_id || (int) $i == (int) ( $n - 1 ) ) ) {
					if ( $items[ $course_id ]['course_result'] == 'evaluate_quizzes' ) {

					}
					if ( in_array( $items[ $course_id ]['course_result'], array(
							'evaluate_quizzes',
							'evaluate_lesson'
						) ) && $items[ $rows[ $i - 1 ]->section_course_id ]['items_completed'] ) {
						$items[ $rows[ $i - 1 ]->section_course_id ]['processed'] = (int) $items[ $rows[ $i - 1 ]->section_course_id ]['items_completed'] / (int) $items[ $rows[ $i - 1 ]->section_course_id ]['items_number'] * 100;
					}
				}

			}

			return $items;
		}

		return array();
	}
}


function learn_press_gradebook_get_final_quiz_id( $course_id ) {
	global $wpdb;
	$sql   = "SELECT p.ID 
			FROM `{$wpdb->prefix}learnpress_sections` as `s`
				INNER join `{$wpdb->prefix}learnpress_section_items` as `si` 
					on `s`.`section_id`=`si`.`section_id` 
				RIGHT join `{$wpdb->prefix}posts` as `p`
					on si.item_id=p.ID and p.post_type='lp_quiz'
			WHERE `s`.`section_course_id`=%d 
			ORDER BY `s`.`section_order` DESC, `si`.`item_order` DESC LIMIT 1";
	$query = $wpdb->prepare( $sql, $course_id );
	$res   = $wpdb->get_var( $query );

	return $res;
}

function learn_press_gradebook_set_transient( $key, $value ) {
	$temp_dir   = get_temp_dir();
	$export_dir = untrailingslashit( $temp_dir ) . DIRECTORY_SEPARATOR . 'gradebook-export' . DIRECTORY_SEPARATOR;
	if ( ! ( file_exists( $export_dir ) && is_dir( $export_dir ) ) ) {
		wp_mkdir_p( $export_dir );
	}
	$content  = json_encode( $value, true );
	$file_tmp = $export_dir . $key;
	$return   = file_put_contents( $file_tmp, $content );

	return $return;
}

function learn_press_gradebook_get_transient( $key, $cache_time = - 1 ) {
	$temp_dir   = get_temp_dir();
	$export_dir = $temp_dir . DIRECTORY_SEPARATOR . 'gradebook-export' . DIRECTORY_SEPARATOR;
	if ( ! ( file_exists( $export_dir ) && is_dir( $export_dir ) ) ) {
		return false;
	}
	$file_tmp = $export_dir . $key;
	$content  = file_get_contents( $file_tmp );
	$value    = json_decode( $content );

	return $value;
}
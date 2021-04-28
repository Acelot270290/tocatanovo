<?php

class LP_Gradebook_Query {

	public static function query_users() {
		$items = array();
		for ( $i = 0; $i < 10; $i ++ ) {
			$items[] = array(
				'id'   => 1,
				'name' => rand( 0, 100 ),
			);
		}

		return new LP_Query_List_Table(
			array(
				'total' => rand( 10, 100 ),
				'items' => $items
			)
		);
	}

	public static function get_user_courses( $user_id ) {
		global $wpdb;
		$query = $wpdb->prepare( "
			SELECT ID
			FROM {$wpdb->posts}
			WHERE post_author = %d
		", $user_id );

		return $wpdb->get_col( $query );
	}

	/**
	 * Query all courses and return a list of courses with pagination.
	 *
	 * @param int    $user_id
	 * @param string $args
	 *
	 * @return LP_Query_List_Table
	 */
	public static function query_courses( $user_id = 0, $args = '' ) {

		global $wpdb, $wp;

		$paged = 1;

		if ( ! empty( $wp->query_vars['view_id'] ) ) {
			$paged = absint( $wp->query_vars['view_id'] );
		}

		$paged = max( $paged, 1 );
		$args  = wp_parse_args(
			$args, array(
				'paged' => $paged,
				'limit' => 10
			)
		);

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$cache_key = sprintf( 'courses-%d-%s', $user_id, md5( build_query( $args ) ) );

		// Check in cache
		if ( false === ( $courses = wp_cache_get( $cache_key, 'lp-gradebook-courses' ) ) ) {
			$courses = array(
				'total' => 0,
				'paged' => $args['paged'],
				'limit' => $args['limit'],
				'pages' => 0,
				'items' => array()
			);

			try {

				$course_ids   = $user_id ? self::get_user_courses( $user_id ) : '';
				$query_args   = $course_ids;
				$query_args[] = $user_id;
				$limit        = $args['limit'];
				$offset       = ( $args['paged'] - 1 ) * $limit;

				$select = "SELECT ui.* ";
				$from   = "FROM {$wpdb->learnpress_user_items} ui";
				$join   = $wpdb->prepare( "INNER JOIN {$wpdb->posts} c ON c.ID = ui.item_id AND c.post_type = %s", LP_COURSE_CPT );
				//$where   = $wpdb->prepare( "WHERE 1 AND user_id = %d AND ref_id IN(" . join( ',', $order_format ) . ")", array_merge( array( $user_id ), $valid_orders ) );
				$where   = $wpdb->prepare( "WHERE 1 AND c.post_author = %d", $user_id );
				$having  = "HAVING 1";
				$orderby = "ORDER BY item_id, user_item_id DESC";

				if ( ! empty( $args['status'] ) ) {
					switch ( $args['status'] ) {
						case 'finished':
						case 'passed':
						case 'failed':

							$where .= $wpdb->prepare( " AND ui.status IN( %s )", array(
								'finished'
							) );

							if ( $args['status'] !== 'finished' ) {
								$select .= ", uim.meta_value AS grade";
								$join   .= $wpdb->prepare( "
									LEFT JOIN {$wpdb->learnpress_user_itemmeta} uim ON uim.learnpress_user_item_id = ui.user_item_id AND uim.meta_key = %s
								", 'grade' );

								if ( 'passed' === $args['status'] ) {
									$having .= $wpdb->prepare( " AND grade = %s", 'passed' );
								} else {
									$having .= $wpdb->prepare( " AND ( grade IS NULL OR grade = %s )", 'failed' );
								}
							}

							break;
						case 'not-enrolled':
							$where .= $wpdb->prepare( " AND ui.status NOT IN( %s, %s, %s )", array(
								'enrolled',
								'finished',
								'pending'
							) );
					}
				}

				$where .= $wpdb->prepare( " AND ui.status NOT IN(%s)", 'pending' );

				$query_parts = apply_filters(
					'learn-press/query/user-purchased-courses',
					compact( 'select', 'from', 'join', 'where', 'having', 'orderby' ),
					$user_id,
					$args
				);
				list( $select, $from, $join, $where, $having, $orderby ) = array_values( $query_parts );

				$sql = "
					SELECT SQL_CALC_FOUND_ROWS *
					FROM
					(	
						{$select}
						{$from}
						{$join}
						{$where}
						{$having}
						{$orderby}
					) X GROUP BY item_id
					LIMIT {$offset}, {$limit}
				";

				$items = $wpdb->get_results( $sql, ARRAY_A );

				if ( $items ) {
					$count      = $wpdb->get_var( "SELECT FOUND_ROWS()" );
					$course_ids = wp_list_pluck( $items, 'item_id' );
					LP_Helper::cache_posts( $course_ids );

					$courses['total'] = $count;
					$courses['pages'] = ceil( $count / $args['limit'] );
					foreach ( $items as $item ) {
						$courses['items'][] = new LP_User_Item_Course( $item );
					}
				}
			}
			catch ( Exception $ex ) {

			}

			wp_cache_set( $cache_key, $courses, 'lp-user-courses' );
		}

		$courses['single'] = __( 'course', 'learnpress-gradebook' );
		$courses['plural'] = __( 'courses', 'learnpress-gradebook' );

		return new LP_Query_List_Table( $courses );

	}
}
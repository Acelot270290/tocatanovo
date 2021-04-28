<?php

/**
 * Class LP_Gradebook_Course
 */
class LP_Gradebook_Course extends LP_Course {

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
	public function __construct( $course_id ) {
		parent::__construct( $course_id );
	}

	public function load() {

		parent::load();

		global $wpdb;

		$select  = "SELECT *";
		$from    = "FROM {$wpdb->learnpress_user_items} ui";
		$join    = "INNER JOIN {$wpdb->posts} p ON p.ID = ui.item_id";
		$where   = "WHERE 1";
		$groupby = "";
		$having  = "";

		$query_parts = apply_filters( 'learn-press/gradeboook/query-course', array_values( compact( 'select', 'from', 'join', 'where', 'groupby', 'having' ) ) );

		list( $select, $from, $join, $where, $groupby, $having ) = $query_parts;

		$query = "
			{$select}
			{$from}
			{$join}
			{$where}
			{$groupby}
			{$having}
		";


	}

	/**
	 * @param string $args
	 *
	 * @return array|bool|mixed
	 */
	public function get_users( $args = '' ) {
		global $wpdb, $wp;

		$args = $this->_get_users_query_args( $args );

		$cache = md5( serialize( $args ) );

		if ( ( false === ( $users = wp_cache_get( $cache, 'gradebook-course-users' ) ) ) ) {
			global $wpdb;

			$statuses        = array( 'finished', 'enrolled' );
			$format_statuses = array_fill( 0, sizeof( $statuses ), '%s' );

			$select = "SELECT SQL_CALC_FOUND_ROWS DISTINCT user_id";
			$from   = "FROM {$wpdb->learnpress_user_items} ui";
			$join   = "INNER JOIN {$wpdb->users} u ON u.ID = ui.user_id";
			$where  = $wpdb->prepare( "
				WHERE item_id = %d
				AND `status` IN(" . join( ',', $format_statuses ) . ")	
			", array_merge( array( $this->get_id() ), $statuses ) );

			if ( ! empty( $args['search'] ) ) {
				$where .= $wpdb->prepare( "
					AND ( u.user_email LIKE %s OR u.display_name LIKE %s )
				", '%' . $wpdb->esc_like( $args['search'] ) . '%', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
			}

			$groupby = "";
			$having  = "";
			$orderby = "ORDER BY user_item_id DESC";
			$limit   = "LIMIT " . $args['offset'] . ', ' . $args['limit'];

			$query_parts = apply_filters( 'learn-press/gradeboook/query-users', array_values( compact( 'select', 'from', 'join', 'where', 'groupby', 'having', 'orderby', 'limit' ) ) );

			list( $select, $from, $join, $where, $groupby, $having, $orderby, $limit ) = $query_parts;

			$query = "
				{$select}
				{$from}
				{$join}
				{$where}
				{$groupby}
				{$having}
				{$orderby}
				{$limit}
			";

			$users = $wpdb->get_col( $query );
			$total = $wpdb->get_var( "SELECT FOUND_ROWS()" );
			$users = array(
				'total' => $total,
				'paged' => $args['paged'],
				'limit' => $args['limit'],
				'pages' => ceil( $total / $args['limit'] ),
				'items' => $users
			);

			wp_cache_set( $cache, $users, 'gradebook-course-users' );
		}

		$users['nav_base']   = remove_query_arg( 'start', learn_press_get_current_url() );
		$users['nav_format'] = '?start=%#%';

		return new LP_Query_List_Table( $users );
	}

	/**
	 * @param array $args
	 *
	 * @return array|string
	 */
	protected function _get_users_query_args( $args = array() ) {
		settype( $args, 'array' );
		$defaults = array(
			'paged'  => 1,
			'limit'  => 10,
			'search' => ''
		);

		if ( $limit = LP_Request::get_int( 'limit' ) ) {
			$defaults['limit'] = $limit;
		} else {
			$defaults['limit'] = $this->_limit;
		}

		if ( ( $start = LP_Request::get_int( 'start' ) ) && empty( $args['paged'] ) ) {
			$defaults['paged'] = $start;
		}

		if ( $search = LP_Request::get_string( 'search' ) ) {
			$defaults['search'] = $search;
		}

		$args = wp_parse_args( $args, $defaults );
		if ( $args['limit'] < 0 ) {
			$args['limit'] = 9999999;
		}
		$args['paged']  = max( $args['paged'], 1 );
		$args['offset'] = ( $args['paged'] - 1 ) * $args['limit'];

		return $args;
	}

	public function set_limit( $limit ) {
		$this->_limit = intval( $limit );
	}
}
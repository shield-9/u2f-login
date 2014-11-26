<?php
class U2F_List_Table extends WP_List_Table {
	private $data = array();

	function __construct( $data ) {
		global $status, $page;
		$this->data = $data;

		parent::__construct( array(
			'singular'	=> 'key',
			'plural'	=> 'keys',
			'ajax'		=> false
		) );
	}

	function display_tablenav( $which ) {
		if( 'bottom' != $which ) {
			parent::display_tablenav( $which );
		}
	}

	function print_column_headers( $with_id = true ) {
		if( $with_id ) {
			parent::print_column_headers( $with_id );
		}
	}

	function extra_tablenav( $which ) {
		switch( $which ) {
			case 'top':
				// esc_html_e('Extra Table Navigation(Top)', 'u2f');
				break;
			case 'bottom':
				// esc_html_e('Extra Table Navigation(Bottom)', 'u2f');
				break;
		}
	}

	function get_columns() {
		$columns = array(
			'cb'		=> '<input type="checkbox" />',
			'name'		=> _x('Name', 'Security Key Name', 'u2f'),
			'added'		=> __('Date Added', 'u2f'),
			'last_used'	=> __('Last Time Used', 'u2f'),
		);
		return $columns;
	}

	function get_sortable_columns() {
		$sortable_columns = array(
			'name'		=> array('name', false),
			'added'		=> array('added', false),
			'last_used'	=> array('last_used', false),
		);
		return $sortable_columns;
	}

	function column_cb( $item ){
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->_args['singular'],
			$item['keyHandle']
		);
	}

	function column_default($item, $column_name){
		switch($column_name) {
			case 'added':
			case 'last_used':
				return isset( $item[ $column_name ] ) ? date_i18n( __('M j, Y @ G:i'), $item[ $column_name ] ) : __('Not Set', 'u2f');
			default:
				return $item[ $column_name ];
		}
	}

	function get_bulk_actions() {
		$actions = array(
			'delete' => __('Delete', 'u2f'),
		);
		return $actions;
	}

	function process_bulk_action() {
		if( !isset( $_GET['key'] ) or !is_array( $_GET['key'] ) )
			return false;
		switch( $this->current_action() ) {
			case 'delete':
				foreach( $_GET['key'] as $key ) {
					U2F::delete_security_key( get_current_user_id(), $key );
					foreach( $this->data as $index => $data ) {
						if( $key == $data['keyHandle'] ) {
							unset( $this->data[ $index ] );
						}
					}
				}
				break;
		}
	}

	function prepare_items() {
		$per_page = 20;

		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		function usort_reorder( $a,$b ){
			$orderby = ( !empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'added';	// If no sort, default to title
			$order = ( !empty( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'asc';		// If no order, default to asc
			$result = strnatcmp( $a[ $orderby ], $b[ $orderby ] );				// Determine sort order
			return ( $order==='asc') ? $result : -$result;					// Send final sort direction to usort
		}

		usort( $this->data, 'usort_reorder');

		$current_page = $this->get_pagenum();

		$total_items = count( $this->data );

		$this->data = array_slice( $this->data, ( ( $current_page - 1) * $per_page ), $per_page );

		$this->items = $this->data;

		$this->set_pagination_args( array(
			'total_items'	=> $total_items,
			'per_page'	=> $per_page,
			'total_pages'	=> ceil( $total_items / $per_page )
		) );
	}
}

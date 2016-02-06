<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

function rest_api_token_auth_page_register() {
	add_management_page( 'REST API Token Auth', 'REST API Token Auth', 'manage_options', 'rest_api_token_auth_page', 'rest_api_token_auth_page' );

}
add_action( 'admin_menu', 'rest_api_token_auth_page_register', 10 );

/**
 * Display the API Keys
 *
 * @since       1.0.0
 * @return      void
 */
function rest_api_token_auth_page() {

	if( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h2><?php _e( 'REST API Token Authentication Table', 'wp-api-token-auth' ); ?> </h2>
		<?php

		do_action( 'rest_api_token_auth_tools_api_keys_before' );
		$api_keys_table = new Token_Auth_Keys_Table();
		$api_keys_table->prepare_items();
		$api_keys_table->display(); ?>
		<p>
		<?php __( 'These API keys allow you to use the WordPress REST API to retrieve store data in JSON for external applications or devices.', 'wp-api-token-auth' ); ?>
		</p>
		<?php
		do_action( 'rest_api_token_auth_tools_api_keys_after' );
		?>
	</div>
	<?php
}

/**
 * Token_Auth_Keys_Table Class
 *
 * Renders the API Keys table
 *
 * @since 1.0.0
 */
class Token_Auth_Keys_Table extends WP_List_Table {

	/**
	 * @var int Number of items per page
	 * @since 1.0.0
	 */
	public $per_page = 30;

	/**
	 * @var object Query results
	 * @since 1.0.0
	 */
	private $keys;

	/**
	 * Get things started
	 *
	 * @since 1.0.0
	 * @see WP_List_Table::__construct()
	 */
	public function __construct() {
		global $status, $page;

		// Set parent defaults
		parent::__construct( array(
			'singular'  => __( 'API Key', 'wp-api-token-auth' ),     // Singular name of the listed records
			'plural'    => __( 'API Keys', 'wp-api-token-auth' ),    // Plural name of the listed records
			'ajax'      => false                       // Does this table support ajax?
		) );

		$this->query();
	}

	/**
	 * This function renders most of the columns in the list table.
	 *
	 * @access public
	 * @since 1.0.0
	 *
	 * @param array $item Contains all the data of the keys
	 * @param string $column_name The name of the column
	 *
	 * @return string Column Name
	 */
	public function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}

	/**
	 * Displays the public key rows
	 *
	 * @access public
	 * @since 1.0.0
	 *
	 * @param array $item Contains all the data of the keys
	 * @param string $column_name The name of the column
	 *
	 * @return string Column Name
	 */
	public function column_key( $item ) {
		return '<input readonly="readonly" type="text" class="large-text" value="' . esc_attr( $item[ 'key' ] ) . '"/>';
	}

	/**
	 * Displays the token rows
	 *
	 * @access public
	 * @since 1.0.0
	 *
	 * @param array $item Contains all the data of the keys
	 * @param string $column_name The name of the column
	 *
	 * @return string Column Name
	 */
	public function column_token( $item ) {
		return '<input readonly="readonly" type="text" class="large-text" value="' . esc_attr( $item[ 'token' ] ) . '"/>';
	}

	/**
	 * Displays the secret key rows
	 *
	 * @access public
	 * @since 1.0.0
	 *
	 * @param array $item Contains all the data of the keys
	 * @param string $column_name The name of the column
	 *
	 * @return string Column Name
	 */
	public function column_secret( $item ) {
		return '<input readonly="readonly" type="text" class="large-text" value="' . esc_attr( $item[ 'secret' ] ) . '"/>';
	}

	/**
	 * Renders the column for the user field
	 *
	 * @access public
	 * @since 1.0.0
	 * @return void
	 */
	public function column_user( $item ) {

		$actions = array();

		$actions['reissue'] = sprintf(
			'<a href="%s" class="rest-api-token-auth-regenerate-api-key">%s</a>',
			esc_url( wp_nonce_url( add_query_arg( array( 'user_id' => $item['id'], 'rest_api_token_auth_action' => 'process_api_key', 'rest_api_token_auth_process' => 'regenerate' ) ), 'wp-api-token-auth-nonce' ) ),
			__( 'Reissue', 'wp-api-token-auth' )
		);
		$actions['revoke'] = sprintf(
			'<a href="%s" class="rest-api-token-auth-revoke-api-key rest-api-token-auth-delete">%s</a>',
			esc_url( wp_nonce_url( add_query_arg( array( 'user_id' => $item['id'], 'rest_api_token_auth_action' => 'process_api_key', 'rest_api_token_auth_process' => 'revoke' ) ), 'wp-api-token-auth-nonce' ) ),
			__( 'Revoke', 'wp-api-token-auth' )
		);

		$actions = apply_filters( 'rest_api_token_auth_row_actions', array_filter( $actions ) );

		return sprintf('%1$s %2$s', $item['user'], $this->row_actions( $actions ) );
	}

	/**
	 * Retrieve the table columns
	 *
	 * @access public
	 * @since 1.0.0
	 * @return array $columns Array of all the list table columns
	 */
	public function get_columns() {
		$columns = array(
			'user'         => __( 'Username', 'wp-api-token-auth' ),
			'key'          => __( 'Public Key', 'wp-api-token-auth' ),
			'token'        => __( 'Token', 'wp-api-token-auth' ),
			'secret'       => __( 'Secret Key', 'wp-api-token-auth' )
		);

		return $columns;
	}

	/**
	 * Retrieve the current page number
	 *
	 * @access public
	 * @since 1.0.0
	 * @return int Current page number
	 */
	public function get_paged() {
		return isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	}

	/**
	 * Performs the key query
	 *
	 * @access public
	 * @since 1.0.0
	 * @return void
	 */
	public function query() {
		$users    = get_users( array(
			'meta_value' => 'rest_api_token_auth_secret_key',
			'number'     => $this->per_page,
			'offset'     => $this->per_page * ( $this->get_paged() - 1 )
		) );
		$keys     = array();

		foreach( $users as $user ) {
			$keys[$user->ID]['id']     = $user->ID;
			$keys[$user->ID]['email']  = $user->user_email;
			$keys[$user->ID]['user']   = '<a href="' . add_query_arg( 'user_id', $user->ID, 'user-edit.php' ) . '"><strong>' . $user->user_login . '</strong></a>';
			$keys[$user->ID]['key']    = $this->get_user_public_key( $user->ID );
			$keys[$user->ID]['secret'] = $this->get_user_secret_key( $user->ID );
			$keys[$user->ID]['token']  = $this->get_token( $user->ID );
		}

		return $keys;
	}



	/**
	 * Retrieve count of total users with keys
	 *
	 * @access public
	 * @since 1.0.0
	 * @return int
	 */
	public function total_items() {
		global $wpdb;

		if( ! get_transient( 'rest-api-token-auth-total-api-keys' ) ) {
			$total_items = $wpdb->get_var( "SELECT count(user_id) FROM $wpdb->usermeta WHERE meta_value='rest_api_token_auth_secret_key'" );

			set_transient( 'rest-api-token-auth-total-api-keys', $total_items, 60 * 60 );
		}

		return get_transient( 'rest-api-token-auth-total-api-keys' );
	}

    // todo: docbloc
    /**
     * 
     *
     * @param [type] $user_id [description]
     *
     * @return [type] [description]
     */
	public function get_user_public_key( $user_id = 0 ) {
		global $wpdb;

		if ( empty( $user_id ) ) {
			return '';
		}

		$cache_key       = md5( 'rest_api_token_auth_cache_user_public_key' . $user_id );
		$user_public_key = get_transient( $cache_key );

		if ( empty( $user_public_key ) ) {
			$user_public_key = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->usermeta WHERE meta_key = 'rest_api_token_auth_public_key' AND user_id = %d", $user_id ) );
			set_transient( $cache_key, $user_public_key, HOUR_IN_SECONDS );
		}

		return $user_public_key;
	}

    // todo: docbloc
    /**
     * 
     *
     * @param [type] $user_id [description]
     *
     * @return [type] [description]
     */
	public function get_user_secret_key( $user_id = 0 ) {
		global $wpdb;

		if ( empty( $user_id ) ) {
			return '';
		}

		$cache_key       = md5( 'rest_api_token_auth_cache_user_secret_key' . $user_id );
		$user_secret_key = get_transient( $cache_key );

		if ( empty( $user_secret_key ) ) {
			$user_secret_key = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->usermeta WHERE meta_key = 'rest_api_token_auth_secret_key' AND user_id = %d", $user_id ) );
			set_transient( $cache_key, $user_secret_key, HOUR_IN_SECONDS );
		}

		return $user_secret_key;
	}


	/**
	 * Retrieve the user's token
	 *
	 * @access private
	 * @author Chris Christoff
	 * @since  1.0.0
	 * @param  int $user_id
	 * @return string
	 */
	public function get_token( $user_id = 0 ) {
		return hash( 'md5', $this->get_user_secret_key( $user_id ) . $this->get_user_public_key( $user_id ) );
	}	

	/**
	 * Setup the final data for the table
	 *
	 * @access public
	 * @since 1.0.0
	 * @return void
	 */
	public function prepare_items() {
		$columns = $this->get_columns();

		$hidden = array(); // No hidden columns
		$sortable = array(); // Not sortable... for now

		$this->_column_headers = array( $columns, $hidden, $sortable, 'id' );

		$data = $this->query();

		$total_items = $this->total_items();

		$this->items = $data;

		$this->set_pagination_args( array(
				'total_items' => $total_items,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $total_items / $this->per_page )
			)
		);
	}
}
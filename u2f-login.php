<?php
/*
 * Plugin Name: U2F Login
 * Plugin URI: http://www.extendwings.com
 * Description: Make WordPress login secure with U2F (Universal Second Factor) protocol
 * Version: 0.1.0-dev
 * Author: Daisuke Takahashi(Extend Wings)
 * Author URI: http://www.extendwings.com
 * License: AGPLv3 or later
 * Text Domain: u2f
 * Domain Path: /languages/
*/

if( ! function_exists('add_action') ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

if( version_compare( PHP_VERSION, '5.5', '<') ) {
	require_once( ABSPATH . 'wp-admin/includes/plugin.php');
	deactivate_plugins( __FILE__ );
}

if( version_compare( get_bloginfo('version'), '4.0', '<') ) {
	require_once( ABSPATH . 'wp-admin/includes/plugin.php');
	deactivate_plugins( __FILE__ );
}

add_action('init', array('U2F', 'init') );

class U2F {
	static $instance;

	private $u2f;

	const VERSION = '0.1.0-dev';

	static function init() {
		if( ! self::$instance ) {
			if( did_action('plugins_loaded') )
				self::plugin_textdomain();
			else
				add_action('plugins_loaded', array(__CLASS__, 'plugin_textdomain') );

			self::$instance = new U2F;
		}
		return self::$instance;
	}

	private function __construct() {
		require_once( plugin_dir_path( __FILE__ ) . 'lib/php-u2flib-server/src/u2flib_server/U2F.php');
		$this->u2f = new u2flib_server\U2F( set_url_scheme('//' . $_SERVER['HTTP_HOST'] ) );

		add_action('login_enqueue_scripts', array( &$this, 'login_enqueue_assets') );
		add_action('login_head', array( &$this, 'admin_print_scripts') );
		add_action('login_form', array( &$this, 'login_form') );
		add_action( 'wp_ajax_nopriv_u2f_login', array( &$this, 'verify_credentials') );
		add_filter('authenticate', array( &$this, 'authenticate'), 25, 3);

		add_action('admin_menu', array( &$this, 'users_menu') );
		add_action('admin_print_scripts-users_page_security-key', array( &$this, 'admin_print_scripts') );
		add_action('admin_enqueue_scripts', array( &$this, 'admin_enqueue_assets') );
		if( is_admin() ) {
			add_action( 'wp_ajax_u2f_register', array( &$this, 'register') );
		}

		add_filter('plugin_row_meta', array( &$this, 'plugin_row_meta'), 10, 2);
	}

	public function login_enqueue_assets() {
		$min = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? '' : '.min';

		wp_enqueue_script('u2f-login', plugin_dir_url( __FILE__ ) . "login{$min}.js", array('jquery'), self::VERSION, true);
		wp_enqueue_style('u2f-login', plugin_dir_url( __FILE__ ) . "login{$min}.css", array(), self::VERSION);

		$data = array(
			'ajax_url' => admin_url( 'admin-ajax.php'),
			
		);

		if( isset( $_GET['u2f_avail'] ) && 'false' == $_GET['u2f_avail'] ) {
			$data['u2f_avail'] = 'false';
		}

		$strings = array(
			'Wait'            => __('Wait...', 'u2f'),
			'Login'           => __('Log In'),
			'U2FGuide'        => __('Now insert (and tap) your Security Key', 'u2f'),
			'LostKeyGuide'    => __('Did you lost or damaged your Security Key?', 'u2f'),
			'EmailTokenGuide' => __('Check your inbox and enter the token', 'u2f'),
		);

		wp_localize_script('u2f-login', 'u2f_data', $data );
		wp_localize_script('u2f-login', 'u2f_l10n', $strings );
	}

	public function login_form() {
		echo '<input type="hidden" name="u2f_response" id="u2f_response" />' . PHP_EOL;
		echo '<input type="hidden" name="method" id="method" />' . PHP_EOL;
	}

	public function verify_credentials() {
		header('Content-Type: application/json');

		if( !empty( $_POST['data']['log'] ) )
			$credentials['user_login'] = $_POST['data']['log'];
		if( !empty( $_POST['data']['pwd'] ) )
			$credentials['user_password'] = $_POST['data']['pwd'];

		$user = wp_authenticate( $credentials['user_login'], $credentials['user_password'] );

		if( is_wp_error( $user ) ) {
			$errors = $user;

			echo $this->export_error_data( $errors, 'json');
			die();
		}

		$type = 'mailtoken';

		if( $_POST['data']['u2f'] != 'false') {
			$keys = self::get_security_keys( $user->ID );

			if( $keys ) {
				$type = 'u2f';
			}
		}

		switch( $type ){
			case 'u2f':
				try {
					$data = $this->u2f->getAuthenticateData( $keys );
					$response = array(
						'success' => true,
						'method'  => 'u2f',
						'data'    => $data,
					);
					set_transient('u2f_login_request_' . $user->ID, $data, 30 * MINUTE_IN_SECONDS );
				} catch( Exception $e ) {
					$errors = new WP_Error('internal_server_error', __('<strong>ERROR</strong>: An error occured in <strong>U2F Login</strong> plugin.', 'u2f') );
					$response = $this->export_error_data( $errors, 'array');
				} finally {
					echo json_encode( $response );
					die();
				}
			default:
				$token = trim( strtr( base64_encode( openssl_random_pseudo_bytes(4) ), '+/', '-_'), '=');
				$hash = password_hash( $token, PASSWORD_DEFAULT);
				$mail_status = wp_mail(
					sprintf('%s <%s>', $user->display_name, $user->user_email ),
					__('[WordPress] Your authentication code', 'u2f'),
					sprintf( __('Your authentication code is: %s'), $token ),
					sprintf('From: WordPress <%s>', get_bloginfo('admin_email') )
				);

				if( $mail_status ) {
					$response = array(
						'success' => true,
						'method'  => 'mailtoken',
					);
					set_transient('u2f_login_token_hash_' . $user->ID, $hash, 30 * MINUTE_IN_SECONDS );
				} else {
					$errors = new WP_Error('internal_server_error', __('<strong>ERROR</strong>: An error occured in <strong>U2F Login</strong> plugin.', 'u2f') );
					$response = $this->export_error_data( $errors, 'array');
				}

				echo json_encode( $response );
				die();
		}
	}

	private function export_error_data( $errors, $type ) {
		$errors = apply_filters('wp_login_errors', $errors );

		$msgs = array(
			'message' => array(),
			'error'   => array(),
		);

		if( $errors->get_error_code() ) {

			foreach( $errors->get_error_codes() as $code ) {
				$severity = $errors->get_error_data( $code );
				foreach( $errors->get_error_messages( $code ) as $error_message ) {
					if('message' == $severity )
						$msgs['message'][] = $error_message;
					else
						$msgs['error'][] = $error_message;
				}
			}
		}

		switch( $type ) {
			case 'array':
				return $msgs;
			case 'json':
				return json_encode( $msgs );
			default:
				return false;
		}
	}

	public function authenticate( $user, $username, $password ) {
		if( !is_a( $user, 'WP_User') ) {
			return $user;
		}

		if( doing_action('wp_ajax_nopriv_u2f_login') ) {
			return $user;
		}

		switch( $_POST['method'] ) {
			case 'u2f':
				$requests = get_transient('u2f_login_request_' . $user->ID );
			//	delete_transient('u2f_login_request_' . $user->ID );

				$response = json_decode( stripslashes( $_POST['u2f_response'] ) );

				$keys = self::get_security_keys( $user->ID );

				try {
					$reg = $this->u2f->doAuthenticate( $requests, $keys, $response );
					self::update_security_key( $user->ID, $reg );

					return $user;
				} catch( Exception $e ) {
					return new WP_Error('invalid_security_key', __('<strong>ERROR</strong>: Invalid Security Key.', 'u2f') );
				}
			case 'mailtoken':
				$hash = get_transient('u2f_login_token_hash_' . $user->ID );

				if( password_verify( $_POST['token'], $hash ) ) {
					return $user;
				} else {
					return new WP_Error('invalid_token', __('<strong>ERROR</strong>: Invalid Token.', 'u2f') );
				}
			default:
				return new WP_Error('invalid_auth', __('<strong>ERROR</strong>: Invalid Authentication Attempts.', 'u2f') );
		}
	}

	public function users_menu() {
		add_users_page(__('Security Key', 'u2f'), __('Your Security Key', 'u2f'), 'read', 'security-key', array( &$this, 'render_users_menu') );
	}

	public function render_users_menu() {
		if( ! class_exists('WP_List_Table') ) {
			require_once( ABSPATH.'wp-admin/includes/class-wp-list-table.php');
		}

		$data = get_user_meta( get_current_user_id(), 'u2f_registered_key');

		require_once( plugin_dir_path( __FILE__ ) . 'class.list-table.php');
		$list_table = new U2F_List_Table( $data );
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h2><?php _e('Security Key', 'u2f'); ?></h2>
			<h3><?php _e('Security Keys associated with your account', 'u2f'); ?></h3>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
				<?php $list_table->display() ?>
			</form>

			<h3><?php _e('Add another Security Key', 'u2f'); ?></h3>
			<div class="button button-primary button-large" id="u2f-register">
				<?php _e('Register', 'u2f'); ?>
			</div>
		</div><!-- wrap -->
		<?php
	}

	public function admin_print_scripts() {
		echo '<script src="chrome-extension://pfboblefjcgdjicmnffhdgionmgcdmne/u2f-api.js"></script>' . PHP_EOL;
	}

	public function admin_enqueue_assets( $hook ) {
		$min = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? '' : '.min';

		if('users_page_security-key' == $hook ) {
			wp_enqueue_script('u2f-admin', plugin_dir_url( __FILE__ ) . "admin{$min}.js", array('jquery'), self::VERSION, true);
			wp_enqueue_style('u2f-admin', plugin_dir_url( __FILE__ ) . "admin{$min}.css", array(), self::VERSION);

			$keys = self::get_security_keys( get_current_user_id() );

			try {
				$data = $this->u2f->getRegisterData( $keys );
				list($req,$sigs) = $data;

				set_transient('u2f_register_request', $req, HOUR_IN_SECONDS );
				$data = array(
					'request' => json_encode( $req ),
					'sigs'    => json_encode( $sigs ),
					'ajax_url' => admin_url( 'admin-ajax.php')
				);
				wp_localize_script('u2f-admin', 'u2f_data', $data );
			} catch( Exception $e ) {
				// wp_die()?
			}

		}
	}
	
	public function register() {
		header('Content-Type: application/json');

		try {
			$reg = $this->u2f->doRegister( get_transient('u2f_register_request'), (object) $_POST['data'] );

			self::add_security_key( get_current_user_id(), $reg );

			$response = array(
				'success' =>true,
			);
		} catch( Exception $e ) {
			$response = array(
				'errorCode' => $e->getCode(),
				'errorText' => $e->getMessage(),
			);
		} finally {
			delete_transient('u2f_register_request');

			echo json_encode( $response );
			die();
		}
	}

	static function add_security_key( $user_id, $register ) {
		if( !is_numeric( $user_id ) ) {
			throw new \InvalidArgumentException('$user_id of add_security_key() method only accepts int.');
		}

		if(
			!is_object( $register )
			|| !property_exists( $register, 'keyHandle') || empty( $register->keyHandle )
			|| !property_exists( $register, 'publicKey') || empty( $register->publicKey )
			|| !property_exists( $register, 'certificate') || empty( $register->certificate )
			|| !property_exists( $register, 'counter') || ( 0 != $register->counter )
		) {
			throw new \InvalidArgumentException('$register of add_security_key() method only accepts Registration.');
		}

		$register = array(
			'keyHandle'   => $register->keyHandle,
			'publicKey'   => $register->publicKey,
			'certificate' => $register->certificate,
			'counter'     => $register->counter,
		);

		$register['name']      = 'New Security Key';
		$register['added']     = current_time('timestamp');
		$register['last_used'] = $register['added'];

		add_user_meta( $user_id, 'u2f_registered_key', $register );
	}

	static function get_security_keys( $user_id ) {
		if( !is_numeric( $user_id ) ) {
			throw new \InvalidArgumentException('$user_id of get_security_keys() method only accepts int.');
		}

		$keys = get_user_meta( $user_id, 'u2f_registered_key');
		if( $keys ) {
			foreach( $keys as $index => $key ) {
				$keys[ $index ] = (object) $key;
			}
		}

		return $keys;
	}

	static function update_security_key( $user_id, $data ) {
		if( !is_numeric( $user_id ) ) {
			throw new \InvalidArgumentException('$user_id of update_security_key() method only accepts int.');
		}

		if(
			!is_object( $data )
			|| !property_exists( $data, 'keyHandle') || empty( $data->keyHandle )
			|| !property_exists( $data, 'publicKey') || empty( $data->publicKey )
			|| !property_exists( $data, 'certificate') || empty( $data->certificate )
			|| !property_exists( $data, 'counter') || ( 0 == $data->counter )
		) {
			throw new \InvalidArgumentException('$data of update_security_key() method only accepts Registration.');
		}

		$keys = get_user_meta( $user_id, 'u2f_registered_key');
		if( $keys ) {
			foreach( $keys as $index => $key ) {
				if( $key->keyHandle === $data->keyHandle ) {
					update_user_meta( $user->ID, (array)$data, $key );
					break;
				}
			}
		}

		return $keys;
	}

	static function delete_security_key( $user_id, $keyHandle ) {
		global $wpdb;

		if( !is_numeric( $user_id ) || !$keyHandle ) {
			return false;
		}

		$user_id = absint( $user_id );
		if( !$user_id ) {
			return false;
		}

		$table = $wpdb->usermeta;

		$keyHandle = wp_unslash( $keyHandle );
		$keyHandle = maybe_serialize( $keyHandle );

		$query = $wpdb->prepare("SELECT umeta_id FROM $table WHERE meta_key = 'u2f_registered_key' AND user_id = %d", $user_id );

		if( $keyHandle )
			$query .= $wpdb->prepare(" AND meta_value LIKE %s", '%:"' . $keyHandle . '";s:%');

		$meta_ids = $wpdb->get_col( $query );
		if( !count( $meta_ids ) )
			return false;

		$query = "DELETE FROM $table WHERE umeta_id IN( " . implode( ',', $meta_ids ) . " )";

		$count = $wpdb->query($query);

		if( !$count )
			return false;

		wp_cache_delete( $user_id, 'user_meta');

		return true;
	}

	static function plugin_textdomain() {
		load_plugin_textdomain('u2f', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');
	}

	function plugin_row_meta( $links, $file ) {
		if( plugin_basename( __FILE__ ) === $file ) {
			$links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url('http://www.extendwings.com/donate/'),
				__('Donate', 'u2f')
			);
		}
		return $links;
	}
}

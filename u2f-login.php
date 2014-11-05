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
		add_filter('plugin_row_meta', array(&$this, 'plugin_row_meta'), 10, 2);
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

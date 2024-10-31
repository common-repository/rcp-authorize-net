<?php
/**
 * Plugin Name: Restrict Content Pro - Authorize.net
 * Description: Adds the Authorize.net payment gateway for Restrict Content Pro.
 * Version: 1.0.4
 * Author: iThemes, LLC
 * Author URI: https://ithemes.com
 * Contributors: jthillithemes, layotte, ithemes
 * Text Domain: rcp-authorize-net
 * iThemes Package: rcp-authorize-net
 */

namespace RCP\Anet;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define constants.
 */
define( 'RCP_ANET_VERSION', '1.0.4' );
define( 'RCP_ANET_PATH', plugin_dir_path( __FILE__ ) );
define( 'RCP_ANET_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check for requirements and load the plugin.
 *
 * @since 1.0
 * @return void
 */
function load() {

	// PHP 5.6+ is required.
	if ( version_compare( PHP_VERSION, '5.6', '<' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\unsupported_php_version_notice' );

		return;
	}

	// RCP 3.0.5+ is required.
	if ( ! defined( 'RCP_PLUGIN_VERSION' ) || ! version_compare( RCP_PLUGIN_VERSION, '3.0.5', '>=' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\unsupported_rcp_version_notice' );

		return;
	}

	// Load the textdomain.
	load_plugin_textdomain( 'rcp-authorize-net', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	// Include required files.
	require_once RCP_ANET_PATH . 'includes/actions.php';
	require_once RCP_ANET_PATH . 'includes/class-rcp-anet-payment-gateway.php';
	require_once RCP_ANET_PATH . 'includes/filters.php';
	require_once RCP_ANET_PATH . 'includes/functions.php';
	require_once RCP_ANET_PATH . 'includes/admin/settings.php';

}

add_action( 'plugins_loaded', __NAMESPACE__ . '\load' );

/**
 * Displays an admin notice if using an unsupported PHP version.
 *
 * @since 1.0
 * @return void
 */
function unsupported_php_version_notice() {
	echo '<div class="error"><p>' . __( 'The Restrict Content Pro Authorize.net add-on requires PHP version 5.6 or later. Please contact your web host and request your site be updated to a modern PHP version, preferably 7.0 and later.', 'rcp-authorize-net' ) . '</p></div>';
}

/**
 * Displays an admin notice if using an unsupported version of RCP.
 *
 * @since 1.0
 * @return void
 */
function unsupported_rcp_version_notice() {
	echo '<div class="error"><p>' . __( 'The Restrict Content Pro Authorize.net add-on requires Restrict Content Pro version 3.0.5 or later.', 'rcp-authorize-net' ) . '</p></div>';
}

if ( ! function_exists( 'ithemes_rcp_authorize_net_updater_register' ) ) {
	function ithemes_rcp_authorize_net_updater_register( $updater ) {
		$updater->register( 'REPO', __FILE__ );
	}
	add_action( 'ithemes_updater_register', 'ithemes_rcp_authorize_net_updater_register' );

	require( __DIR__ . '/lib/updater/load.php' );
}
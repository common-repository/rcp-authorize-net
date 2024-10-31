<?php
/**
 * Settings
 *
 * @package   rcp-authorize-net
 * @copyright Copyright (c) 2019, Restrict Content Pro team
 * @license   GPL2+
 * @since     1.0
 */

namespace RCP\Anet;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Authorize.net gateway settings to "Payments" tab.
 *
 * @param array $rcp_options Save settings.
 *
 * @since 1.0
 * @return void
 */
function settings( $rcp_options ) {

	?>
	<table class="form-table">
		<tr valign="top">
			<th colspan=2>
				<h3><?php _e( 'Authorize.net Settings', 'rcp-authorize-net' ); ?></h3>
			</th>
		</tr>
		<?php // Authorize.net Test Login ID ?>
		<tr>
			<th>
				<label for="rcp_settings[authorize_test_api_login]"><?php _e( 'Test API Login ID', 'rcp-authorize-net' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" id="rcp_settings[authorize_test_api_login]" style="width: 300px;" name="rcp_settings[authorize_test_api_login]" value="<?php echo ! empty( $rcp_options['authorize_test_api_login'] ) ? esc_attr( $rcp_options['authorize_test_api_login'] ) : ''; ?>"/>
				<p class="description"><?php _e( 'Enter your authorize.net test API login ID.', 'rcp-authorize-net' ); ?></p>
			</td>
		</tr>
		<?php // Authorize.net Test Transaction Key ?>
		<tr>
			<th>
				<label for="rcp_settings[authorize_test_txn_key]"><?php _e( 'Test Transaction Key', 'rcp-authorize-net' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" id="rcp_settings[authorize_test_txn_key]" style="width: 300px;" name="rcp_settings[authorize_test_txn_key]" value="<?php echo ! empty( $rcp_options['authorize_test_txn_key'] ) ? esc_attr( $rcp_options['authorize_test_txn_key'] ) : ''; ?>"/>
				<p class="description"><?php _e( 'Enter your authorize.net test transaction key', 'rcp-authorize-net' ); ?></p>
			</td>
		</tr>
		<?php // Authorize.net Test Signature Key ?>
		<tr>
			<th>
				<label for="rcp_settings[authorize_test_signature_key]"><?php _e( 'Test Signature Key', 'rcp-authorize-net' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" id="rcp_settings[authorize_test_signature_key]" style="width: 300px;" name="rcp_settings[authorize_test_signature_key]" value="<?php echo ! empty( $rcp_options['authorize_test_signature_key'] ) ? esc_attr( $rcp_options['authorize_test_signature_key'] ) : ''; ?>"/>
				<p class="description"><?php _e( 'Enter your authorize.net test signature key', 'rcp-authorize-net' ); ?></p>
			</td>
		</tr>
		<?php // Authorize.net Live Login ID ?>
		<tr>
			<th>
				<label for="rcp_settings[authorize_api_login]"><?php _e( 'Live API Login ID', 'rcp-authorize-net' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" id="rcp_settings[authorize_api_login]" style="width: 300px;" name="rcp_settings[authorize_api_login]" value="<?php echo ! empty( $rcp_options['authorize_api_login'] ) ? esc_attr( $rcp_options['authorize_api_login'] ) : ''; ?>"/>
				<p class="description"><?php _e( 'Enter your authorize.net live API login ID.', 'rcp-authorize-net' ); ?></p>
			</td>
		</tr>
		<?php // Authorize.net Live Transaction Key ?>
		<tr>
			<th>
				<label for="rcp_settings[authorize_txn_key]"><?php _e( 'Live Transaction Key', 'rcp-authorize-net' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" id="rcp_settings[authorize_txn_key]" style="width: 300px;" name="rcp_settings[authorize_txn_key]" value="<?php echo ! empty( $rcp_options['authorize_txn_key'] ) ? esc_attr( $rcp_options['authorize_txn_key'] ) : ''; ?>"/>
				<p class="description"><?php _e( 'Enter your authorize.net live transaction key', 'rcp-authorize-net' ); ?></p>
			</td>
		</tr>
		<?php // Authorize.net Live Signature Key ?>
		<tr>
			<th>
				<label for="rcp_settings[authorize_signature_key]"><?php _e( 'Live Signature Key', 'rcp-authorize-net' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" id="rcp_settings[authorize_signature_key]" style="width: 300px;" name="rcp_settings[authorize_signature_key]" value="<?php echo ! empty( $rcp_options['authorize_signature_key'] ) ? esc_attr( $rcp_options['authorize_signature_key'] ) : ''; ?>"/>
				<p class="description"><?php _e( 'Enter your authorize.net live signature key', 'rcp-authorize-net' ); ?></p>
			</td>
		</tr>
	</table>
	<?php

}

add_action( 'rcp_payments_settings', __NAMESPACE__ . '\settings' );
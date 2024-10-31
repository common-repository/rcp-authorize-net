<?php
/**
 * Functions
 *
 * @package   rcp-authorize-net
 * @copyright Copyright (c) 2019, Restrict Content Pro team
 * @license   GPL2+
 * @since     1.0
 */

namespace RCP\Anet;

use RCP_Membership;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Determines if a membership is an Authorize.net subscription.
 *
 * @param int|RCP_Membership $membership_object_or_id Membership ID or object.
 *
 * @since 1.0
 * @return bool
 */
function is_authnet_membership( $membership_object_or_id ) {

	if ( ! is_object( $membership_object_or_id ) ) {
		$membership = rcp_get_membership( $membership_object_or_id );
	} else {
		$membership = $membership_object_or_id;
	}

	$is_authnet = false;

	if ( ! empty( $membership ) && $membership->get_id() > 0 ) {
		$subscription_id = $membership->get_gateway_subscription_id();

		if ( false !== strpos( $subscription_id, 'anet_' ) ) {
			$is_authnet = true;
		}
	}

	/**
	 * Filters whether or not the membership is an Authorize.net subscription.
	 *
	 * @param bool           $is_authnet
	 * @param RCP_Membership $membership
	 *
	 * @since 1.0
	 */
	return (bool) apply_filters( 'rcp_is_authorizenet_membership', $is_authnet, $membership );

}

/**
 * Determine if all necessary API credentials are filled in
 *
 * @since  1.0
 * @return bool
 */
function has_api_access() {

	global $rcp_options;

	$ret = false;

	if ( rcp_is_sandbox() ) {
		$api_login_id    = $rcp_options['authorize_test_api_login'];
		$transaction_key = $rcp_options['authorize_test_txn_key'];
	} else {
		$api_login_id    = $rcp_options['authorize_api_login'];
		$transaction_key = $rcp_options['authorize_txn_key'];
	}

	if ( ! empty( $api_login_id ) && ! empty( $transaction_key ) ) {
		$ret = true;
	}

	return $ret;

}

/**
 * Cancel an Authorize.net subscription based on the subscription ID.
 *
 * @param string $payment_profile_id Subscription ID.
 *
 * @since 1.0
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function cancel_membership( $payment_profile_id ) {

	global $rcp_options;

	$ret = true;

	if ( rcp_is_sandbox() ) {
		$api_login_id    = isset( $rcp_options['authorize_test_api_login'] ) ? sanitize_text_field( $rcp_options['authorize_test_api_login'] ) : '';
		$transaction_key = isset( $rcp_options['authorize_test_txn_key'] ) ? sanitize_text_field( $rcp_options['authorize_test_txn_key'] ) : '';
	} else {
		$api_login_id    = isset( $rcp_options['authorize_api_login'] ) ? sanitize_text_field( $rcp_options['authorize_api_login'] ) : '';
		$transaction_key = isset( $rcp_options['authorize_txn_key'] ) ? sanitize_text_field( $rcp_options['authorize_txn_key'] ) : '';
	}

	require_once RCP_ANET_PATH . 'vendor/autoload.php';

	$profile_id = str_replace( 'anet_', '', $payment_profile_id );

	/**
	 * Create a merchantAuthenticationType object with authentication details.
	 */
	$merchant_authentication = new \net\authorize\api\contract\v1\MerchantAuthenticationType();
	$merchant_authentication->setName( $api_login_id );
	$merchant_authentication->setTransactionKey( $transaction_key );

	/**
	 * Set the transaction's refId
	 */
	$refId = 'ref' . time();

	$request = new \net\authorize\api\contract\v1\ARBCancelSubscriptionRequest();
	$request->setMerchantAuthentication( $merchant_authentication );
	$request->setRefId( $refId );
	$request->setSubscriptionId( $profile_id );

	/**
	 * Submit the request
	 */
	$controller  = new \net\authorize\api\controller\ARBCancelSubscriptionController( $request );
	$environment = rcp_is_sandbox() ? \net\authorize\api\constants\ANetEnvironment::SANDBOX : \net\authorize\api\constants\ANetEnvironment::PRODUCTION;
	$response    = $controller->executeWithApiResponse( $environment );

	/**
	 * An error occurred - get the error message.
	 */
	if ( $response == null || $response->getMessages()->getResultCode() != "Ok" ) {

		$error_messages = $response->getMessages()->getMessage();
		$error          = $error_messages[0]->getCode() . "  " . $error_messages[0]->getText();
		$ret            = new WP_Error( 'rcp_authnet_error', $error );

	}

	return $ret;

}

/**
 * Get all headers from a request
 *
 * @since 1.0
 * @return array|false
 */
function get_headers() {

	$headers = array();

	if ( function_exists( 'apache_request_headers' ) ) {
		$headers = apache_request_headers();
	} else {
		foreach ( $_SERVER as $name => $value ) {
			if ( substr( strtoupper( $name ), 0, 5 ) == 'HTTP_' ) {
				$headers[ str_replace( ' ', '-', ucwords( strtoupper( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
			}
		}
	}

	return $headers;

}
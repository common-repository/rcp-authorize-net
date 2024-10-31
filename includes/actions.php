<?php
/**
 * Actions
 *
 * @package   rcp-authorize-net
 * @copyright Copyright (c) 2019, Restrict Content Pro team
 * @license   GPL2+
 * @since     1.0
 */

namespace RCP\Anet;

use RCP_Membership;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Update the billing card for a given membership.
 *
 * @param RCP_Membership $membership
 *
 * @since 1.0
 * @return void
 */
function update_membership_billing_card( $membership ) {

	global $rcp_options;

	if ( ! is_a( $membership, 'RCP_Membership' ) ) {
		return;
	}

	if ( ! is_authnet_membership( $membership ) ) {
		return;
	}

	require_once RCP_ANET_PATH . 'vendor/autoload.php';

	if ( rcp_is_sandbox() ) {
		$api_login_id    = isset( $rcp_options['authorize_test_api_login'] ) ? sanitize_text_field( $rcp_options['authorize_test_api_login'] ) : '';
		$transaction_key = isset( $rcp_options['authorize_test_txn_key'] ) ? sanitize_text_field( $rcp_options['authorize_test_txn_key'] ) : '';
	} else {
		$api_login_id    = isset( $rcp_options['authorize_api_login'] ) ? sanitize_text_field( $rcp_options['authorize_api_login'] ) : '';
		$transaction_key = isset( $rcp_options['authorize_txn_key'] ) ? sanitize_text_field( $rcp_options['authorize_txn_key'] ) : '';
	}

	$error          = '';
	$card_name      = isset( $_POST['rcp_card_name'] ) ? sanitize_text_field( $_POST['rcp_card_name'] ) : '';
	$fname = $lname = '';
	$card_number    = isset( $_POST['rcp_card_number'] ) && is_numeric( $_POST['rcp_card_number'] ) ? sanitize_text_field( $_POST['rcp_card_number'] ) : '';
	$card_exp_month = isset( $_POST['rcp_card_exp_month'] ) && is_numeric( $_POST['rcp_card_exp_month'] ) ? sanitize_text_field( $_POST['rcp_card_exp_month'] ) : '';
	$card_exp_year  = isset( $_POST['rcp_card_exp_year'] ) && is_numeric( $_POST['rcp_card_exp_year'] ) ? sanitize_text_field( $_POST['rcp_card_exp_year'] ) : '';
	$card_cvc       = isset( $_POST['rcp_card_cvc'] ) && is_numeric( $_POST['rcp_card_cvc'] ) ? sanitize_text_field( $_POST['rcp_card_cvc'] ) : '';
	$card_zip       = isset( $_POST['rcp_card_zip'] ) ? sanitize_text_field( $_POST['rcp_card_zip'] ) : '';

	if ( ! empty( $card_name ) ) {
		$names = explode( ' ', $card_name );
		$fname = isset( $names[0] ) ? $names[0] : '';

		if ( ! empty( $names[1] ) ) {
			unset( $names[0] );
			$lname = implode( ' ', $names );
		}
	}

	if ( empty( $card_number ) || empty( $card_exp_month ) || empty( $card_exp_year ) || empty( $card_cvc ) || empty( $card_zip ) ) {
		$error = __( 'Please enter all required fields.', 'rcp-authorize-net' );
	}

	if ( empty( $error ) ) {

		$profile_id = str_replace( 'anet_', '', $membership->get_gateway_subscription_id() );

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

		$subscription = new \net\authorize\api\contract\v1\ARBSubscriptionType();

		/**
		 * Update card details.
		 */
		$credit_card = new \net\authorize\api\contract\v1\CreditCardType();
		$credit_card->setCardNumber( $card_number );
		$credit_card->setExpirationDate( $card_exp_year . '-' . $card_exp_month );
		$credit_card->setCardCode( $card_cvc );

		$payment = new \net\authorize\api\contract\v1\PaymentType();
		$payment->setCreditCard( $credit_card );

		$subscription->setPayment( $payment );

		/**
		 * Update the billing name & zip.
		 */
		$bill_to = new \net\authorize\api\contract\v1\NameAndAddressType();
		$bill_to->setZip( $card_zip );
		if ( ! empty( $fname ) ) {
			$bill_to->setFirstName( $fname );
		}
		if ( ! empty( $lname ) ) {
			$bill_to->setLastName( $lname );
		}
		$subscription->setBillTo( $bill_to );

		/**
		 * Make request to update details.
		 */
		$request = new \net\authorize\api\contract\v1\ARBUpdateSubscriptionRequest();
		$request->setMerchantAuthentication( $merchant_authentication );
		$request->setRefId( $refId );
		$request->setSubscriptionId( $profile_id );
		$request->setSubscription( $subscription );

		$controller  = new \net\authorize\api\controller\ARBCancelSubscriptionController( $request );
		$environment = rcp_is_sandbox() ? \net\authorize\api\constants\ANetEnvironment::SANDBOX : \net\authorize\api\constants\ANetEnvironment::PRODUCTION;
		$response    = $controller->executeWithApiResponse( $environment );

		/**
		 * An error occurred - get the error message.
		 */
		if ( $response == null || $response->getMessages()->getResultCode() != "Ok" ) {
			$error_messages = $response->getMessages()->getMessage();
			$error          = $error_messages[0]->getCode() . "  " . $error_messages[0]->getText();
		}

	}

	if ( ! empty( $error ) ) {
		wp_redirect( add_query_arg( array( 'card' => 'not-updated', 'msg' => urlencode( $error ) ) ) );
		exit;
	}

	wp_redirect( add_query_arg( array( 'card' => 'updated', 'msg' => '' ) ) );
	exit;

}

add_action( 'rcp_update_membership_billing_card', __NAMESPACE__ . '\update_membership_billing_card' );

/**
 * Process Authorize.net webhook.
 *
 * @param \WP_Query $query
 *
 * @since 1.0
 * @return void
 */
function process_webhook( $query ) {
	if ( 'rcp-authorizenet-listener' != $query->request ) {
		return;
	}

	$gateway = new Payment_Gateway();
	$gateway->process_webhooks();
}

add_action( 'parse_request', __NAMESPACE__ . '\process_webhook' );
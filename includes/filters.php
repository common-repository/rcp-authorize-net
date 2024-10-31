<?php
/**
 * Filters
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
 * Register Authorize.net payment gateway.
 *
 * @param array $gateways
 *
 * @since 1.0
 * @return array
 */
function register_gateway( $gateways ) {

	$gateways['authorizenet'] = array(
		'label'       => __( 'Credit / Debit Card', 'rcp-authorize-net' ),
		'admin_label' => __( 'Authorize.net', 'rcp-authorize-net' ),
		'class'       => 'RCP\Anet\Payment_Gateway'
	);

	return $gateways;

}

add_filter( 'rcp_payment_gateways', __NAMESPACE__ . '\register_gateway' );

/**
 * Determines whether or not a membership can be cancelled.
 *
 * @param bool           $can_cancel    Whether or not the membership can be cancelled.
 * @param int            $membership_id ID of the membership.
 * @param RCP_Membership $membership    Membership object.
 *
 * @since 1.0
 * @return bool
 */
function can_cancel_membership( $can_cancel, $membership_id, $membership ) {

	if ( 'authorizenet' != $membership->get_gateway() ) {
		return $can_cancel;
	}

	if ( ! has_api_access() ) {
		return false;
	}

	$subscription_id = $membership->get_gateway_subscription_id();

	if ( false === strpos( $subscription_id, 'anet_' ) ) {
		return false;
	}

	return true;

}

add_filter( 'rcp_membership_can_cancel', __NAMESPACE__ . '\can_cancel_membership', 10, 3 );

/**
 * Cancel Authorize.net payment profiles.
 *
 * @param true|WP_Error  $success                 Whether or not the payment profile was cancelled.
 * @param string         $gateway                 Payment gateway slug.
 * @param string         $gateway_subscription_id Gateway subscription ID.
 * @param int            $membership_id           ID of the membership.
 * @param RCP_Membership $membership              Membership object.
 *
 * @since 1.0
 * @return true|WP_Error
 */
function cancel_payment_profile( $success, $gateway, $gateway_subscription_id, $membership_id, $membership ) {

	if ( 'authorizenet' != $gateway ) {
		return $success;
	}

	$cancelled = cancel_membership( $gateway_subscription_id );

	if ( is_wp_error( $cancelled ) ) {

		rcp_log( sprintf( 'Failed to cancel Authorize.net payment profile for membership #%d. Error code: %s; Error Message: %s.', $membership->get_id(), $cancelled->get_error_code(), $cancelled->get_error_message() ) );

		$success = $cancelled;

	} else {
		$success = true;
	}

	return $success;

}

add_filter( 'rcp_membership_payment_profile_cancelled', __NAMESPACE__ . '\cancel_payment_profile', 10, 5 );

/**
 * Filters whether or not the membership's saved billing card can be updated.
 *
 * @param bool           $can_update    Whether or not the card can be updated.
 * @param int            $membership_id ID of the membership being checked.
 * @param RCP_Membership $membership    Membership object.
 *
 * @since 1.0
 * @return bool
 */
function can_update_billing_card( $can_update, $membership_id, $membership ) {

	if ( 'authorizenet' != $membership->get_gateway() ) {
		return $can_update;
	}

	if ( ! has_api_access() ) {
		return false;
	}

	$subscription_id = $membership->get_gateway_subscription_id();

	if ( false === strpos( $subscription_id, 'anet_' ) ) {
		return false;
	}

	return true;

}

add_filter( 'rcp_membership_can_update_billing_card', __NAMESPACE__ . '\can_update_billing_card', 10, 3 );

/**
 * Builds the link to the Authorize.net subscription details page.
 *
 * @param string $url             URL to the subscription in the gateway.
 * @param string $gateway         Gateway slug.
 * @param string $subscription_id ID of the subscription in the gateway.
 *
 * @since 1.0
 * @return string
 */
function subscription_id_url( $url, $gateway, $subscription_id ) {

	if ( 'authorizenet' != $gateway ) {
		return $url;
	}

	$sandbox = rcp_is_sandbox();

	$anet_id  = str_replace( 'anet_', '', $subscription_id );
	$base_url = $sandbox ? 'https://sandbox.authorize.net/' : 'https://account.authorize.net/';
	$path     = $sandbox ? 'sandbox' : 'anet';
	$url      = $base_url . 'ui/themes/' . urlencode( $path ) . '/ARB/SubscriptionDetail.aspx?SubscrID=' . urlencode( $anet_id );

	return $url;

}

add_filter( 'rcp_gateway_subscription_id_url', __NAMESPACE__ . '\subscription_id_url', 10, 3 );
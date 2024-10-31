<?php
/**
 * Authorize.net Payment Gateway
 *
 * @package   rcp-authorize-net
 * @copyright Copyright (c) 2019, Restrict Content Pro team
 * @license   GPL2+
 * @since     1.0
 */

namespace RCP\Anet;

use DateTime;
use Exception;
use \net\authorize\api\contract\v1 as AnetAPI;
use \net\authorize\api\controller as AnetController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Payment_Gateway extends \RCP_Payment_Gateway {

	/**
	 * @since  1.0
	 * @var string
	 * @access private
	 */
	private $api_login_id;

	/**
	 * @since  1.0
	 * @var string
	 * @access private
	 */
	private $transaction_key;

	/**
	 * @since  1.0
	 * @var string
	 * @access private
	 */
	private $transaction_signature;

	/**
	 * Get things going
	 *
	 * @access public
	 * @since  1.0
	 * @return void
	 */
	public function init() {
		global $rcp_options;

		$this->supports[] = 'one-time';
		$this->supports[] = 'recurring';
		$this->supports[] = 'fees';
		$this->supports[] = 'trial';

		if ( $this->test_mode ) {
			$this->api_login_id          = isset( $rcp_options['authorize_test_api_login'] ) ? sanitize_text_field( $rcp_options['authorize_test_api_login'] ) : '';
			$this->transaction_key       = isset( $rcp_options['authorize_test_txn_key'] ) ? sanitize_text_field( $rcp_options['authorize_test_txn_key'] ) : '';
			$this->transaction_signature = isset( $rcp_options['authorize_test_signature_key'] ) ? sanitize_text_field( $rcp_options['authorize_test_signature_key'] ) : '';
		} else {
			$this->api_login_id          = isset( $rcp_options['authorize_api_login'] ) ? sanitize_text_field( $rcp_options['authorize_api_login'] ) : '';
			$this->transaction_key       = isset( $rcp_options['authorize_txn_key'] ) ? sanitize_text_field( $rcp_options['authorize_txn_key'] ) : '';
			$this->transaction_signature = isset( $rcp_options['authorize_signature_key'] ) ? sanitize_text_field( $rcp_options['authorize_signature_key'] ) : '';
		}

		require_once RCP_ANET_PATH . 'vendor/autoload.php';

	}

	/**
	 * Validate additional fields during registration submission
	 *
	 * @access public
	 * @since  1.0
	 * @return void
	 */
	public function validate_fields() {

		if ( empty( $_POST['rcp_card_cvc'] ) ) {
			rcp_errors()->add( 'missing_card_code', __( 'The security code you have entered is invalid', 'rcp-authorize-net' ), 'register' );
		}

		if ( empty( $_POST['rcp_card_zip'] ) ) {
			rcp_errors()->add( 'missing_card_zip', __( 'Please enter a Zip / Postal Code code', 'rcp-authorize-net' ), 'register' );
		}

		if ( empty( $_POST['rcp_card_name'] ) ) {
			rcp_errors()->add( 'missing_card_name', __( 'Please enter the name on card', 'rcp-authorize-net' ), 'register' );
		}

		if ( empty( $this->api_login_id ) || empty( $this->transaction_key ) ) {
			rcp_errors()->add( 'missing_authorize_settings', __( 'Authorize.net API Login ID or Transaction key is missing.', 'rcp-authorize-net' ), 'register' );
		}

		$sub_id = ! empty( $_POST['rcp_level'] ) ? absint( $_POST['rcp_level'] ) : false;

		if ( $sub_id ) {

			$sub = rcp_get_subscription_length( $sub_id );

			if ( rcp_registration_is_recurring() && 'day' == $sub->duration_unit && $sub->duration < 7 ) {
				rcp_errors()->add( 'invalid_authorize_length', __( 'Authorize.net does not permit subscriptions with renewal periods less than 7 days.', 'rcp-authorize-net' ), 'register' );
			}

			if ( rcp_registration_is_recurring() && 'year' == $sub->duration_unit && $sub->duration > 1 ) {
				rcp_errors()->add( 'invalid_authorize_length_years', __( 'Authorize.net does not permit subscriptions with renewal periods greater than 1 year.', 'rcp-authorize-net' ), 'register' );
			}

		}

	}

	/**
	 * Process registration
	 *
	 * @access public
	 * @since  1.0
	 * @return void
	 */
	public function process_signup() {

		/**
		 * @var \RCP_Payments $rcp_payments_db
		 */
		global $rcp_payments_db;

		if ( empty( $this->api_login_id ) || empty( $this->transaction_key ) ) {
			rcp_errors()->add( 'missing_authorize_settings', __( 'Authorize.net API Login ID or Transaction key is missing.', 'rcp-authorize-net' ) );
		}

		$member = new \RCP_Member( $this->user_id );

		$length = $this->length;
		$unit   = $this->length_unit . 's';

		if ( 'years' == $unit && 1 == $length ) {
			$unit   = 'months';
			$length = 12;
		}

		$names = explode( ' ', sanitize_text_field( $_POST['rcp_card_name'] ) );
		$fname = isset( $names[0] ) ? $names[0] : $member->first_name;

		if ( ! empty( $names[1] ) ) {
			unset( $names[0] );
			$lname = implode( ' ', $names );
		} else {
			$lname = $member->last_name;
		}

		/**
		 * Disable auto renew if the card is going to expire before the first payment.
		 */
		if ( $this->auto_renew && ! empty( $this->subscription_start_date ) && ! $this->is_trial() ) {
			$card_expiration = strtotime( sprintf( '%d-%d-%d', $_POST['rcp_card_exp_year'], $_POST['rcp_card_exp_month'], 1 ) );
			$first_payment   = strtotime( $this->subscription_start_date );

			if ( $first_payment > $card_expiration ) {
				$this->auto_renew = false;

				$this->membership->update( array(
					'auto_renew' => 0
				) );

				$this->membership->add_note( __( 'Auto renew disabled, as the supplied card expires before the first payment is scheduled to be taken.', 'rcp' ) );

				if ( empty( $this->initial_amount ) ) {
					// If the first payment was $0 anyway, complete $0 payment, activate account, and bail.
					$rcp_payments_db->update( $this->payment->id, array(
						'payment_type' => 'Credit Card',
						'status'       => 'complete'
					) );

					wp_redirect( $this->return_url );
					exit;
				}
			}
		}

		try {

			/**
			 * Create a merchantAuthenticationType object with authentication details.
			 */
			$merchant_authentication = new AnetAPI\MerchantAuthenticationType();
			$merchant_authentication->setName( $this->api_login_id );
			$merchant_authentication->setTransactionKey( $this->transaction_key );

			/**
			 * Set the transaction's refId
			 */
			$refId = 'ref' . time();

			/**
			 * Add credit card details and create payment object.
			 */
			$credit_card = new AnetAPI\CreditCardType();
			$credit_card->setCardNumber( sanitize_text_field( $_POST['rcp_card_number'] ) );
			$credit_card->setExpirationDate( sanitize_text_field( $_POST['rcp_card_exp_year'] ) . '-' . sanitize_text_field( $_POST['rcp_card_exp_month'] ) );
			$credit_card->setCardCode( sanitize_text_field( $_POST['rcp_card_cvc'] ) );

			$payment = new AnetAPI\PaymentType();
			$payment->setCreditCard( $credit_card );

			$environment = rcp_is_sandbox() ? \net\authorize\api\constants\ANetEnvironment::SANDBOX : \net\authorize\api\constants\ANetEnvironment::PRODUCTION;

			/**
			 * Create a recurring subscription.
			 */
			if ( $this->auto_renew ) {

				if ( $this->initial_amount > 0 || $this->is_trial() ) {
					/**
					 * First authorize the initial amount because Authorize.net doesn't actually
					 * take payment until several hours later. If this fails then we won't be
					 * creating the subscription.
					 *
					 * Authorizations are only done for payments greater than $0 and free trials.
					 * This is not done if the payment due today is $0 due to credits / fees / discounts.
					 */
					rcp_log( sprintf( 'Authorizing initial payment amount with Authorize.net for user #%d.', $this->user_id ) );

					// Do a $1 authorization for free trials.
					$auth_amount = $this->is_trial() ? 1 : $this->initial_amount;

					$auth_transaction = new AnetAPI\TransactionRequestType();
					$auth_transaction->setTransactionType( 'authOnlyTransaction' );
					$auth_transaction->setAmount( $auth_amount );
					$auth_transaction->setPayment( $payment );

					$auth_request = new AnetAPI\CreateTransactionRequest();
					$auth_request->setMerchantAuthentication( $merchant_authentication );
					$auth_request->setRefId( $refId );
					$auth_request->setTransactionRequest( $auth_transaction );

					$auth_controller = new AnetController\CreateTransactionController( $auth_request );
					$auth_response   = $auth_controller->executeWithApiResponse( $environment );

					// Invalid or no response from Authorize.net.
					if ( empty( $auth_response ) ) {
						$error_messages = $auth_response->getMessages()->getMessage();
						$error          = '<p>' . __( 'There was a problem processing your payment.', 'rcp' ) . '</p>';
						$error          .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $error_messages[0]->getCode() ) . '</p>';
						$error          .= '<p>' . sprintf( __( 'Error message: %s', 'rcp' ), $error_messages[0]->getText() ) . '</p>';

						rcp_log( sprintf( 'Authorize.net card authorization failed for user #%d. Invalid response from Authorize.net. Error code: %s. Error message: %s.', $this->user_id, $error_messages[0]->getCode(), $error_messages[0]->getText() ) );

						$this->handle_processing_error( new Exception( $error ) );
					}

					$auth_transaction_response = $auth_response->getTransactionResponse();

					// Successful API request, but authorization was not successful.
					if ( empty( $auth_transaction_response ) || $auth_transaction_response->getResponseCode() != '1' ) {
						$errors = $auth_transaction_response->getErrors();
						$error  = '<p>' . __( 'There was a problem processing your payment.', 'rcp' ) . '</p>';
						$error  .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $errors[0]->getErrorCode() ) . '</p>';
						$error  .= '<p>' . sprintf( __( 'Error message: %s', 'rcp' ), $errors[0]->getErrorText() ) . '</p>';

						rcp_log( sprintf( 'Authorize.net card authorization failed for user #%d. Card was declined. Error code: %s. Error message: %s.', $this->user_id, $errors[0]->getErrorCode(), $errors[0]->getErrorText() ) );

						$this->handle_processing_error( new Exception( $error ) );
					}

					/**
					 * Authorization was successful!
					 *
					 * Save the authorization transaction ID so we can void it later.
					 *
					 * Now we can create the actual subscription.
					 */
					rcp_update_membership_meta( $this->membership->get_id(), 'authorizenet_authonly_trans_id', sanitize_text_field( $auth_transaction_response->getTransId() ) );
					$this->membership->add_note( sprintf( __( 'Authorized %s in Authorize.net via transaction ID %s.', 'rcp' ), rcp_currency_filter( $auth_amount ), sanitize_text_field( $auth_transaction_response->getTransId() ) ) );

				}

				/**
				 * Configure the subscription information.
				 */
				$subscription = new AnetAPI\ARBSubscriptionType();
				$subscription->setName( substr( $this->subscription_name . ' - ' . $this->subscription_key, 0, 50 ) ); // Max of 50 characters

				/**
				 * Configure billing interval.
				 */
				$interval = new AnetAPI\PaymentScheduleType\IntervalAType();
				$interval->setLength( $length );
				$interval->setUnit( $unit );

				/**
				 * Configure billing schedule.
				 */
				$payment_schedule = new AnetAPI\PaymentScheduleType();
				$payment_schedule->setInterval( $interval );
				$payment_schedule->setStartDate( new DateTime( date( 'Y-m-d' ) ) );
				$payment_schedule->setTotalOccurrences( 9999 );

				// Delay start date for free trials.
				if ( ! empty( $this->subscription_start_date ) ) {
					$payment_schedule->setStartDate( new DateTime( date( 'Y-m-d', strtotime( $this->subscription_start_date, current_time( 'timestamp' ) ) ) ) );
				} elseif ( $this->initial_amount != $this->amount ) {
					$payment_schedule->setTrialOccurrences( 1 );
					$subscription->setTrialAmount( $this->initial_amount );
				}

				$subscription->setPaymentSchedule( $payment_schedule );
				$subscription->setAmount( $this->amount );

				/**
				 * Add credit card details to subscription.
				 */
				$subscription->setPayment( $payment );

				/**
				 * Configure order details.
				 */
				$order = new AnetAPI\OrderType();
				$order->setDescription( $this->subscription_key );
				$subscription->setOrder( $order );

				/**
				 * Add customer information.
				 */
				$bill_to = new AnetAPI\NameAndAddressType();
				$bill_to->setFirstName( $fname );
				$bill_to->setLastName( $lname );
				$bill_to->setZip( sanitize_text_field( $_POST['rcp_card_zip'] ) );
				$subscription->setBillTo( $bill_to );

				/**
				 * Make API request.
				 */
				$request = new AnetAPI\ARBCreateSubscriptionRequest();
				$request->setMerchantAuthentication( $merchant_authentication );
				$request->setRefId( $refId );
				$request->setSubscription( $subscription );
				$controller = new AnetController\ARBCreateSubscriptionController( $request );

				$response = $controller->executeWithApiResponse( $environment );

				if ( $response != null && $response->getMessages()->getResultCode() == "Ok" ) {

					$this->membership->set_recurring( $this->auto_renew );
					$this->membership->set_gateway_subscription_id( 'anet_' . $response->getSubscriptionId() );
					$this->membership->add_note( __( 'Subscription started in Authorize.net', 'rcp' ) );

					if ( empty( $this->initial_amount ) || $this->is_trial() ) {
						// Complete $0 payment and activate account.
						$rcp_payments_db->update( $this->payment->id, array(
							'payment_type' => 'Credit Card',
							'status'       => 'complete'
						) );
					} else {
						/*
						 * Manually activate because webhook has a big delay and we want to activate the membership ASAP.
						 * Note: Payment hasn't actually been collected yet, but it takes ages to do so we're doing it now
						 * anyway. If payment ends up failing we'll pick that up in the webhook and disable the account.
						 */
						$this->membership->activate();
					}

					do_action( 'rcp_authorizenet_signup', $this->user_id, $this, $response );

				} else {

					$error_messages = $response->getMessages()->getMessage();
					$error          = '<p>' . __( 'There was a problem processing your payment.', 'rcp' ) . '</p>';
					$error          .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $error_messages[0]->getCode() ) . '</p>';
					$error          .= '<p>' . sprintf( __( 'Error message: %s', 'rcp' ), $error_messages[0]->getText() ) . '</p>';

					$this->handle_processing_error( new Exception( $error ) );

				}

			} else {

				/**
				 * Process one-time transaction.
				 */

				/**
				 * Create new order.
				 */
				$order = new AnetAPI\OrderType();
				$order->setInvoiceNumber( $this->payment->id );
				$order->setDescription( $this->payment->subscription );

				/**
				 * Set up billing information.
				 */
				$bill_to = new AnetAPI\CustomerAddressType();
				$bill_to->setFirstName( $fname );
				$bill_to->setLastName( $lname );
				$bill_to->setZip( sanitize_text_field( $_POST['rcp_card_zip'] ) );
				$bill_to->setEmail( $this->email );

				/**
				 * Create a transaction and add all the information.
				 */
				$transaction = new AnetAPI\TransactionRequestType();
				$transaction->setTransactionType( 'authCaptureTransaction' );
				$transaction->setAmount( $this->initial_amount );
				$transaction->setPayment( $payment );
				$transaction->setOrder( $order );
				$transaction->setBillTo( $bill_to );

				/**
				 * Make API request.
				 */
				$request = new AnetAPI\CreateTransactionRequest();
				$request->setMerchantAuthentication( $merchant_authentication );
				$request->setRefId( $refId );
				$request->setTransactionRequest( $transaction );
				$controller = new AnetController\CreateTransactionController( $request );

				$response = $controller->executeWithApiResponse( $environment );

				if ( $response != null && $response->getMessages()->getResultCode() == "Ok" ) {

					$transaction_response = $response->getTransactionResponse();

					if ( ! empty( $transaction_response ) && '1' == $transaction_response->getResponseCode() ) {

						/**
						 * Verify the sha2TransHash to confirm this response was actually from Authorize.net.
						 * If it doesn't match, show an error.
						 */
						$amount         = number_format( $this->initial_amount, 2, '.', '' );
						$authorize_hash = $transaction_response->getTransHashSha2();
						$my_hash        = $this->get_transHashSHA2( $transaction_response->getTransId(), $amount );

						if ( ! hash_equals( $authorize_hash, $my_hash ) ) {
							$error = '<p>' . __( 'There was a problem verifying your payment.', 'rcp-authorize-net' ) . '</p>';
							$error .= '<p>' . __( 'Error code: transHashSHA2 mismatch', 'rcp-authorize-net' ) . '</p>';
							$error .= '<p>' . sprintf( __( 'Error message: The hash for transaction ID %s did not match the one provided by the gateway.', 'rcp-authorize-net' ), $transaction_response->getTransId() ) . '</p>';

							$this->handle_processing_error( new \Exception( $error ) );
						}

						/**
						 * Payment was successful. Complete the pending payment and activate the subscription.
						 */
						$rcp_payments_db->update( $this->payment->id, array(
							'date'           => date( 'Y-m-d g:i:s', time() ),
							'payment_type'   => __( 'Authorize.net Credit Card One Time', 'rcp-authorize-net' ),
							'transaction_id' => 'anet_' . sanitize_text_field( $transaction_response->getTransID() ),
							'status'         => 'complete'
						) );

						do_action( 'rcp_gateway_payment_processed', $member, $this->payment->id, $this );

					} else {

						/**
						 * API request was successful but card was declined.
						 */
						$errors = $transaction_response->getErrors();
						$error  = '<p>' . __( 'There was a problem processing your payment.', 'rcp-authorize-net' ) . '</p>';
						$error  .= '<p>' . sprintf( __( 'Error code: %s', 'rcp-authorize-net' ), $errors[0]->getErrorCode() ) . '</p>';
						$error  .= '<p>' . sprintf( __( 'Error message: %s', 'rcp-authorize-net' ), $errors[0]->getErrorText() ) . '</p>';

						$this->handle_processing_error( new \Exception( $error ) );

					}

				} else {

					/**
					 * Something in the API request failed or no response from Authorize.net.
					 */
					$error_messages = $response->getMessages()->getMessage();
					$error          = '<p>' . __( 'There was a problem processing your payment.', 'rcp-authorize-net' ) . '</p>';
					$error          .= '<p>' . sprintf( __( 'Error code: %s', 'rcp-authorize-net' ), $error_messages[0]->getCode() ) . '</p>';
					$error          .= '<p>' . sprintf( __( 'Error message: %s', 'rcp-authorize-net' ), $error_messages[0]->getText() ) . '</p>';

					$this->handle_processing_error( new \Exception( $error ) );

				}

			}

		} catch ( \Exception $e ) {
			$this->handle_processing_error( $e );
		}

		// redirect to the success page, or error page if something went wrong
		wp_redirect( $this->return_url );
		exit;

	}

	/**
	 * Handles the error processing.
	 *
	 * @param \Exception $exception
	 *
	 * @since 1.0
	 * @return void
	 */
	protected function handle_processing_error( $exception ) {

		$this->error_message = $exception->getMessage();

		do_action( 'rcp_registration_failed', $this );

		wp_die( $exception->getMessage(), __( 'Error', 'rcp-authorize-net' ), array( 'response' => 401 ) );

	}

	/**
	 * Process webhooks
	 *
	 * @access public
	 * @since  1.0
	 * @return void
	 */
	public function process_webhooks() {

		$payments = new \RCP_Payments();

		rcp_log( 'Starting to process Authorize.net webhook.', true );

		$body       = file_get_contents( "php://input" );
		$event_json = json_decode( $body, true );

		if ( empty( $event_json ) ) {
			return;
		}

		if ( ! $this->is_webhook_valid( $body ) ) {
			rcp_log( 'Exiting Authorize.net webhook - invalid hash.', true );

			wp_die( __( 'Invalid hash.', 'rcp-authorize-net' ), __( 'Error', 'rcp-authorize-net' ), array( 'response' => 403 ) );
		}

		if ( empty( $event_json['eventType'] ) ) {
			rcp_log( 'Exiting Authorize.net webhook - missing event type.', true );

			wp_die( __( 'Missing event type.', 'rcp-authorize-net' ), __( 'Error', 'rcp-authorize-net' ), array( 'response' => 500 ) );
		}

		$event_type = $event_json['eventType'];

		switch ( $event_type ) {
			/**
			 * Subscription created.
			 *
			 * 'payload' => array(
			 *      'name'       => '',       // Subscription name
			 *      'amount'     => 00.00,    // Subscription price
			 *      'status'     => 'active', // Subscription status
			 *      'profile'    => array(
			 *          'customerProfileId'        => 123,
			 *          'customerPaymentProfileId' => 123,
			 *      ),
			 *      'entityName' => 'subscription',
			 *      'id'         => '123',    // Subscription ID.
			 * )
			 */
			case 'net.authorize.customer.subscription.created' :
				$subscription_id = $event_json['payload']['id'];

				rcp_log( sprintf( 'Processing customer.subscription.created for subscription ID %s.', $subscription_id ) );

				$this->membership = rcp_get_membership_by( 'gateway_subscription_id', 'anet_' . $subscription_id );

				if ( empty( $this->membership ) ) {
					rcp_log( sprintf( 'Exiting Authorize.net webhook - unable to find associated membership for subscription ID %s.', $subscription_id ) );

					die();
				}

				$member = $this->membership->get_customer()->get_member();

				do_action( 'rcp_webhook_recurring_payment_profile_created', $member, $this );
				break;

			/**
			 * Auth capture created.
			 *
			 * This is for both one-time payments and subscriptions. We have to get the full transaction details to
			 * find out which subscription it's for. Ugh.
			 *
			 * 'payload' => array(
			 *      'responseCode' => 1,     // Success
			 *      'authCode'     => '',
			 *      'avsResponse'  => 'Y',
			 *      'authAmount'   => 00.00,
			 *      'entityName'   => 'transaction',
			 *      'id'           => '123', // Transaction ID.
			 * )
			 */
			case 'net.authorize.payment.authcapture.created' :
				$transaction_id = ! empty( $event_json['payload']['id'] ) ? $event_json['payload']['id'] : '';

				if ( empty( $transaction_id ) ) {
					rcp_log( 'Exiting Authorize.net webhook - missing transaction ID.', true );

					wp_die( __( 'Missing transaction ID.', 'rcp-authorize-net' ), __( 'Error', 'rcp-authorize-net' ), array( 'response' => 500 ) );
				}

				rcp_log( sprintf( 'Processing payment.authcapture.created for transaction ID %s.', $transaction_id ) );

				$subscription = $this->get_transaction_subscription( $transaction_id );

				if ( empty( $subscription ) ) {
					rcp_log( 'Exiting Authorize.net webhook - this is a one-time payment.' );

					die();
				}

				$subscription_id  = $subscription->getId();
				$this->membership = rcp_get_membership_by( 'gateway_subscription_id', 'anet_' . $subscription_id );

				if ( empty( $this->membership ) ) {
					rcp_log( sprintf( 'Exiting Authorize.net webhook - unable to find associated membership for subscription ID %s.', $subscription_id ) );

					die();
				}

				$payment_data = array(
					'date'             => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
					'subscription'     => $this->membership->get_membership_level_name(),
					'payment_type'     => 'Credit Card',
					'subscription_key' => $this->membership->get_subscription_key(),
					'amount'           => sanitize_text_field( $event_json['payload']['authAmount'] ),
					'user_id'          => $this->membership->get_customer()->get_user_id(),
					'customer_id'      => $this->membership->get_customer_id(),
					'membership_id'    => $this->membership->get_id(),
					'transaction_id'   => sanitize_text_field( 'anet_' . $transaction_id ),
					'status'           => 'complete',
					'gateway'          => 'authorizenet'
				);

				$member = $this->membership->get_customer()->get_member();

				if ( 1 == $event_json['payload']['responseCode'] ) {

					// Payment approved.

					if ( $payments->payment_exists( 'anet_' . $transaction_id ) ) {
						do_action( 'rcp_ipn_duplicate_payment', 'anet_' . $transaction_id, $member, $this );

						die( 'duplicate payment found' );
					}

					$customer = $this->membership->get_customer();

					if ( version_compare( RCP_PLUGIN_VERSION, '3.1', '>=' ) ) {
						$pending_payment_id = rcp_get_membership_meta( $this->membership->get_id(), 'pending_payment_id', true );
					} else {
						$pending_payment_id = $customer->get_pending_payment_id();
					}

					if ( ! empty( $pending_payment_id ) ) {

						/*
						 * Complete a pending payment. This is the first payment made via registration.
						 */

						rcp_log( 'Processing approved Authorize.net payment via webhook - updating pending payment.' );

						// Unhook activation email if status is already "active".
						if ( 'active' == $this->membership->get_status() ) {
							remove_action( 'rcp_membership_post_activate', 'rcp_email_on_membership_activation', 10 );
						}

						$payments->update( absint( $pending_payment_id ), $payment_data );
						$payment_id = $pending_payment_id;

						// Void the authorized transaction.
						$auth_trans_id = rcp_get_membership_meta( $this->membership->get_id(), 'authorizenet_authonly_trans_id', true );
						if ( ! empty( $auth_trans_id ) ) {
							$void_auth_result = $this->void_transaction( $auth_trans_id );

							rcp_log( sprintf( 'Void auth result: %s', var_export( $void_auth_result, true ) ) );

							$this->membership->add_note( sprintf( __( 'Successfully voided initial authorization ID %s.', 'rcp' ), $auth_trans_id ) );
						}

					} else {

						/*
						 * Insert a renewal payment.
						 */

						$this->membership->renew( $this->membership->is_recurring() );

						rcp_log( 'Processing approved Authorize.net payment via webhook - inserting new payment.' );

						$payment_id = $payments->insert( $payment_data );

						do_action( 'rcp_webhook_recurring_payment_processed', $member, $payment_id, $this );

					}

					$this->membership->add_note( __( 'Subscription processed in Authorize.net', 'rcp-authorize-net' ) );

					do_action( 'rcp_authorizenet_silent_post_payment', $member, $this );
					do_action( 'rcp_gateway_payment_processed', $member, $payment_id, $this );

				} elseif ( 2 == $event_json['payload']['responseCode'] ) {

					// Payment declined
					rcp_log( 'Processing Authorize.net webhook - declined payment.' );

					$this->webhook_event_id = sanitize_text_field( $transaction_id );

					do_action( 'rcp_recurring_payment_failed', $member, $this );
					do_action( 'rcp_authorizenet_silent_post_error', $member, $this );

				} elseif ( 3 == $event_json['payload']['responseCode'] ) {

					// Payment error
					rcp_log( 'Processing Authorize.net webhook - error with payment.' );

					$this->webhook_event_id = sanitize_text_field( $transaction_id );

					do_action( 'rcp_recurring_payment_failed', $member, $this );
					do_action( 'rcp_authorizenet_silent_post_error', $member, $this );

				} else {

					// General error
					rcp_log( 'Processing Authorize.net webhook - general error with payment.' );

					$this->webhook_event_id = sanitize_text_field( $transaction_id );

					do_action( 'rcp_authorizenet_silent_post_error', $member, $this );

				}

				break;

			/**
			 * Subscription suspended.
			 * Such as due to non-payment.
			 *
			 *   'payload' => array (
			 *      'name'       => '',          // Subscription name
			 *      'amount'     => 00.00,       // Subscription price
			 *      'status'     => 'suspended', // Subscription status
			 *      'profile'    => array (
			 *          'customerProfileId'        => 123,
			 *          'customerPaymentProfileId' => 123,
			 *      ),
			 *      'entityName' => 'subscription',
			 *      'id'         => '123',       // Subscription ID.
			 * )
			 */
			case 'net.authorize.customer.subscription.suspended' :
				$subscription_id = 'anet_' . $event_json['payload']['id'];
				$membership      = rcp_get_membership_by( 'gateway_subscription_id', $subscription_id );

				rcp_log( sprintf( 'Processing net.authorize.customer.subscription.suspended event type for subscription ID %s.', $subscription_id ) );

				if ( empty( $membership ) ) {
					rcp_log( 'Exiting Authorize.net webhook - unable to find membership.' );

					die();
				}

				if ( ! $membership->is_active() ) {
					rcp_log( sprintf( 'Exiting Authorize.net webhook - membership #%d is not active.', $membership->get_id() ) );

					die();
				}

				$membership->expire();
				$membership->add_note( __( 'Membership expired via authorize.customer.subscription.suspended webhook.', 'rcp' ) );
				break;

			/**
			 * Subscription cancelled.
			 * This would only be used if the subscription were manually cancelled inside Authorize.net.
			 *
			 * 'payload' => array(
			 *      'name'       => '',          // Subscription name
			 *      'amount'     => 00.00,       // Subscription price
			 *      'status'     => 'cancelled', // Subscription status
			 *      'profile'    => array(
			 *          'customerProfileId'        => 123,
			 *          'customerPaymentProfileId' => 123,
			 *      ),
			 *      'entityName' => 'subscription',
			 *      'id'         => '123',       // Subscription ID.
			 * )
			 */
			case 'net.authorize.customer.subscription.cancelled' :
				$subscription_id = 'anet_' . $event_json['payload']['id'];
				$membership      = rcp_get_membership_by( 'gateway_subscription_id', $subscription_id );

				rcp_log( sprintf( 'Processing net.authorize.customer.subscription.cancelled event type for subscription ID %s.', $subscription_id ) );

				if ( empty( $membership ) ) {
					rcp_log( 'Exiting Authorize.net webhook - unable to find membership.' );

					die();
				}

				if ( ! $membership->is_active() ) {
					rcp_log( sprintf( 'Exiting Authorize.net webhook - membership #%d is not active.', $membership->get_id() ) );

					die();
				}

				$membership->cancel();
				break;
		}

		die( 'success' );

	}

	/**
	 * Given a transaction ID, get the associated subscription.
	 *
	 * @param string $transaction_id
	 *
	 * @since 1.0
	 * @return \net\authorize\api\contract\v1\SubscriptionPaymentType|false
	 */
	protected function get_transaction_subscription( $transaction_id ) {

		/**
		 * Create a merchantAuthenticationType object with authentication details.
		 */
		$merchant_authentication = new AnetAPI\MerchantAuthenticationType();
		$merchant_authentication->setName( $this->api_login_id );
		$merchant_authentication->setTransactionKey( $this->transaction_key );

		$request = new AnetAPI\GetTransactionDetailsRequest();
		$request->setMerchantAuthentication( $merchant_authentication );
		$request->setTransId( $transaction_id );

		$controller  = new AnetController\GetTransactionDetailsController( $request );
		$environment = rcp_is_sandbox() ? \net\authorize\api\constants\ANetEnvironment::SANDBOX : \net\authorize\api\constants\ANetEnvironment::PRODUCTION;
		$response    = $controller->executeWithApiResponse( $environment );

		if ( ( $response != null ) && ( $response->getMessages()->getResultCode() == "Ok" ) ) {
			return $response->getTransaction()->getSubscription();
		} else {
			return false;
		}

	}

	/**
	 * Void a transaction. Used for removing authorizations.
	 *
	 * @param int $transaction_id Transaction ID in Authorize.net.
	 *
	 * @since 1.0
	 * @return bool
	 */
	public function void_transaction( $transaction_id ) {

		/**
		 * Create a merchantAuthenticationType object with authentication details.
		 */
		$merchant_authentication = new AnetAPI\MerchantAuthenticationType();
		$merchant_authentication->setName( $this->api_login_id );
		$merchant_authentication->setTransactionKey( $this->transaction_key );

		// Set the transaction's refId
		$refId = 'ref' . time();

		// Create a transaction
		$transaction_request_type = new AnetAPI\TransactionRequestType();
		$transaction_request_type->setTransactionType( "voidTransaction" );
		$transaction_request_type->setRefTransId( $transaction_id );

		$request = new AnetAPI\CreateTransactionRequest();
		$request->setMerchantAuthentication( $merchant_authentication );
		$request->setRefId( $refId );
		$request->setTransactionRequest( $transaction_request_type );

		$controller  = new AnetController\CreateTransactionController( $request );
		$environment = rcp_is_sandbox() ? \net\authorize\api\constants\ANetEnvironment::SANDBOX : \net\authorize\api\constants\ANetEnvironment::PRODUCTION;
		$response    = $controller->executeWithApiResponse( $environment );

		if ( ( $response != null ) && ( $response->getMessages()->getResultCode() == "Ok" ) ) {
			$transaction_response = $response->getTransactionResponse();

			return $transaction_response->getResponseCode();
		} else {
			return false;
		}

	}

	/**
	 * Load credit card fields
	 *
	 * @access public
	 * @since  1.0
	 * @return string
	 */
	public function fields() {
		ob_start();
		rcp_get_template_part( 'card-form' );

		return ob_get_clean();
	}

	/**
	 * Determines if the webhook is valid by verifying the SHA256 hash.
	 *
	 * @param string $body
	 *
	 * @since 1.0
	 * @return bool
	 */
	public function is_webhook_valid( $body ) {

		$auth_hash = isset( $_SERVER['HTTP_X_ANET_SIGNATURE'] ) ? strtoupper( explode( '=', $_SERVER['HTTP_X_ANET_SIGNATURE'] )[1] ) : '';

		if ( empty( $auth_hash ) ) {
			return false;
		}

		$generated_hash = strtoupper( hash_hmac( 'sha512', $body, $this->transaction_signature ) );

		return hash_equals( $auth_hash, $generated_hash );

	}

	/**
	 * Get the transHashSHA2 value for a transaction so it can be verified at Authorize.net.
	 * See: https://developer.authorize.net/support/hash_upgrade/
	 *
	 * @param string $transaction_id
	 * @param float  $transaction_amount
	 *
	 * @since 1.0
	 * @return string
	 */
	protected function get_transHashSHA2( $transaction_id, $transaction_amount ) {

		// Get the API Key we used to send API requests to Authorize.net
		$api_key = $this->api_login_id;

		// Convert the Signature in the Authorize.net Settings to a Byte Array
		$key = hex2bin( $this->transaction_signature );

		// Build the string that Authorize.net wants us to create (see link in function description)
		$string = '^' . $api_key . '^' . $transaction_id . '^' . $transaction_amount . '^';

		// Hash it using our signature as the secret
		return strtoupper( HASH_HMAC( 'sha512', $string, $key ) );

	}

}
<?php
/**
 * Gateway class
 */
class WC_PurePay extends WC_Payment_Gateway {

	const MMS_URL                = 'https://admin.purepay.eu';
	const DEFAULT_HOSTED_URL     = 'https://gateway.purepay.eu/paymentform/';
	const DEFAULT_DIRECT_URL     = 'https://gateway.purepay.eu/direct/';
	const DEFAULT_MERCHANT_ID    = '114731';
	const DEFAULT_SECRET         = 'uH2JZDtzdKnkj4Hh';

	private $gateway     = 'PurePay';
	public  $gateway_url = '';

	public static $lang;
	
	public function __construct() {

		$id = str_replace(' ', '', strtolower($this->gateway));

		// Language translation module to use
		self::$lang = 'Purepay';

		$this->has_fields          = false;
		$this->id                  = $id;
		$this->icon                = str_replace('/classes', '/', plugins_url('/', __FILE__)) . '/img/logo.png';
		$this->method_title        = __(ucwords($this->gateway), self::$lang);
		$this->method_description  = __(ucwords($this->gateway) . ' hosted works by sending the user to ' . ucwords($this->gateway) . ' to enter their payment infomation', self::$lang);
		$this->supports = array (
			'subscriptions',
			'products',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change'
		);
		$this->init_form_fields();

		$this->init_settings();

		// Get setting values
		$this->enabled             = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'no';
		$this->title               = isset($this->settings['title']) ? $this->settings['title'] : 'Credit Card via ' . strtoupper($this->gateway);
		$this->description         = isset($this->settings['description']) ? $this->settings['description'] : 'Pay via Credit / Debit Card with ' . strtoupper($this->gateway) . ' secure card processing.';
		$this->gateway             = isset($this->settings['gateway']) ? $this->settings['gateway'] : $this->gateway;
		$this->type                = isset($this->settings['type']) ? $this->settings['type'] : 'hosted';

		// Custom forms
		$this->gateway_url = $this->settings['gatewayURL'];

		if (
			// Make sure we're given an valid URL
			!empty($this->gateway_url) &&
			preg_match('/(http[s]?:\/\/[a-z0-9\.]+(?:\/[a-z]+\/?){1,})/i', $this->gateway_url) != false
		) {
			// Prevent insecure requests
			$this->gateway_url = str_ireplace('http://', 'https://', $this->gateway_url);
			// Always append end slash
			if (preg_match('/(\.php|\/)$/', $this->gateway_url) == false) {
				$this->gateway_url .= '/';
			}
			// Prevent direct requests using hosted
			if (isset($this->settings['type']) && $this->settings['type'] == 'hosted' && preg_match('/(\/direct\/)$/i', $this->gateway_url) != false) {
				$this->gateway_url = self::DEFAULT_HOSTED_URL;
			}
		} else {
			if (isset($this->settings['type']) && $this->settings['type'] == 'direct') {
				$this->gateway_url = self::DEFAULT_DIRECT_URL;
			} else {
				$this->gateway_url = self::DEFAULT_HOSTED_URL;
			}
		}

		// Hooks
		/* 1.6.6 */
		add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));

		/* 2.0.0 */
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

		add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
		add_action('woocommerce_api_wc_' . $this->id, array($this, 'process_response'));
		add_action('woocommerce_api_wc_' . $this->id . '_callback', array($this, 'process_response'));

		add_action('get_footer', 'render_footer');			


		/* 3.0.0 Subscriptions */
		add_action('woocommerce_scheduled_subscription_payment_purepay', array( $this, 'scheduled_subscription_payment'),10,3);
		if(!function_exists('wpb_adding_scripts')){
			function wpb_adding_scripts() { // Register our scripts only when function call
				wp_register_script('checkout_card_js', plugin_dir_url( __FILE__ ) . 'extra/jquery.card.js', true);
				wp_register_script('checkout_js', plugin_dir_url( __FILE__ ) . 'extra/checkout-card.min.js', true);
				wp_register_style('form_style', plugin_dir_url( __FILE__ ) . 'extra/form-style.css', true);
				wp_enqueue_script('checkout_card_js');
				wp_enqueue_script('checkout_js');
				wp_enqueue_style('form_style');
			}
		}
		if(!function_exists('render_footer')){
			function render_footer() {
				$content = '
				<div class="pure-pay-footer">
						<div class="rowPure">
							<div class="columnPure">
								<div class="pure-col1">
									<div class="pure-pay-footer-logo"></div>
								</div>
							</div>
							<div class="columnPure">
								<div class="pure-col2">
								<a class="pure-pay-link" href="https://purepay.eu/">Secured by PurePay <svg height="12px" width="12px" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="lock" class="lock-vector svg-inline--fa fa-lock fa-w-14" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="currentColor" d="M400 224h-24v-72C376 68.2 307.8 0 224 0S72 68.2 72 152v72H48c-26.5 0-48 21.5-48 48v192c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48V272c0-26.5-21.5-48-48-48zm-104 0H152v-72c0-39.7 32.3-72 72-72s72 32.3 72 72v72z"></path></svg></a>
								</div>
							</div>
						</div>
					</div>';
				echo $content;
			}
		}
	}
	

	/**
	 * Initialise Gateway Settings
	 */
	function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'       => __('Enable/Disable', self::$lang),
				'label'       => __('Enable ' . strtoupper($this->gateway), self::$lang),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),

			'title' => array(
				'title'       => __('Title', self::$lang),
				'type'        => 'text',
				'description' => __('This controls the title which the user sees during checkout.', self::$lang),
				'default'     => __(strtoupper(ucwords($this->gateway)), self::$lang)
			),

			'type' => array(
				'title'       => __('Type of integration', self::$lang),
				'type'        => 'select',
				'options' => array(
					'hosted'  => 'Hosted',
					'iframe'  => 'Embedded (iframe)',
					'direct'  => 'Direct'
				),
				'description' => __('This controls method of integration.', self::$lang),
				'default'     => 'hosted'
			),

			'description' => array(
				'title'       => __('Description', self::$lang),
				'type'        => 'textarea',
				'description' => __('This controls the description which the user sees during checkout.', self::$lang),
				'default'     => 'Pay securely via Credit / Debit Card with ' . ucwords($this->gateway)
			),

			'merchantID' => array(
				'title'       => __('Merchant ID', self::$lang),
				'type'        => 'text',
				'description' => __('Please enter your ' . ucwords($this->gateway) . ' merchant ID', self::$lang),
				'default'     => self::DEFAULT_MERCHANT_ID
			),

			'signature' => array(
				'title'       => __('Signature Key', self::$lang),
				'type'        => 'text',
				'description' => __('Please enter the signature key for the merchant account. This can be changed in the <a href="' . self::MMS_URL . '" target="_blank">MMS</a>', self::$lang),
				'default'     => self::DEFAULT_SECRET
			),

			'formResponsive' => array(
				'title'       => __('Responsive form', self::$lang),
				'type'        => 'select',
				'options' => array(
					'Y'       => 'Yes',
					'N'       => 'No'
				),
				'description' => __('This controls whether the payment form is responsive.', self::$lang),
				'default'     => 'No'
			),

			'gatewayURL' => array(
				'title'       => __('Gateway URL', self::$lang),
				'type'        => 'text',
				'description' => __('Allows the use of custom forms. Leave blank to use default', self::$lang)
			),

			'countryCode' => array(
				'title'       => __('Country Code', self::$lang),
				'type'        => 'text',
				'description' => __('Please enter your 3 digit <a href="http://en.wikipedia.org/wiki/ISO_3166-1" target="_blank">ISO country code</a>', self::$lang),
				'default'     => '826'
			),

			'failedOrderMessage' => array(
				'title'       => __('Failed Order Message', self::$lang),
				'type'        => 'text',
				'description' => __('Use this feild to customize the message shown when a customers card is declined.', self::$lang),
				'default'     => 'Your Card Was Declined'
			),

		);

	}

	public function capture_order($order_id) {
		global $woocommerce;

		$order     = new WC_Order($order_id);
		$countries = new WC_Countries();
		$amount    = intval(bcmul(round($order->get_total(), 2), 100, 0));

		$billing_address  = $order->get_billing_address_1();
		$billing2 = $order->get_billing_address_2();

		if (!empty($billing2)) {
			$billing_address .= "\n" . $billing2;
		}
		$billing_address .= "\n" . $order->get_billing_city();
		$state = $order->get_billing_state();
		if (!empty($state)) {
			$billing_address .= "\n" . $state;
			unset($state);
		}
		$country = $order->get_billing_country();
		if (!empty($country)) {
			$billing_address .= "\n" . $country;
			unset($country);
		}

		// Fields for hash
		$req = array(
			'merchantID'        => $this->settings['merchantID'],
			'amount'            => $amount,
			'countryCode'       => $this->settings['countryCode'],
			'currencyCode'      => $order->get_currency(),
			'transactionUnique' => $order->get_order_key() . '-' . time(),
			'orderRef'          => $order_id,
			'customerName'      => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'customerAddress'   => $billing_address,
			'customerPostCode'  => $order->get_billing_postcode(),
			'customerEmail'     => $order->get_billing_email(),
		);

		$phone = $order->get_billing_phone();
		if (!empty($phone)) {
			$req['customerPhone'] = $phone;
			unset($phone);
		}

		return $req;
	}

	/**
	 * Generate the form buton
	 */
	public function generate_purepay_form($order_id) {
		if ($this->type == 'hosted') {
			echo $this->generate_purepay_hosted_form($order_id);
		} else if ($this->type == 'iframe') {
			echo $this->generate_purepay_embedded_form($order_id);
		} else if ($this->type == 'direct') {
			echo $this->generate_purepay_direct_form($order_id);
		} else {
			return null;
		}
	}

	/**
	 * Hosted form
	 */
	public function generate_purepay_hosted_form($order_id) {
		global $woocommerce;

		$order     = new WC_Order($order_id);
		$redirect  = add_query_arg('wc-api', 'WC_PurePay', home_url('/'));
		$callback  = add_query_arg('wc-api', 'WC_PurePay_Callback', home_url('/'));

		$req = array_merge($this->capture_order($order_id), array(
			'redirectURL'       => $redirect,
			'callbackURL'       => $callback,
			'formResponsive'    => $this->settings['formResponsive']
		));

		if (isset($this->settings['signature']) && !empty($this->settings['signature'])) {
			$req['signature'] = $this->create_signature($req, $this->settings['signature']);
		}
		echo '<p>' . __('Thank you for your order, please click the button below to pay with ' . ucwords($this->gateway) . '.', self::$lang) . '</p>';
		$form = '<form action="' . $this->gateway_url . '" method="post" id="' . $this->gateway . '_payment_form">';

		foreach ($req as $key => $value) {
			$form .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
		}
		$form .= '<input type="submit" class="button alt" value="' . __('Pay securely via ' . ucwords($this->gateway), self::$lang) . '" />';
		$form .= '&nbsp;<a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel order', self::$lang) . '</a>';
		$form .= '</form>';

		return $form;

	}

	/**
	 * Embedded form
	 */
	public function generate_purepay_embedded_form($order_id) {
		global $woocommerce;

		$redirect  = add_query_arg('wc-api', 'WC_PurePay', home_url('/'));
		$callback  = add_query_arg('wc-api', 'WC_PurePay_Callback', home_url('/'));

		$req = array_merge($this->capture_order($order_id), array(
			'redirectURL'       => $redirect,
			'callbackURL'       => $callback,
			'formResponsive'    => $this->settings['formResponsive']
		));

		if (isset($this->settings['signature']) && !empty($this->settings['signature'])) {
			$req['signature'] = $this->create_signature($req, $this->settings['signature']);
		}

		require(__DIR__ . '/../embedded.php');
		return '';

	}

	/**
	 * Direct form step 1
	 */

	public function generate_purepay_direct_form($order_id, $errors = array()) {
		echo '<div class="card-area"></div>';	
		wpb_adding_scripts();
		global $woocommerce;
		$order = new WC_Order($order_id);

		// Fields for hash
		$fields = array(
			'full-name' => array(
				'name' => '',
				'placeholder' => 'Full Name',
				'value' => @$_POST['name'],
				'required' => 'required'
			),
			'cardNumber' => array(
				'name' => '',
				'placeholder' => 'Card Number',
				'value' => @$_POST['cardNumber'],
				'required' => 'required'
			),
			'cardExpiryMonth' => array(
				'name' => '',
				'value' => @$_POST['cardExpiryMonth'],
				'required' => 'required',
				'placeholder' => 'MM',
				'maxlength' => '2'
			),
			'cardExpiryYear' => array(
				'name' => '',
				'value' => @$_POST['cardExpiryYear'],
				'required' => 'required',
				'placeholder' => 'YYYY',
				'maxlength' => '4'
			),
			'cardCVV' => array(
				'name' => '',
				'placeholder' => 'CVV',
				'value' => @$_POST['cardCVV'],
				'required' => 'required'
			)
		);
		
		$form = '<form action="' . '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '&step=2" method="post" id="' . $this->gateway . '_payment_form">';

		foreach ($fields as $key => $value) {
			//$form .= '<label class="card-label label-' . $key . '">' . $value['name'] . '</label>'; Removed Labels 11/21/19
			if (array_search($key, $errors) !== false) {
				$value['style'] = 'border: 1px solid red;';
			}
			$value['name'] = $key;
			$form .= "<input type='text' class='card-input field-${key}'";

			// Go through attribute keys and values
			foreach ($value as $ak => $av) {
				$form .= " ${ak}='${av}'";
			}
			$form .= '/>';
		}
		
		$form .= '<br/><input type="submit" class="button alt" value="' . __('Pay securely via ' . ucwords($this->gateway), self::$lang) . '" />';
		$form .= '&nbsp;<a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel order', self::$lang) . '</a>';
		$form .= '</form>';

		
		return $form;

	} // End Direct Function

	/**
	 * Direct form step 2
	 */
	public function generate_purepay_direct_form_step2($order_id, $request = array()) {

		global $woocommerce;

		$order = new WC_Order($order_id);

		$req = array_merge($this->capture_order($order_id), array(
			'action'            => 'SALE',
			'type'              => 1,
			'cardNumber'        => $_POST['cardNumber'],
			'cardExpiryMonth'   => $_POST['cardExpiryMonth'],
			'cardExpiryYear'    => $_POST['cardExpiryYear'],
			'cardCVV'           => $_POST['cardCVV'],
			'threeDSMD'         => (isset($_REQUEST['MD']) ? $_REQUEST['MD'] : null),
			'threeDSPaRes'      => (isset($_REQUEST['PaRes']) ? $_REQUEST['PaRes'] : null),
			'threeDSPaReq'      => (isset($_REQUEST['PaReq']) ? $_REQUEST['PaReq'] : null)
		));

		if (isset($this->settings['signature']) && !empty($this->settings['signature'])) {
			$req['signature'] = $this->create_signature($req, $this->settings['signature']);
		}


		$ch = curl_init($this->gateway_url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($req));
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		parse_str(curl_exec($ch), $res);
		curl_close($ch);

		if ($res['responseCode'] == 65802) {

			// Send details to 3D Secure ACS and the return here to repeat request

			$pageUrl = (@$_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';

			if ($_SERVER['SERVER_PORT'] != '80') {
				$pageUrl .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
			} else {
				$pageUrl .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
			}
			?>
			<p>Your transaction requires 3D Secure Authentication</p>
			<form action="<?=htmlentities($res['threeDSACSURL'])?>" method="post">
				<input type="hidden" name="MD" value="<?=htmlentities($res['threeDSMD'])?>">
				<input type="hidden" name="PaReq" value="<?=htmlentities($res['threeDSPaReq'])?>">
				<input type="hidden" name="TermUrl" value="<?=htmlentities($pageUrl)?>">
				<input type="submit" value="Continue">
			</form>
			<?php

		} else {

			if (empty($res)) {

				$message = __('Payment error: ', 'woothemes') . 'Communication error with server';

				if (method_exists($woocommerce, add_error)) {
					$woocommerce->add_error($message);
				} else {
					wc_add_notice($message, $notice_type = 'error');
				}

				$order->add_order_note(__(ucwords($this->gateway) . ' payment failed. Communication error with server or an empty response was received', self::$lang));
				wp_safe_redirect($order->get_cancel_order_url($order));
				exit;

			}
			return $this->process_response($res);

		}

		return $form;

	}

	/**
	 * Function to generate a signature
	 */
	function create_signature(array $data, $key) {

		if (!$key || !is_string($key) || $key === '' || !$data || !is_array($data)) {
				return null;
		}

		ksort($data);

		// Create the URL encoded signature string
		$ret = http_build_query($data, '', '&');

		// Normalise all line endings (CRNL|NLCR|NL|CR) to just NL (%0A)
		$ret = preg_replace('/%0D%0A|%0A%0D|%0A|%0D/i', '%0A', $ret);

		// Hash the signature string and the key together
		return hash('SHA512', $ret . $key);

	}


	/**
	 * Process the payment and return the result
	 */
	function process_payment($order_id) {

		$order = new WC_Order($order_id);

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url(true)
		);

	}


	/**
	 * receipt_page
	 */
	function receipt_page($order) {

		global $woocommerce;

		if (isset($_REQUEST['step']) && (int)$_REQUEST['step'] === 2) {

			$required = array('cardNumber', 'cardCVV', 'cardExpiryMonth', 'cardExpiryYear');
			$errors = array();

			// Check that the required fields are present:
			foreach ($required as $i => $field) {
				if (
					(isset($_POST[$field]) && empty($_POST[$field])) ||
					!isset($_POST[$field])
				) {
					array_push($errors, $field);
				}
			}

			// Check year is a numeric and has either two or four digits
			if (
				array_search('cardExpiryYear', $errors) === false &&
				($year = $_POST['cardExpiryYear']) &&
				is_numeric($year) &&
				(strlen($year) == 2 || strlen($year) == 4)
			) {
				if (strlen($year) == 4) {
					$_POST['cardExpiryYear'] = substr($year, 2);
				}
			} else {
				array_push($errors, 'cardExpiryYear');
			}

			// Check month is a numeric and has two digits
			if (!(
				array_search('cardExpiryMonth', $errors) === false &&
				($month = $_POST['cardExpiryMonth']) &&
				is_numeric($month) && strlen($month) == 2
			)) {
				array_push($errors, 'cardExpiryMonth');
			}


			if (count($errors) > 0) {

				echo $this->generate_purepay_direct_form($order, $errors);

			} else {

				echo $this->generate_purepay_direct_form_step2($order, $_REQUEST);

			}

		} else {

			echo $this->generate_purepay_form($order);

		}

	}

	/**
	 * Redirect to the URL provided depending on integration type
	 */
	private function redirect($url) {
		if ($this->type === 'iframe') {
			echo '<script>window.top.location = "' . $url . '";</script>';
		} else {
			wp_safe_redirect($url);
		}
		exit;
	}

	/**
	 * Check for response from payment gateway
	 */
	function process_response($data = null) {

		global $woocommerce;

		$_POST = array_map('stripslashes_deep', $_POST);

		$response = $data ?: $_POST;

		if (empty($response) || !isset($response['orderRef']) || !is_numeric($response['orderRef'])) {
			$this->throw_empty_response();
		}

		$order = new WC_Order((int)$response['orderRef']);
		if (!$this->check_signature($response, $this->settings['signature'])) {
			$this->throw_signature_error($order);
		}

		if (isset($response['responseCode'])) {

			if ($order->get_status == 'completed') {
				$this->redirect($this->get_return_url($order));
			} else {

				$orderNotes  = "\r\nResponse Code : {$response['responseCode']}\r\n";
				$orderNotes .= "Message : {$response['responseMessage']}\r\n";
				$orderNotes .= "Amount Received : " . number_format($response['amount'] / 100, 2, '.', ',') . "\r\n";
				$orderNotes .= "Unique Transaction Code : {$response['transactionUnique']}";

				if ($response['responseCode'] === '0') {
					$order->set_transaction_id($response['xref']);
					$order->add_order_note(__(ucwords($this->gateway) . ' payment completed.' . $orderNotes, self::$lang));
					$order->payment_complete();
					$this->redirect($this->get_return_url($order));

				} else {
					$message = __('Payment error: ', 'woothemes') . $response['responseMessage'];
	
					if (method_exists($woocommerce, add_error)) {
						$woocommerce->add_error($message);
					}
					$order->add_order_note(__(ucwords($this->gateway) . ' payment failed.' . $orderNotes, self::$lang));
					wp_safe_redirect($order->get_checkout_payment_url($order));
					wc_add_notice( __('Payment error: Your Card Was Declined or Rejected.', 'woothemes') . $error_message, 'error' );
					exit(); // always exit
				}

			}

		} else {
			exit;
		}

	}

	/**
	 * Check the signature received in a response
	 */
	function check_signature(array $data, $key) {
		$current_sig = $data['signature'];
		unset($data['signature']);
		$generated_sig = $this->create_signature($data, $key);
		return ($current_sig === $generated_sig);

	}

	/**
	 * Redirect to the checkout when the server response is not legitimate
	 */
	function throw_signature_error($order){
		$message = "\r\n" . __('Payment error: ', 'woothemes') . "Signature Check Failed";
		if (method_exists($woocommerce, add_error)) {
			$woocommerce->add_error($message);
		} else {
			wc_add_notice($message, $notice_type = 'error');
		}
		$order->add_order_note(__(ucwords($this->gateway).' payment failed' . $message, self::$lang));
		$this->redirect($order->get_checkout_payment_url(true));
		exit;
	}

	function throw_empty_response() {
		$message = 'Payment unsuccessful - empty response (contact Administrator)';
		if (method_exists($woocommerce, add_error)) {
			$woocommerce->add_error($message);
		} else {
			wc_add_notice($message, $notice_type = 'error');
		}
		$this->redirect(get_site_url());
		exit;
	}

	/**
	* Hook to process a subscriptions payment
	*/
	function scheduled_subscription_payment( $amount_to_charge, $order) {

		$result = $this->process_subscription_payment( $order, $amount_to_charge );
		if ( is_wp_error( $result ) ) {

			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order );
		} else {
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
		}
	}


	function process_subscription_payment ( $renewal_order, $amount=0 ) {
			global $woocommerce;

			// Gets all subscriptions (hopefully just one) linked to this order
			$subs = wcs_get_subscriptions_for_renewal_order($renewal_order);

			// Get all orders on this subscription and remove any that haven't been paid
			$orders = array_filter(current($subs)->get_related_orders('all'),function($ord){return $ord->is_paid();});

			// Replace every order with orderId=>xref kvps
			$xrefs = array_map(function($ord){return $ord->get_transaction_id();},$orders);

			// Return the xref corresponding to the most recent order (assuming order number increase with time)
			$xref = $xrefs[max(array_keys($xrefs))];

			$req = array(
				'merchantID' => $this->settings['merchantID'],
				'xref' => $xref,
				'amount' => intval(bcmul(round($amount, 2), 100, 0)),
				'action' => "SALE",
				'type' => 9,
			);


			// Sign and send request
			$req['signature'] = $this->create_signature($req,$this->settings['signature']);
			$ch = curl_init(self::DEFAULT_DIRECT_URL);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($req));
			curl_setopt($ch, CURLOPT_HEADER, false);
			//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			parse_str(curl_exec($ch), $res);
			curl_close($ch);

			$orderNotes  = "\r\nResponse Code : {$res['responseCode']}\r\n";
			$orderNotes .= "Message : {$res['responseMessage']}\r\n";
			$orderNotes .= "Amount Received : " . number_format($res['amount'] / 100, 2, '.', ',') . "\r\n";
			$orderNotes .= "Unique Transaction Code : {$res['transactionUnique']}";


			// handle response
			if($this->check_signature($res,$this->settings['signature'])){
				if(isset($res['responseCode']) && $res['responseCode']==0){
					$renewal_order->set_transaction_id($res['xref']);
					$renewal_order->add_order_note(__(ucwords($this->gateway) . ' payment completed.' . $orderNotes, self::$lang));
					$renewal_order->payment_complete();
					$renewal_order->save();
					return true;
				} else {
					$renewal_order->add_order_note(__(ucwords($this->gateway) . ' payment failed with signature error.' . $orderNotes, self::$lang));
					$renewal_order->save();
					return new WP_Error('payment_failed_error','recurring payment failed due to gateway decline');
				}
			} else {
				$message = "\r\n" . __('Payment error: ', 'woothemes') . "Signature Check Failed";
				$renewal_order->add_order_note(__(ucwords($this->gateway).' payment failed' . $orderNotes, self::$lang));
				$renewal_order->save();
				return new WP_Error('signature_error','recurring payment failed due to signature error');
			}

	}

	

	/**
	 * Check for Callback Response
	 * @deprecated Duplicate function. Use process_response instead.
	 */
	function process_callback() {
		$this->process_response();
	}

		
}
<?php
/**
 * Alipay Gateway
 *
 * @package blesta
 * @subpackage blesta.components.gateways.nonmerchant.alipay
 * @author Bill Zhong
 */
class Alipay extends NonmerchantGateway {
	/**
	 * @var string The version of this gateway
	 */
	private static $version = "1.0.0";
	/**
	 * @var string The authors of this gateway
	 */
	private static $authors = array(array('name' => "Bill Zhong", 'url' => "http://billzhong.com"));
	/**
	 * @var array An array of meta data for this gateway
	 */
	private $meta;
	/**
	 * @var string The URL to post payments to
	 */
	private $alipay_api = "https://mapi.alipay.com/gateway.do";

	/**
	 * Construct a new merchant gateway
	 */
	public function __construct() {
		
		// Load components required by this gateway
		Loader::loadComponents($this, array("Input", "Net"));
		
		// Load the language required by this gateway
		Language::loadLang("alipay", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this gateway
	 *
	 * @return string The common name of this gateway
	 */
	public function getName() {
		return Language::_("Alipay.name", true);
	}
	
	/**
	 * Returns the version of this gateway
	 *
	 * @return string The current version of this gateway
	 */
	public function getVersion() {
		return self::$version;
	}

	/**
	 * Returns the name and URL for the authors of this gateway
	 *
	 * @return array The name and URL of the authors of this gateway
	 */
	public function getAuthors() {
		return self::$authors;
	}
	
	/**
	 * Return all currencies supported by this gateway
	 *
	 * @return array A numerically indexed array containing all currency codes (ISO 4217 format) this gateway supports
	 */
	public function getCurrencies() {
		return array("CNY");
	}
	
	/**
	 * Sets the currency code to be used for all subsequent payments
	 *
	 * @param string $currency The ISO 4217 currency code to be used for subsequent payments
	 */
	public function setCurrency($currency) {
		$this->currency = $currency;
	}
	
	/**
	 * Create and return the view content required to modify the settings of this gateway
	 *
	 * @param array $meta An array of meta (settings) data belonging to this gateway
	 * @return string HTML content containing the fields to update the meta data for this gateway
	 */
	public function getSettings(array $meta=null) {
		$this->view = $this->makeView("settings", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));

		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("meta", $meta);
		
		return $this->view->fetch();
	}
	
	/**
	 * Validates the given meta (settings) data to be updated for this gateway
	 *
	 * @param array $meta An array of meta (settings) data to be updated for this gateway
	 * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
	 */
	public function editSettings(array $meta) {
		// Verify meta data is valid
		$rules = array(
			'email' => array(
				'valid' => array(
					'rule' => array("isEmail", false),
					'message' => Language::_("Alipay.!error.account_id.valid", true)
				)
			),
			'pid' => array(
				'valid' => array(
					'rule' => array("betweenLength", 16, 16),
					'message' => Language::_("Alipay.!error.pid.valid", true)
				)
			),
			'key' => array(
				'valid' => array(
					'rule' => array("isEmpty"),
					'negate' => true,
					'message' => Language::_("Alipay.!error.key.valid", true)
				)
			),

		);
		
		$this->Input->setRules($rules);
		
		// Validate the given meta data to ensure it meets the requirements
		$this->Input->validates($meta);
		// Return the meta data, no changes required regardless of success or failure for this gateway
		return $meta;
	}
	
	/**
	 * Returns an array of all fields to encrypt when storing in the database
	 *
	 * @return array An array of the field names to encrypt when storing in the database
	 */
	public function encryptableFields() {
		
		return array("key");
	}
	
	/**
	 * Sets the meta data for this particular gateway
	 *
	 * @param array $meta An array of meta data to set for this gateway
	 */
	public function setMeta(array $meta=null) {
		$this->meta = $meta;
	}
	
	/**
	 * Returns all HTML markup required to render an authorization and capture payment form
	 *
	 * @param array $contact_info An array of contact info including:
	 * 	- id The contact ID
	 * 	- client_id The ID of the client this contact belongs to
	 * 	- user_id The user ID this contact belongs to (if any)
	 * 	- contact_type The type of contact
	 * 	- contact_type_id The ID of the contact type
	 * 	- first_name The first name on the contact
	 * 	- last_name The last name on the contact
	 * 	- title The title of the contact
	 * 	- company The company name of the contact
	 * 	- address1 The address 1 line of the contact
	 * 	- address2 The address 2 line of the contact
	 * 	- city The city of the contact
	 * 	- state An array of state info including:
	 * 		- code The 2 or 3-character state code
	 * 		- name The local name of the country
	 * 	- country An array of country info including:
	 * 		- alpha2 The 2-character country code
	 * 		- alpha3 The 3-cahracter country code
	 * 		- name The english name of the country
	 * 		- alt_name The local name of the country
	 * 	- zip The zip/postal code of the contact
	 * @param float $amount The amount to charge this contact
	 * @param array $invoice_amounts An array of invoices, each containing:
	 * 	- id The ID of the invoice being processed
	 * 	- amount The amount being processed for this invoice (which is included in $amount)
	 * @param array $options An array of options including:
	 * 	- description The Description of the charge
	 * 	- return_url The URL to redirect users to after a successful payment
	 * 	- recur An array of recurring info including:
	 * 		- amount The amount to recur
	 * 		- term The term to recur
	 * 		- period The recurring period (day, week, month, year, onetime) used in conjunction with term in order to determine the next recurring payment
	 * @return string HTML markup required to render an authorization and capture payment form
	 */
	public function buildProcess(array $contact_info, $amount, array $invoice_amounts=null, array $options=null) {
		$this->view = $this->makeView("process", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));

		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$fields = array(
//			"service" => "trade_create_by_buyer", //双接口，注释下行
			"service" => "create_direct_pay_by_user",
			"partner" => $this->ifSet($this->meta['pid']),
			"_input_charset" => "utf-8",
			'notify_url' => Configure::get("Blesta.gw_callback_url") . Configure::get("Blesta.company_id") . "/alipay/?client_id=".$this->ifSet($contact_info['client_id']),
			"return_url" => $this->ifSet($options['return_url']),
			"out_trade_no" => $this->ifSet($contact_info['client_id']) . "-" . time(),
			"subject" => str_replace(' ', '', $this->ifSet($options['description']) ),
			"payment_type" => "1",
//			"logistics_type" => "EXPRESS",//双接口
//			"logistics_fee" => "0.00",//双接口
//			"logistics_payment" => "SELLER_PAY",//双接口
			"seller_email" => $this->ifSet($this->meta['email']),
//			"price" => round($amount, 2), //双接口，注释下行
			"total_fee" => round($amount, 2),
//			"quantity" => "1",//双接口
//			"receive_name" => "--",//双接口
//			"receive_address" => "--",//双接口
//			"receive_zip" => "0",//双接口
//			"receive_phone" => "000000",//双接口
		);

		if (isset($invoice_amounts) && is_array($invoice_amounts))
			$fields['body'] = $this->serializeInvoices($invoice_amounts);

		$this->view->set("post_to", $this->alipay_api);
		$this->view->set("fields",  $this->sign($fields));
		
		return $this->view->fetch();
	}
	
	/**
	 * Validates the incoming POST/GET response from the gateway to ensure it is
	 * legitimate and can be trusted.
	 *
	 * @param array $get The GET data for this request
	 * @param array $post The POST data for this request
	 * @return array An array of transaction data, sets any errors using Input if the data fails to validate
	 *  - client_id The ID of the client that attempted the payment
	 *  - amount The amount of the payment
	 *  - currency The currency of the payment
	 *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
	 *  	- id The ID of the invoice to apply to
	 *  	- amount The amount to apply to the invoice
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the gateway to identify this transaction
	 */
	public function validate(array $get, array $post) {

		$backup = $post;

		$client_id = $this->ifSet($post['client_id']);
		unset($post['client_id']);
		unset($post['sign_type']);
		$sign = $post['sign'];
		unset($post['sign']);

		if ($this->checkSign($post, $sign) && $this->checkNotify($this->ifSet($post['notify_id']))) {

			$this->log('CALLBACK: '.$this->ifSet($_SERVER['REQUEST_URI']), serialize($backup), "input", true);
			echo 'success';

//			if ($this->ifSet($post['trade_status']) == 'WAIT_SELLER_SEND_GOODS') {
//				$this->sendGoods($this->ifSet($post['trade_no']));
//			}

			if ($this->ifSet($post['trade_status']) == 'TRADE_FINISHED') {
				if ($this->checkTransID($this->ifSet($post['trade_no']), $client_id)) {
					return null;
				} else {
					return array(
						'client_id' => $client_id,
						'amount' => $this->ifSet($post['price']),
						'currency' => 'CNY',
						'invoices' => $this->unserializeInvoices($this->ifSet($post['body'])),
						'status' => 'approved',
						'transaction_id' => $this->ifSet($post['trade_no'])
					);
				}
			}

		} else {
			$this->log('CALLBACK: '.$this->ifSet($_SERVER['REQUEST_URI']), serialize($backup), "input", false);
			return null;
		}

	}
	
	/**
	 * Returns data regarding a success transaction. This method is invoked when
	 * a client returns from the non-merchant gateway's web site back to Blesta.
	 *
	 * @param array $get The GET data for this request
	 * @param array $post The POST data for this request
	 * @return array An array of transaction data, may set errors using Input if the data appears invalid
	 *  - client_id The ID of the client that attempted the payment
	 *  - amount The amount of the payment
	 *  - currency The currency of the payment
	 *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
	 *  	- id The ID of the invoice to apply to
	 *  	- amount The amount to apply to the invoice
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- transaction_id The ID returned by the gateway to identify this transaction
	 */
	public function success(array $get, array $post) {

		$backup = $get;

		unset($get[0]);
		unset($get[1]);
		$client_id = $this->ifSet($get['client_id']);
		unset($get['client_id']);
		unset($get['sign_type']);
		$sign = $get['sign'];
		unset($get['sign']);

		if ($this->ifSet($get['is_success']) == 'T' && $this->checkSign($get, $sign) && $this->checkNotify($this->ifSet($get['notify_id']))) {

			$this->log('RETURN: '.$this->ifSet($_SERVER['REQUEST_URI']), serialize($backup), "input", true);

//			if ($this->ifSet($get['trade_status']) == 'WAIT_SELLER_SEND_GOODS') {
//				$this->sendGoods($this->ifSet($get['trade_no']));
//			}

			if ($this->ifSet($get['trade_status']) == 'TRADE_FINISHED') {
				if (!$this->checkTransID($this->ifSet($get['trade_no']), $client_id)) {
					return array(
						'client_id' => $client_id,
						'amount' => $this->ifSet($get['price']),
						'currency' => 'CNY',
						'invoices' => $this->unserializeInvoices($this->ifSet($get['body'])),
						'status' => 'approved',
						'transaction_id' => $this->ifSet($get['trade_no'])
					);
				}
			}

			return array(
				'client_id' => $client_id,
				'amount' => $this->ifSet($get['price']),
				'currency' => 'CNY',
				'invoices' => $this->unserializeInvoices($this->ifSet($get['body'])),
				'status' => 'pending',
				'transaction_id' => $this->ifSet($get['trade_no'])
			);

		} else {
			$this->log('RETURN: '.$this->ifSet($_SERVER['REQUEST_URI']), serialize($backup), "input", false);
			return null;
		}

	}
	
	/**
	 * Captures a previously authorized payment
	 *
	 * @param string $reference_id The reference ID for the previously authorized transaction
	 * @param string $transaction_id The transaction ID for the previously authorized transaction
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function capture($reference_id, $transaction_id, $amount, array $invoice_amounts=null) {
		
		$this->Input->setErrors($this->getCommonError("unsupported"));
	}
	
	/**
	 * Void a payment or authorization
	 *
	 * @param string $reference_id The reference ID for the previously submitted transaction
	 * @param string $transaction_id The transaction ID for the previously submitted transaction
	 * @param string $notes Notes about the void that may be sent to the client by the gateway
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function void($reference_id, $transaction_id, $notes=null) {
		
		$this->Input->setErrors($this->getCommonError("unsupported"));
	}
	
	/**
	 * Refund a payment
	 *
	 * @param string $reference_id The reference ID for the previously submitted transaction
	 * @param string $transaction_id The transaction ID for the previously submitted transaction
	 * @param float $amount The amount to refund this card
	 * @param string $notes Notes about the refund that may be sent to the client by the gateway
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function refund($reference_id, $transaction_id, $amount, $notes=null) {
		
		$this->Input->setErrors($this->getCommonError("unsupported"));
	}

	/**
	 * Serializes an array of invoice info into a string
	 *
	 * @param array A numerically indexed array invoices info including:
	 *  - id The ID of the invoice
	 *  - amount The amount relating to the invoice
	 * @return string A serialized string of invoice info in the format of key1=value1|key2=value2
	 */
	private function serializeInvoices(array $invoices) {
		$str = "";
		foreach ($invoices as $i => $invoice)
			$str .= ($i > 0 ? "|" : "") . $invoice['id'] . "=" . $invoice['amount'];
		return $str;
	}

	/**
	 * Unserializes a string of invoice info into an array
	 *
	 * @param string A serialized string of invoice info in the format of key1=value1|key2=value2
	 * @return array A numerically indexed array invoices info including:
	 *  - id The ID of the invoice
	 *  - amount The amount relating to the invoice
	 */
	private function unserializeInvoices($str) {
		$invoices = array();
		$temp = explode("|", $str);
		foreach ($temp as $pair) {
			$pairs = explode("=", $pair, 2);
			if (count($pairs) != 2)
				continue;
			$invoices[] = array('id' => $pairs[0], 'amount' => $pairs[1]);
		}
		return $invoices;
	}

	/**
	 * Checks whether a transaction exists given the transaction ID from the gateway.
	 *
	 * @param string A transaction id
	 * @param string A client id
	 * @return bool true if the transaction id exists.
	 */
	private function checkTransID($transaction_id, $client_id) {
		Loader::loadModels($this, array("Transactions"));

		$transaction = $this->Transactions->getByTransactionId($transaction_id, $client_id);

		if ($transaction) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Sign a array before send request to alipay
	 *
	 * @param array A array need be signed
	 * @return array A signed array
	 */
	private function sign($fields) {
		$temp = array();
		while (list ($key, $val) = each($fields)) {
			if ($key == "sign" || $key == "sign_type" || $val == "") continue;
			else $temp[$key] = $fields[$key];
		}
		$fields = $temp;
		unset($temp);

		ksort($fields);
		reset($fields);
		$signString = "";
		while (list ($key, $val) = each($fields)) {
			$signString .= $key . "=" . $val . "&";
		}
		$signString = substr($signString, 0, count($signString) - 2);
		$sign = md5($signString . $this->ifSet($this->meta['key']));

		$fields['sign'] = $sign;
		$fields['sign_type'] = "MD5";
		return $fields;
	}

	/**
	 * Check array sign from alipay
	 *
	 * @param array A array need be checked
	 *  - string A sign string
	 * @return bool true if the sign is right
	 */
	private function checkSign($fields, $sign) {

		ksort($fields);
		reset($fields);
		$signString = '';
		while (list ($key, $val) = each($fields)) {
			$signString .= $key . '=' . $val . '&';
		}
		$signString = substr($signString, 0, count($signString) - 2);
		$signed = md5($signString . $this->ifSet($this->meta['key']));

		if ($signed == $sign) return true; else return false;
	}

	/**
	 * Check notify id
	 *
	 * @param string A notify id from alipay
	 * @return bool true if the id is right
	 */
	private function checkNotify($notify_id) {

		$http = $this->Net->create("Http");
		$http->setTimeout(5);

		$fields = array(
			'service' => 'notify_verify',
			'partner' => $this->ifSet($this->meta['pid']),
			'notify_id' => $notify_id,
		);
		$result = $http->post($this->alipay_api, http_build_query($fields));

		if ($result == 'true') return true; else return false;
	}

	/**
	 * Send send_goods_confirm_by_platform request to alipay
	 *
	 * @param string An unique alipay id
	 */
//	private function sendGoods($tid) {
//		$http = $this->Net->create("Http");
//		$http->setTimeout(5);
//		$fields = array(
//			"service" => "send_goods_confirm_by_platform",
//			"partner" => $this->ifSet($this->meta['pid']),
//			"_input_charset" => "utf-8",
//			"trade_no" => $tid,
//			"logistics_name" => "Blesta",
//			"transport_type" => "EXPRESS",
//		);
//		$result = $http->post($this->alipay_api, $this->sign($fields));
//		if (strpos($result, '<is_success>T</is_success>') !== false) {
//			$this->log('send_goods_confirm_by_platform', $result, "output", true);
//		} else {
//			$this->log('send_goods_confirm_by_platform', $result, "output", false);
//		}
//	}

}
?>
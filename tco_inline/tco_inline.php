<?php

defined( '_JEXEC' ) or die( 'Direct Access to ' . basename( __FILE__ ) . ' is not allowed.' );

/**
 *
 * @author     2Checkout
 * @version    $Id: tco_inline.php$
 * @package    VirtueMart
 * @subpackage payment
 * @copyright  Copyright (C) 2015 VirtueMart - All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 */
if ( ! class_exists( 'vmPSPlugin' ) )
{
	require( JPATH_VM_PLUGINS . DS . 'vmpsplugin.php' );
}

if (!class_exists( 'VmConfig' ))
	require(JPATH_ADMINISTRATOR .'/components/com_virtuemart/helpers/config.php');

class plgVmpaymentTco_Inline extends vmPSPlugin {

	private $current_method = null;
	private $order_number = null;
	private $tco_data_helper = null;
	private $tco_ipn_helper = null;
	private $order = null;
	private $config;

	/**
	 * plgVmPaymentTco constructor.
	 *
	 * @param $subject
	 * @param $config
	 */
	function __construct( & $subject, $config ) {

		parent::__construct( $subject, $config );
		$this->config = VmConfig::loadConfig();
		$this->_loggable       = true;
		$this->tableFields     = array_keys( $this->getTableSQLFields() );
		$this->_tablepkey      = 'id';
		$this->_tableId        = 'id';
		$this->tco_data_helper = null;
		$this->tco_ipn_helper  = null;
		$this->order_number    = null;
		$this->order           = null;
		$this->loadTcoLibrary( 'TwoCheckoutInlineLibrary' );
		$this->loadTcoLibrary( 'TwoCheckoutInlineApi' );

		$varsToPush = $this->getVarsToPush();
		if (method_exists($this, 'addVarsToPushCore')) {
			$this->addVarsToPushCore( $varsToPush, 1 );
		}
		$this->setConfigParameterable( $this->_configTableFieldName, $varsToPush );
	}

	/**
	 * @param $method
	 *
	 * @return array
	 */
	function _getTcoDetails( $method ) {
		return array(
			'seller_id'   => $method->tco_seller_id,
			'secret_word' => $method->tco_secret_word,
			'secret_key'  => $method->tco_secret_key,
		);
	}


	/**
	 * @return string
	 */
	public function getVmPluginCreateTableSQL() {

		return $this->createTableSQL( 'Payment 2Checkout Table' );
	}

	/**
	 * @return array
	 */
	function getTableSQLFields() {

		return array(
			'id'                          => 'int(11) unsigned NOT NULL AUTO_INCREMENT ',
			'virtuemart_order_id'         => 'int(11) UNSIGNED DEFAULT NULL',
			'order_number'                => 'char(32) DEFAULT NULL',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
			'payment_name'                => 'char(255) NOT NULL DEFAULT \'\' ',
			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
			'payment_currency'            => 'char(3) ',
			'cost_per_transaction'        => 'decimal(10,2) DEFAULT NULL ',
			'cost_percent_total'          => 'decimal(10,2) DEFAULT NULL ',
			'tax_id'                      => 'smallint(1) DEFAULT NULL',
			'tco_response'                => 'varchar(255)  ',
			'tco_response_order_number'   => 'char(20) DEFAULT NULL'
		);
	}

	/**
	 * @return mixed
	 */
	function tcoGetCurrency() {
		$vendorModel = VmModel::getModel( 'Vendor' );
		$vendorModel->setId( 1 );
		$vendor = $vendorModel->getVendor();
		$vendorModel->addImages( $vendor, 1 );
		$this->getPaymentCurrency( $this->current_method );
		$q  = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . (int) $this->current_method->payment_currency . '" ';
		$db = JFactory::getDBO();
		$db->setQuery( $q );

		return $db->loadResult();
	}


	/**
	 * @param $className
	 */
	function loadTcoLibrary( $className ) {
		if ( ! class_exists( $className ) )
		{
			$filePath = JPATH_SITE . DS . 'plugins' . DS . 'vmpayment' . DS . 'tco_inline' . DS . 'tco_inline' . DS . 'library' . DS . strtolower( $className ) . '.php';
			if ( file_exists( $filePath ) )
			{
				require $filePath;

				return;
			}
			else
			{
				vmError( 'Programming error: trying to load:' . $filePath );
			}
		}
	}

	/**
	 * @param $className
	 */
	function loadTcoHelper( $className ) {
		if ( ! class_exists( $className ) )
		{
			$filePath = JPATH_SITE . DS . 'plugins' . DS . 'vmpayment' . DS . 'tco_inline' . DS . 'tco_inline' . DS . 'helpers' . DS . strtolower( $className ) . '.php';
			if ( file_exists( $filePath ) )
			{
				require $filePath;

				return;
			}
			else
			{
				vmError( 'Programming error: trying to load:' . $filePath );
			}
		}
	}

	/**
	 * @param VirtueMartCart $cart
	 * @param                $method
	 * @param                $cartPrices
	 *
	 * @return float
	 *
	 * @since version
	 */
	function getCosts(VirtueMartCart $cart, $method, $cartPrices)
	{
		if (preg_match('/%$/', $method->cost_percent_total)) {
			$costPercentTotal = substr($method->cost_percent_total, 0, -1);
		} else {
			$costPercentTotal = $method->cost_percent_total;
		}

		return ((float)$method->cost_per_transaction + ($cartPrices['salesPrice'] * (float) $costPercentTotal * 0.01));
	}

	/**
	 * @inheritdoc
	 */
	protected function checkConditions( $cart, $method, $cartPrices ) {

		$address = ( ( $cart->ST == 0 ) ? $cart->BT : $cart->ST );

		$amount     = $cartPrices['salesPrice'];
		$amountCond = ( $amount >= $method->min_amount AND $amount <= $method->max_amount
		                OR
		                ( $method->min_amount <= $amount AND ( $method->max_amount == 0 ) ) );

		$countries = array();
		if ( ! empty( $method->countries ) )
		{
			if ( ! is_array( $method->countries ) )
			{
				$countries[0] = $method->countries;
			}
			else
			{
				$countries = $method->countries;
			}
		}

		if ( ! is_array( $address ) )
		{
			$address                          = array();
			$address['virtuemart_country_id'] = 0;
		}

		if ( ! isset( $address['virtuemart_country_id'] ) )
		{
			$address['virtuemart_country_id'] = 0;
		}
		if ( in_array( $address['virtuemart_country_id'], $countries ) || count( $countries ) == 0 )
		{
			if ( $amountCond )
			{
				return true;
			}
		}
	}

	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 * @param VirtueMartCart $cart
	 * @param int $activeMethod
	 * @param array $cart_prices
	 * @return bool
	 */
	protected function checkMethodConditions($cart, $activeMethod, $cart_prices) {

		//Check method publication start
		if ($activeMethod->publishup) {
			$nowDate = JFactory::getDate();
			$publish_up = JFactory::getDate($activeMethod->publishup);
			if ($publish_up->toUnix() > $nowDate->toUnix()) {
				return FALSE;
			}
		}
		if ($activeMethod->publishdown) {
			$nowDate = JFactory::getDate();
			$publish_down = JFactory::getDate($activeMethod->publishdown);
			if ($publish_down->toUnix() <= $nowDate->toUnix()) {
				return FALSE;
			}
		}

		return parent::checkConditions($cart, $activeMethod, $cart_prices);

	}

	/**
	 * @param $cart
	 * @param $order
	 *
	 * @throws Exception
	 * @return null|true
	 */
	function plgVmConfirmedOrder( $cart, $order ) {
		if ( ! ( $this->current_method = $this->getVmPluginMethod( $order['details']['BT']->virtuemart_paymentmethod_id ) ) )
		{
			return null; // Another method was selected, do nothing
		}
		if ( ! $this->selectedThisElement( $this->current_method->payment_element ) )
		{
			return false;
		}

		$tcoDetails = $this->_getTcoDetails( $this->current_method );

		if ( empty( $tcoDetails['seller_id'] ) )
		{
			vmInfo( JText::_( 'VMPAYMENT_TCO_SELLER_ID_NOT_SET' ) );

			return false;
		}

		$this->loadTcoHelper('TcoData');
		$this->tco_data_helper = new TcoData();

		$this->order_number = $order['details']['BT']->order_number;

		if ( ! class_exists( 'VirtueMartModelOrders' ) )
		{
			require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
		}
		if ( ! class_exists( 'VirtueMartModelCurrency' ) )
		{
			require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php' );
		}

		$address = ( ( isset( $order['details']['BT'] ) ) ? $order['details']['BT'] : $order['details']['ST'] );

		if ( ! class_exists( 'TableVendors' ) )
		{
			require( JPATH_VM_ADMINISTRATOR . DS . 'table' . DS . 'vendors.php' );
		}

		$orderReference = new TwoCheckoutInlineLibrary();
		$currencyCode   = $this->tcoGetCurrency();
		$postVariables  = $orderReference->getFormFields( $order, $address, $tcoDetails, $this->current_method, $currencyCode );
		$pluginName     = $this->renderPluginName( $this->current_method, $order );
		$dbValues       = $orderReference->storeInternalDataParams( $order, $cart, $pluginName, $this->current_method);
		$this->storePSPluginInternalData( $dbValues );
		echo json_encode($postVariables);
		exit();
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	function plgVmOnPaymentNotification() {
		if ( strtoupper( $_SERVER['REQUEST_METHOD'] ) !== 'POST' )
		{
			return false;
		}

		$params = vRequest::getRequest();
		unset( $params['view'] );
		unset( $params['task'] );
		unset( $params['tmpl'] );
		unset( $params['option'] );
		unset( $params['Itemid'] );

		if ( ! class_exists( 'VirtueMartModelOrders' ) )
		{
			require( VMPATH_ADMIN . DS . 'models' . DS . 'orders.php' );
		}

		if ( ! ( $orderNumber = VirtueMartModelOrders::getOrderIdByOrderNumber( $params['REFNOEXT'] ) ) )
		{
			throw new Exception( sprintf( 'Cannot identify virtue mart order: "%s".',
				$this->params['REFNOEXT'] ) );
		}

		if ( ! ( $payment = $this->getDataByOrderId( $orderNumber ) ) )
		{
			if (JDEBUG) {
				JLog::add( 'Incorrect payment method. Moving on. [Inline]', JLog::DEBUG, 'IPN-NOTIF' );
			}
			return false;
		}

		if ( ! $payment )
		{
			throw new Exception( sprintf( 'Payment not found for order: "%s".',
				$this->params['REFNOEXT'] ) );
		}

		$currentMethod = $this->getVmPluginMethod( $payment->virtuemart_paymentmethod_id );
		if ( ! $this->selectedThisElement( $currentMethod->payment_element ) )
		{
			return false;
		}
		$tcoDetails = $this->_getTcoDetails( $currentMethod );
		$secretKey  = $tcoDetails['secret_key'];

		$this->loadTcoHelper('TcoIpnInline');
		$this->tco_ipn_helper = new TcoIpnInline( $params, $orderNumber, $secretKey, $currentMethod );


		if ( ! $this->tco_ipn_helper->indexAction() )
		{
			return false;
		}

		return true;
	}

	function plgVmOnPaymentResponseReceived(&$html)
	{

		$virtuemartPaymentMethodId = JRequest::getInt('pm', 0);
		$orderNumber = JRequest::getVar('o_id', 0);
		//$status = JRequest::getVar('status', null);
		$refno = JRequest::getVar('refno', null);
		$app = JFactory::getApplication();

		if (!($method = $this->getVmPluginMethod($virtuemartPaymentMethodId))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		if (!class_exists('VirtueMartCart')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
		}
		if (!class_exists('shopFunctionsF')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		}
		if (!class_exists('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		if (!$refno || empty($refno)) {
			return $app->redirect(JRoute::_('index.php/cart'));
		}

		$api = new TwoCheckoutInlineApi();
		$api->setSellerId($method->tco_seller_id);
		$api->setSecretKey($method->tco_secret_key);
		$api_response = $api->call( '/orders/' . $refno . '/', [], 'GET' );
		if(!empty($api_response['Status']) && isset($api_response['Status']) ){
			if ( in_array( $api_response['Status'], [ 'AUTHRECEIVED', 'COMPLETE' ] ) )
			{
				$orderModel        = VmModel::getModel( 'orders' );
				$virtuemartOrderId = $orderModel->getOrderIdByOrderNumber( $orderNumber );
				$order             = $orderModel->getOrder( $virtuemartOrderId );
				$orderModel->updateStatusForOneOrder(
					$virtuemartOrderId,
					[ 'order_status' => $method->status_success ],
					false
				);
				$orderReference = new TwoCheckoutInlineLibrary();
				$dbValues = $orderReference->updateInternalDataParams( $this->_tablename, $virtuemartOrderId, $api_response['RefNo'] );
				if (!empty($dbValues)) {
					$this->storePSPluginInternalData($dbValues, 'virtuemart_order_id', TRUE);
				}
			}
		}
		$cart = VirtueMartCart::getCart();
		$cart->emptyCart();
		$link = JRoute::_("index.php?option=com_virtuemart&view=orders&layout=details&order_number=" . $order['details']['BT']->order_number . "&order_pass=" . $order['details']['BT']->order_pass,
			false);

		return $app->redirect($link);
	}

	/**
	 * @param $virtuemart_paymentmethod_id
	 * @param $paymentCurrencyId
	 *
	 * @return bool|null
	 */
	function plgVmgetPaymentCurrency( $virtuemart_paymentmethod_id, &$paymentCurrencyId ) {
		if ( ! ( $method = $this->getVmPluginMethod( $virtuemart_paymentmethod_id ) ) )
		{
			return null; // Another method was selected, do nothing
		}
		if ( ! $this->selectedThisElement( $method->payment_element ) )
		{
			return false;
		}
		$this->getPaymentCurrency( $method );
		$paymentCurrencyId = $method->payment_currency;
	}

	/**
	 * @param $virtuemartOrderId
	 * @param $paymentMethodId
	 *
	 * @return string|null
	 */
	function plgVmOnShowOrderBEPayment( $virtuemartOrderId, $paymentMethodId ) {
		if ( ! $this->selectedThisByMethodId( $paymentMethodId ) )
		{
			return null; // Another method was selected, do nothing
		}

		if ( ! ( $paymentTable = $this->getDataByOrderId( $virtuemartOrderId ) ) )
		{
			return null;
		}
		VmConfig::loadJLang( 'com_virtuemart' );
		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE();
		$html .= $this->getHtmlRowBE( 'COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name );
		$html .= $this->getHtmlRowBE( 'TCO_ORDER_NUMBER', $paymentTable->tco_response_order_number );
		$html .= '</table>' . "\n";

		return $html;
	}

	function plgVmOnStoreInstallPaymentPluginTable( $jpluginId ) {
		return $this->onStoreInstallPluginTable( $jpluginId );
	}

	public function plgVmOnSelectCheckPayment( VirtueMartCart $cart ) {
		return $this->OnSelectCheck( $cart );
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 */
	public function plgVmDisplayListFEPayment( VirtueMartCart $cart, $selected = 0, &$htmlIn ) {

		if ($this->getPluginMethods($cart->vendorId) === 0) {
			if (empty($this->_name)) {
				$app = JFactory::getApplication();
				$app->enqueueMessage(vmText::_('COM_VIRTUEMART_CART_NO_' . strtoupper($this->_psType)));
				return false;
			} else {
				return false;
			}
		}
		vmJsApi::addJScript('TwoCoInlineCart', 'https://secure.2checkout.com/checkout/client/twoCoInlineCart.js', false);
		vmJsApi::addJScript('/plugins/vmpayment/tco_inline/tco_inline/assets/js/inline.js');
		foreach ($this->methods as $currentMethod) {
			if ($this->checkConditions($cart, $currentMethod, $cart->cartPrices)) {
				$this->current_method = $currentMethod;

				$virtuemart_paymentmethod_id = $this->current_method->virtuemart_paymentmethod_id;
				$seller_id = $this->current_method->tco_seller_id;
				$methodSalesPrice = $this->setCartPrices($cart, $cartPrices, $this->current_method);
				$htmlForm = $this->getPluginHtml($currentMethod, $selected, $methodSalesPrice);
				ob_start();
				require_once JPATH_PLUGINS . '/vmpayment/tco_inline/tco_inline/views/form.php';
				$htmlForm .= ob_get_contents();    // get the contents from the buffer
				ob_end_clean();

				$htmlIn[] = [$htmlForm];
			}
		}

		return true;
	}

	public function plgVmonSelectedCalculatePricePayment( VirtueMartCart $cart, array &$cartPrices, &$cartPricesName ) {
		$cart->automaticSelectedPayment=false;
		$cart->setCartIntoSession();

		return $this->onSelectedCalculatePrice( $cart, $cartPrices, $cartPricesName );
	}

	function plgVmOnCheckAutomaticSelectedPayment( VirtueMartCart $cart, array $cartPrices = array() ) {
		return $this->onCheckAutomaticSelected( $cart, $cartPrices );
	}

	public function plgVmOnShowOrderFEPayment( $virtuemartOrderId, $virtuemartPaymentMethodId, &$paymentName ) {
		$this->onShowOrderFE( $virtuemartOrderId, $virtuemartPaymentMethodId, $paymentName );
	}

	function plgVmonShowOrderPrintPayment( $orderNumber, $methodId ) {
		return $this->onShowOrderPrint( $orderNumber, $methodId );
	}

	function plgVmDeclarePluginParamsPayment( $name, $id, &$data ) {
		return $this->declarePluginParams( 'payment', $name, $id, $data );
	}

	function plgVmDeclarePluginParamsPaymentVM3( &$data ) {
		return $this->declarePluginParams( 'payment', $data );
	}

	function plgVmSetOnTablePluginParamsPayment( $name, $id, &$table ) {
		return $this->setOnTablePluginParams( $name, $id, $table );
	}

	function plgVmOnSelfCallFE( $type, $name, &$render ) {
		$id = vRequest::getInt( 'virtuemart_paymentmethod_id', 0 );
		if ( ! ( $method = $this->getVmPluginMethod( $id ) ) ) {
			return null;
		}
		// Another method was selected, do nothing
		if ( ! $this->selectedThisElement( $method->payment_element ) ) {
			return false;
		}
		$transactionId = vRequest::get( 'transactionId' );
	}

}

// No closing tag

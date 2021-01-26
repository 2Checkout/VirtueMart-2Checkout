<?php

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

/**
 *
 * @author     2Checkout
 * @version    $Id: twocheckout.php$
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
if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}


use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

class plgVmPaymentTwocheckout extends vmPSPlugin
{

    private $current_method  = null;
    private $order_number    = null;
    private $tco_data_helper = null;
    private $tco_ipn_helper  = null;
    private $order           = null;

    /**
     * plgVmPaymentTwocheckout constructor.
     * @param $subject
     * @param $config
     */
    function __construct(&$subject, $config)
    {

        parent::__construct($subject, $config);
        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $this->tco_data_helper = null;
        $this->tco_ipn_helper = null;
        $this->order_number = null;
        $this->order = null;
        $this->loadLibraries();

        $varsToPush = $this->getVarsToPush();
        if (method_exists($this, 'addVarsToPushCore')) {
            $this->addVarsToPushCore( $varsToPush, 1 );
        }
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    /**
     *
     * @return string
     *
     * @since version
     */
    public function getVmPluginCreateTableSQL()
    {

        return $this->createTableSQL('Payment 2Checkout Table');
    }


    function plgVmOnPaymentResponseReceived(&$html)
    {

        $virtuemartPaymentMethodId = JRequest::getInt('pm', 0);
        $virtuemartOrderId = JRequest::getVar('order_id', 0);
        $status = JRequest::getVar('status', null);
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
        if (!$status) {
            exit('invalid request!');
        }
        if ($status !== 'success') { // if response is cancel redirect to cart
            return $app->redirect(JRoute::_('index.php/cart'));
        }

	    $orderModel = VmModel::getModel( 'orders' );
	    $order      = $orderModel->getOrder( $virtuemartOrderId );
	    $orderModel->updateStatusForOneOrder(
		    $virtuemartOrderId,
		    [ 'order_status' => $method->status_success ],
		    false
	    );
        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();
        $link = JRoute::_("index.php?option=com_virtuemart&view=orders&layout=details&order_number=" . $order['details']['BT']->order_number . "&order_pass=" . $order['details']['BT']->order_pass,
            false);

        return $app->redirect($link);
    }

    function plgVmOnUserPaymentCancel()
    {

        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        $order_number = JRequest::getVar('on');
        if (!$order_number) {
            return false;
        }
        $db = JFactory::getDBO();
        $query = 'SELECT ' . $this->_tablename . '.`virtuemart_order_id` FROM ' . $this->_tablename . " WHERE  `order_number`= '" . $order_number . "'";

        $db->setQuery($query);
        $virtuemart_order_id = $db->loadResult();

        if (!$virtuemart_order_id) {
            return null;
        }
        $this->handlePaymentUserCancel($virtuemart_order_id);

        return true;
    }


    /**
     *
     * @return array|false|string[]
     *
     * @since version
     */
    function getTableSQLFields()
    {

        return [
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
            'response'                    => 'text DEFAULT NULL',
            'response_order_number'       => 'char(20) DEFAULT NULL'
        ];
    }

    /**
     * @param $cart
     * @param $order
     *
     * @return false|null
     *
     * @throws Exception
     * @since version
     */
    function plgVmConfirmedOrder($cart, $order)
    {

        if (!($this->current_method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($this->current_method->payment_element)) {
            return false;
        }
        if (empty($this->current_method->twocheckout_seller_id)) {
            vmInfo(JText::_('VMPAYMENT_TWOCHECKOUT_SELLER_ID_NOT_SET'));

            return false;
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        if (!class_exists('VirtueMartModelCurrency')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
        }

        if (!class_exists('TableVendors')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'table' . DS . 'vendors.php');
        }

        $this->_debug = $this->current_method->debug;
        $this->order_number = $order['details']['BT']->order_number;
        $address = ((isset($order['details']['BT'])) ? $order['details']['BT'] : $order['details']['ST']);
        $lang = JFactory::getLanguage();
        // default json response
        $jsonResponse = [
            'status'   => false,
            'messages' => 'The payment could not be processed for order ' . $this->order_number . '! Please try again or contact us.',
            'redirect' => null
        ];

        try {
            $orderParams = [
                'Currency'          => $this->tcoGetCurrency(),
                'Language'          => substr($lang->getTag(), 0, 2),
                'Country'           => ShopFunctions::getCountryByID($address->virtuemart_country_id, 'country_2_code'),
                'CustomerIP'        => $this->getCustomerIp(),
                'Source'            => 'VIRTUEMART_3_8',
                'ExternalReference' => $this->order_number,
                'Items'             => $this->getItem($order['details']['BT']->order_total),
                'BillingDetails'    => $this->getBillingDetails($address),
                'PaymentDetails'    => $this->getPaymentDetails($_POST['ess_token'], $order)
            ];

            $api = new TwoCheckoutApi();
            $api->setSellerId($this->current_method->twocheckout_seller_id);
            $api->setSecretKey($this->current_method->twocheckout_secret_key);
            $orderModel = VmModel::getModel('orders');

            $apiResponse = $api->call('/orders', $orderParams);
            if (!$apiResponse) {
                $error = 'The payment could not be processed for order ' . $this->order_number . '! Please try again or contact us.';
                $jsonResponse = ['status' => false, 'messages' => $error, 'redirect' => null];
            } else {
                if (isset($apiResponse['error_code'])) {
                    $jsonResponse = ['status' => false, 'messages' => $apiResponse['message'], 'redirect' => null];
                } elseif (isset($apiResponse['Errors'])) { // errors that must be shown to the client
                    $error = '';
                    foreach ($apiResponse['Errors'] as $key => $value) {
                        $error .= $value . PHP_EOL;
                    }

                    $jsonResponse = ['status' => false, 'messages' => $error, 'redirect' => null];
                } else {
                    $has3ds = false;
                    if (isset($apiResponse['PaymentDetails']['PaymentMethod']['Authorize3DS'])) {
                        $has3ds = $this->hasAuthorize3DS($apiResponse['PaymentDetails']['PaymentMethod']['Authorize3DS']);
                    }
                   if ($has3ds) {
                        $orderModel->updateStatusForOneOrder(
                            $order['details']['BT']->virtuemart_order_id,
                            ['order_status' => $this->current_method->status_pending],
                            false
                        );
                        $jsonResponse = ['status' => true, 'messages' => 'Has 3ds redirect', 'redirect' => $has3ds];
                    } else {
                        $jsonResponse = [
                            'status'   => true,
                            'messages' => 'Order placed',
                            'redirect' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&status=success&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&order_id=' . $order['details']['BT']->virtuemart_order_id)
                        ];
                    }
                    $library = new TwocheckoutLibrary();
                    $dbValues = $library->storeInternalDataParams(
                        $order,
                        $cart,
                        $this->renderPluginName($this->current_method, $order),
                        $this->current_method,
                        $apiResponse
                    );
                    $this->storePSPluginInternalData($dbValues);
                }
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        echo json_encode($jsonResponse);
        exit();
    }

    /**
     *
     * @return bool
     *
     * @throws Exception
     * @since version
     */
    function plgVmOnPaymentNotification()
    {
        if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
            return false;
        }

        $params = vRequest::getRequest();
        unset($params['view']);
        unset($params['task']);
        unset($params['tmpl']);
        unset($params['option']);
        unset($params['Itemid']);

        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }

        if (!($orderNumber = VirtueMartModelOrders::getOrderIdByOrderNumber(trim($params['REFNOEXT'])))) {

            throw new Exception(sprintf('Cannot identify virtue mart order: "%s".',
                $params['REFNOEXT']));
        }

        if (!($payment = $this->getDataByOrderId($orderNumber))) {
	        if (JDEBUG) {
		        JLog::add( 'Incorrect payment method. Moving on. [2payJS]', JLog::DEBUG, 'IPN-NOTIF' );
	        }
	        return false;
        }

        if (!$payment) {
            throw new Exception(sprintf('Payment not found for order: "%s".',
                $params['REFNOEXT']));
        }

        $currentMethod = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);
        if (!$this->selectedThisElement($currentMethod->payment_element)) {
            return false;
        }
        $secretKey = $this->current_method->twocheckout_secret_key;

        if (!class_exists('TcoIpn')) {
            require VMPATH_PLUGINS . '/vmpayment/twocheckout/twocheckout/helper/tcoipn.php';
        }

        $this->tco_ipn_helper = new TcoIpn($params, $orderNumber, $currentMethod);


        if (!$this->tco_ipn_helper->indexAction($params, $orderNumber, $secretKey)) {
            return false;
        }

        return true;
    }

    /**
     *
     * @return mixed|null
     *
     * @since version
     */
    function tcoGetCurrency()
    {
        $vendorModel = VmModel::getModel('Vendor');
        $vendorModel->setId(1);
        $vendor = $vendorModel->getVendor();
        $vendorModel->addImages($vendor, 1);
        $this->getPaymentCurrency($this->current_method);
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . (int)$this->current_method->payment_currency . '" ';
        return JFactory::getDBO()->setQuery($q)->loadResult();
    }

    /**
     *
     *
     * @since version
     */
    function loadLibraries()
    {
        if (!class_exists('TwocheckoutLibrary')) {
            require JPATH_SITE . DS . 'plugins' . DS . 'vmpayment' . DS . 'twocheckout' . DS . 'twocheckout' . DS . 'library' . DS . 'TwocheckoutLibrary.php';
        }

        if (!class_exists('TwoCheckoutApi')) {
            require(VMPATH_PLUGINS . DS . 'vmpayment' . DS . 'twocheckout' . DS . 'twocheckout' . DS . 'library' . DS . 'TwoCheckoutApi.php');
        }
    }

    /**
     * @param $virtuemart_paymentmethod_id
     * @param $paymentCurrencyId
     *
     * @return false|null
     *
     * @since version
     */
    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        $this->getPaymentCurrency($method);
        $paymentCurrencyId = $method->payment_currency;
    }

    /**
     * @param $virtuemartOrderId
     * @param $paymentMethodId
     *
     * @return string|null
     *
     * @since version
     */
    function plgVmOnShowOrderBEPayment($virtuemartOrderId, $paymentMethodId)
    {
        if (!$this->selectedThisByMethodId($paymentMethodId)) {
            return null; // Another method was selected, do nothing
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemartOrderId))) {
            return null;
        }
        VmConfig::loadJLang('com_virtuemart');
        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('TCO_ORDER_NUMBER', $paymentTable->response_order_number);
        $html .= '</table>' . "\n";

        return $html;
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

        return ($method->cost_per_transaction + ($cartPrices['salesPrice'] * $costPercentTotal * 0.01));
    }

    /**
     * @param VirtueMartCart $cart
     * @param int            $method
     * @param array          $cartPrices
     *
     * @return bool
     *
     * @since version
     */
    protected function checkConditions($cart, $method, $cartPrices)
    {
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amount = $cartPrices['salesPrice'];
        $amountCond = ($amount >= $method->min_amount and $amount <= $method->max_amount
            or
            ($method->min_amount <= $amount and ($method->max_amount == 0)));

        $countries = [];
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        if (!is_array($address)) {
            $address = [];
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            if ($amountCond) {
                return true;
            }
        }

        return false;
    }

    function plgVmOnStoreInstallPaymentPluginTable($jpluginId)
    {
        return $this->onStoreInstallPluginTable($jpluginId);
    }

    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart)
    {
        return $this->OnSelectCheck($cart);
    }

    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        $document = JFactory::getDocument(); 
        $css_path =  JRoute::_(JURI::base()."plugins/vmpayment/twocheckout/twocheckout/assets/css/twocheckout.css"); 
        $document->addCustomTag('<link rel="stylesheet" type="text/css" href='.$css_path.' media="all">');
        $document->addScript('https://2pay-js.2checkout.com/v1/2pay.js');
        vmJsApi::addJScript('twocheckout_api', '/plugins/vmpayment/twocheckout/twocheckout/assets/js/twocheckout.js');

        if (isset($this->methods)) {
            foreach ($this->methods as $currentMethod) {
                if ($this->checkConditions($cart, $currentMethod, $cart->cartPrices)) {
                    $this->current_method = $currentMethod;

                    $virtuemart_paymentmethod_id = $this->current_method->virtuemart_paymentmethod_id;
                    $seller_id = $this->current_method->twocheckout_seller_id;
                    $default_style = $this->current_method->default_style;
                    $style = preg_replace('/\v(?:[\v\h]+)/', '', $this->current_method->custom_style);
                    $style = preg_replace("/\s+/", "", $style);
                    $methodSalesPrice = $this->setCartPrices($cart, $cart->cartPrices, $this->current_method);
                    $htmlForm = $this->getPluginHtml($currentMethod, $selected, $methodSalesPrice);
                    if ($this->current_method->sandbox) {
                        $htmlForm .= '<span class="red" title="' . vmText::_('VMPAYMENT_TWOCHECKOUT_SANDBOX_DESC') . '">(test order)</span>';
                    }
                    if ($this->current_method->sandbox) {
                        $htmlForm .= '<p class="vmpayment_description">' . $this->current_method->payment_desc . '</p>';
                    }
                    ob_start();
                    require_once JPATH_PLUGINS . '/vmpayment/twocheckout/twocheckout/views/form.php';
                    $htmlForm .= ob_get_contents();    // get the contents from the buffer
                    ob_end_clean();

                    $htmlIn[] = [$htmlForm];
                }
            }
        }

        return true;
    }

    /**
     * @param $address
     *
     * @return array
     *
     * @since version
     */
    private function getBillingDetails($address)
    {
        $countryCode = ShopFunctions::getCountryByID($address->virtuemart_country_id, 'country_2_code');
        $newAddress = [
            'Address1'    => $address->address_1,
            'City'        => $address->city,
            'State'       => isset($address->virtuemart_state_id) ? ShopFunctions::getStateByID($address->virtuemart_state_id) : 'XX',
            'CountryCode' => $countryCode ? $countryCode : 'XX',
            'Email'       => $address->email,
            'FirstName'   => $address->first_name,
            'LastName'    => $address->last_name,
            'Phone'       => trim($address->phone_1 . ' ' . $address->phone_2),
            'Zip'         => $address->zip,
            'Company'     => $address->company
        ];

        if ($address->address_2) {
            $newAddress['Address2'] = $address->address_2;
        }

        return $newAddress;
    }

    /**
     * for safety reasons we only send one Item with the grand total and the Cart_id as ProductName (identifier)
     * sending products order as ONE we dont have to calculate the total fee of the order (product price, tax, discounts etc)
     * @param float $total
     *
     * @return mixed
     *
     * @since version
     */
    private function getItem($total)
    {
        $config = JFactory::getConfig();

        $items[] = [
            'Code'             => null,
            'Quantity'         => 1,
            'Name'             => $config->get('sitename'),
            'Description'      => 'N/A',
            'RecurringOptions' => null,
            'IsDynamic'        => true,
            'Tangible'         => false,
            'PurchaseType'     => 'PRODUCT',
            'Price'            => [
                'Amount' => number_format($total, 2, '.', ''),
                'Type'   => 'CUSTOM'
            ]
        ];

        return $items;
    }

    /**
     * @param string $token
     * @param object $order
     *
     * @return array
     *
     * @since version
     */
    private function getPaymentDetails($token, $order)
    {
        $paymentId = $order['details']['BT']->virtuemart_paymentmethod_id;
        $orderId = $order['details']['BT']->virtuemart_order_id;

        return [
            'Type'          => $this->current_method->sandbox ? 'TEST' : 'EES_TOKEN_PAYMENT',
            'Currency'      => $this->tcoGetCurrency(),
            'CustomerIP'    => $this->getCustomerIp(),
            'PaymentMethod' => [
                'EesToken'           => $token,
                'Vendor3DSReturnURL' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&status=success&pm=' . $paymentId . '&order_id=' . $orderId),
                'Vendor3DSCancelURL' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&status=cancel&pm=' . $paymentId . '&order_id=' . $orderId),
            ]
        ];
    }

    /**
     * @param $has3ds
     *
     * @return false|string
     *
     * @since version
     */
    private function hasAuthorize3DS($has3ds)
    {
        if (isset($has3ds) && isset($has3ds['Href']) && !empty($has3ds['Href'])) {

            return $has3ds['Href'] . '?avng8apitoken=' . $has3ds['Params']['avng8apitoken'];
        }

        return false;
    }

    private function getCustomerIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            //ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //ip pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return $ip;
        }

        return '1.0.0.1';
    }

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cartPrices, &$cartPricesName)
    {
	    $cart->automaticSelectedPayment=false;
	    $cart->setCartIntoSession();

        return $this->onSelectedCalculatePrice($cart, $cartPrices, $cartPricesName);
    }

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cartPrices = [])
    {
        return $this->onCheckAutomaticSelected($cart, $cartPrices);
    }

    public function plgVmOnShowOrderFEPayment($virtuemartOrderId, $virtuemartPaymentMethodId, &$paymentName)
    {
        $this->onShowOrderFE($virtuemartOrderId, $virtuemartPaymentMethodId, $paymentName);
    }

    function plgVmonShowOrderPrintPayment($orderNumber, $methodId)
    {
        return $this->onShowOrderPrint($orderNumber, $methodId);
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data)
    {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

}

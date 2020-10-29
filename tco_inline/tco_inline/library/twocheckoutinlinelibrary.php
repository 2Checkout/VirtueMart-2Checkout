<?php

if ( ! class_exists( 'TcoData' ) ) {
	  require JPATH_SITE . DS . 'plugins' . DS .'vmpayment'. DS .'tco_inline'. DS .'tco_inline'. DS .'helpers'. DS .'tcodata.php';
}


class TwoCheckoutInlineLibrary {

	private $SRC = 'VIRTUEMART_3_8';
	private $helper;

	public function __construct() {
		$this->helper = new TcoData();
	}

	/**
	 * @return string
	 */
	public function getOrderPlaceRedirectUrl( $order ) {
		return JROUTE::_( JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . "&o_id={$order['details']['BT']->order_number}" );
	}

	private function getItem( $order, $price ) {
		$orderNumber = $order['details']['BT']->order_number;
		$items[]     = [
			'type'     => 'PRODUCT',
			'name'     => 'Cart_' . $orderNumber,
			'price'    => $price,
			'tangible' => 0,
			'quantity' => 1,
		];

		return $items;
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	private function getBillingDetails( $order, $address ) {
		return [
			'name'     => $address->first_name . ' ' . $address->last_name,
			'phone'    => $address->phone_1,
			'country'  => substr( ShopFunctions::getCountryByID( $address->virtuemart_country_id, 'country_3_code' ), 0,
				2 ),
			'state'    => isset( $address->virtuemart_state_id ) ? ShopFunctions::getStateByID( $address->virtuemart_state_id ) : 'XX',
			'email'    => $order['details']['BT']->email,
			'address'  => $address->address_1,
			'address2' => $address->address_2,
			'city'     => $address->city,
			'zip'      => $address->zip,
			'company-name' => $address->company,
		];
	}


	/**
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	private function getShippingSetails( $order, $address ) {
		return [
			'ship-name'     => $address->first_name . ' ' . $address->last_name,
			'ship-country'  => substr( ShopFunctions::getCountryByID( $address->virtuemart_country_id,
				'country_3_code' ), 0, 2 ),
			'ship-state'    => isset( $address->virtuemart_state_id ) ? ShopFunctions::getStateByID( $address->virtuemart_state_id ) : 'XX',
			'ship-email'    => $order['details']['BT']->email,
			//same as billing
			'ship-address'  => $address->address_1,
			'ship-address2' => ! empty( $address->address_2 ) ? $address->address_2 : '',
			'ship-city'     => $address->city,
		];
	}

	/**
	 * @param $order
	 * @param $address
	 * @param $tcoDetails
	 * @param $method
	 * @param $currency
	 *
	 * @return array
	 */
	public function getFormFields( $order, $address, $tcoDetails, $method, $currency ) {
		$orderParams = [];
		try {
			$paymentCurrency        = CurrencyDisplay::getInstance( $method->payment_currency );
			$totalInPaymentCurrency = round( $paymentCurrency->convertCurrencyTo( $method->payment_currency,
				$order['details']['BT']->order_total, false ), 2 );
			$orderNumber            = $order['details']['BT']->order_number;

			$orderParams = [
				'currency'         => strtolower( $currency ),
				'language'         => strtolower( substr( JFactory::getLanguage()->getName(), 0, 2 ) ),
				'country'          => substr( ShopFunctions::getCountryByID( $address->virtuemart_country_id,
					'country_3_code' ), 0, 2 ),
				'products'         => $this->getItem( $order, $totalInPaymentCurrency ),
				'return-method'    => [
					'type' => 'redirect',
					'url'  => $this->getOrderPlaceRedirectUrl( $order )
				],
				'test'             => ( $method->sandbox == 1 ) ? '1' : '0',
				'order-ext-ref'    => $orderNumber,
				'customer-ext-ref' => $order['details']['BT']->email,
				'src'              => $this->SRC,
				'mode'             => 'DYNAMIC',
				'dynamic'          => '1',
				'merchant'         => $tcoDetails['seller_id'],
			];

			$orderParams['billing_address']  = $this->getBillingDetails( $order, $address );
			$orderParams['shipping_address'] = $this->getShippingSetails( $order, $address );

			$orderParams['signature'] = $this->helper->getInlineSignature(
				$tcoDetails['seller_id'],
				$tcoDetails['secret_word'],
				$orderParams );

		} catch ( Exception $e ) {
			vmError (vmText::sprintf ('VMPAYMENT_TCO_ERROR_INLINE_DATA', $e->getMessage()));
		}
		return $orderParams;
	}

	/**
	 * @param $order
	 * @param $cart
	 * @param $pluginName
	 *
	 * @return mixed
	 */
	public function storeInternalDataParams( $order, $cart, $pluginName, $current_method
	) {
		$session                = JFactory::getSession();
		$returnContext          = $session->getId();
		$paymentCurrency        = CurrencyDisplay::getInstance( $current_method->payment_currency );
		$totalInPaymentCurrency = round( $paymentCurrency->convertCurrencyTo( $current_method->payment_currency,
			$order['details']['BT']->order_total, false ), 2 );

		$dbValues['order_number']                = $order['details']['BT']->order_number;
		$dbValues['order_pass']                = $order['details']['BT']->order_pass;
		$dbValues['payment_name']                = $pluginName;
		$dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
		$dbValues['tco_custom']                  = $returnContext;
		$dbValues['cost_per_transaction']        = $current_method->cost_per_transaction;
		$dbValues['cost_percent_total']          = $current_method->cost_percent_total;
		$dbValues['payment_currency']            = $current_method->payment_currency;
		$dbValues['payment_order_total']         = $totalInPaymentCurrency;
		$dbValues['tax_id']                      = $current_method->tax_id;

		return $dbValues;
	}
}

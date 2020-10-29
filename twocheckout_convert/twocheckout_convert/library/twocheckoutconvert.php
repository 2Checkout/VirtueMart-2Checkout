<?php

class TwoCheckoutConvert {

	/**
	 * @return string
	 */
	public function getOrderPlaceRedirectUrl($order)
	{
		return JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id."&o_id={$order['details']['BT']->order_number}");
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
	public function getFormFields($order, $address, $tcoDetails, $method, $currency)
	{
		$orderNumber = $order['details']['BT']->order_number;
		$buyLinkParams = [];

		$buyLinkParams['name'] = $address->first_name . ' ' . $address->last_name;
		$buyLinkParams['phone'] = $address->phone_1;
		$buyLinkParams['country'] = substr(ShopFunctions::getCountryByID($address->virtuemart_country_id, 'country_3_code'), 0, 2);
		$buyLinkParams['state'] = isset($address->virtuemart_state_id) ? ShopFunctions::getStateByID($address->virtuemart_state_id) : 'XX';
		$buyLinkParams['email'] = $order['details']['BT']->email;
		$buyLinkParams['address'] = $address->address_1;
		if (!empty($address->address_2)) {
			$buyLinkParams['address2'] = $address->address_2;
		}
		$buyLinkParams['city'] = $address->city;
		$buyLinkParams['company-name'] = $address->company;

		$buyLinkParams['ship-name'] = $address->first_name . ' ' . $address->last_name;
		$buyLinkParams['ship-country'] = substr(ShopFunctions::getCountryByID($address->virtuemart_country_id, 'country_3_code'), 0, 2);
		$buyLinkParams['ship-state'] =  isset($address->virtuemart_state_id) ? ShopFunctions::getStateByID($address->virtuemart_state_id) : 'XX';
		$buyLinkParams['ship-city'] = $address->city;
		$buyLinkParams['ship-email'] = $order['details']['BT']->email;
		$buyLinkParams['ship-address'] = $address->address_1;
		$buyLinkParams['ship-address2'] = !empty($address->address_2) ? $address->address_2 : '';
		$buyLinkParams['zip'] = $address->zip;

		$paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
		$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);

		$buyLinkParams['prod'] = 'Cart_' . $orderNumber;
		$buyLinkParams['price'] = $totalInPaymentCurrency;
		$buyLinkParams['qty'] = 1;
		$buyLinkParams['type'] = 'PRODUCT';
		$buyLinkParams['tangible'] = 0;
		$buyLinkParams['src'] = 'VIRTUEMART_3_8';

		// url NEEDS a protocol(http or https)
		$buyLinkParams['return-type'] = 'redirect';
		$buyLinkParams['return-url'] = $this->getOrderPlaceRedirectUrl($order);
		$buyLinkParams['expiration'] = time() + (3600 * 5);
		$buyLinkParams['order-ext-ref'] = $orderNumber;
		$buyLinkParams['item-ext-ref'] = date('YmdHis');
		$buyLinkParams['customer-ext-ref'] = $order['details']['BT']->email;
		$buyLinkParams['currency'] = strtolower($currency);
		$buyLinkParams['language'] = strtolower(substr(JFactory::getLanguage()->getName(), 0, 2));

		$buyLinkParams['test'] = ($method->sandbox == 1) ? '1' : '0';
		// sid in this case is the merchant code
		$buyLinkParams['merchant'] =  $tcoDetails['seller_id'];
		$buyLinkParams['dynamic'] = 1;

		if ( ! class_exists( 'TcoData' ) )
		{
			require VMPATH_PLUGINS . '/vmpayment/twocheckout_convert/twocheckout_convert/helper/tcodata.php';
		}
		$vmTcoData = new tcoData();

		$buyLinkParams['signature'] = $vmTcoData->generateSignature(
			$buyLinkParams,
			$tcoDetails['secret_word']
		);


		return $buyLinkParams;
	}
}
<?php

class TwocheckoutLibrary
{

    /**
     * @param $order
     * @param $cart
     * @param $pluginName
     * @param $currentMethod
     * @param $apiResponse
     *
     * @return mixed
     *
     * @since version
     */
    public function storeInternalDataParams($order, $cart, $pluginName, $currentMethod, $apiResponse)
    {
        $session = JFactory::getSession();
        $paymentCurrency = CurrencyDisplay::getInstance($currentMethod->payment_currency);
        $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($currentMethod->payment_currency, $order['details']['BT']->order_total, false), 2);

        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['payment_name'] = $pluginName;
        $dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction'] = $currentMethod->cost_per_transaction;
        $dbValues['cost_percent_total'] = $currentMethod->cost_percent_total;
        $dbValues['payment_currency'] = $currentMethod->payment_currency;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency;
        $dbValues['tax_id'] = $currentMethod->tax_id;
        $dbValues['response'] = json_encode($apiResponse);
        $dbValues['response_order_number'] = $apiResponse['RefNo'];

        return $dbValues;
    }

}

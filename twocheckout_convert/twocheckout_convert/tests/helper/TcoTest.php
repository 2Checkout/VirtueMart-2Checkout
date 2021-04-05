<?php
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '\..\helper\tcoipncplus.php';
require_once dirname(__DIR__) . '\..\helper\tcodata.php';

final class TcoTest extends TestCase
{
	public $valid_ipn_helper_test_class;
	public $invalid_ipn_helper_test_class;
	public $params_valid_ipn_mock  =  array(
		'GIFT_ORDER' => '0',
		'SALEDATE' => '2020-08-04 16:37:50',
		'PAYMENTDATE' => '0000-00-00 00:00:00',
		'REFNO' => '129611332',
		'REFNOEXT' => '80',
		'ORIGINAL_REFNOEXT' =>
			array(
				0 => '',
			),
		'SHOPPER_REFERENCE_NUMBER' => '',
		'ORDERNO' => '0',
		'ORDERSTATUS' => 'PENDING',
		'PAYMETHOD' => 'Visa/MasterCard',
		'PAYMETHOD_CODE' => 'CCVISAMC',
		'FIRSTNAME' => 'John',
		'LASTNAME' => 'Doe',
		'COMPANY' => '',
		'REGISTRATIONNUMBER' => '',
		'FISCALCODE' => '',
		'TAX_OFFICE' => '',
		'CBANKNAME' => '',
		'CBANKACCOUNT' => '',
		'ADDRESS1' => 'Dimitrie Pompeiu 10A',
		'ADDRESS2' => '',
		'CITY' => 'Bucharest',
		'STATE' => 'B',
		'ZIPCODE' => '020337',
		'COUNTRY' => 'Romania',
		'COUNTRY_CODE' => 'ro',
		'PHONE' => '+40770789899',
		'FAX' => '',
		'CUSTOMEREMAIL' => 'cosmin.panait@2checkout.com',
		'FIRSTNAME_D' => 'John',
		'LASTNAME_D' => 'Doe',
		'COMPANY_D' => '',
		'ADDRESS1_D' => 'Dimitrie Pompeiu 10A',
		'ADDRESS2_D' => '',
		'CITY_D' => 'Bucharest',
		'STATE_D' => 'B',
		'ZIPCODE_D' => '020337',
		'COUNTRY_D' => 'Romania',
		'COUNTRY_D_CODE' => 'ro',
		'PHONE_D' => '+40770789899',
		'EMAIL_D' => 'cosmin.panait@2checkout.com',
		'IPADDRESS' => '127.0.0.1',
		'IPCOUNTRY' => '',
		'COMPLETE_DATE' => '0000-00-00 00:00:00',
		'TIMEZONE_OFFSET' => 'GMT+03:00',
		'CURRENCY' => 'EUR',
		'LANGUAGE' => 'en',
		'ORDERFLOW' => 'REGULAR',
		'IPN_PID' =>
			array(
				0 => '31126410',
			),
		'IPN_PNAME' =>
			array(
				0 => 'Woocomerce 2CO connector',
			),
		'IPN_PCODE' =>
			array(
				0 => '',
			),
		'IPN_EXTERNAL_REFERENCE' =>
			array(
				0 => '',
			),
		'IPN_INFO' =>
			array(
				0 => '',
			),
		'IPN_QTY' =>
			array(
				0 => '1',
			),
		'IPN_PRICE' =>
			array(
				0 => '10.00',
			),
		'IPN_VAT' =>
			array(
				0 => '0.00',
			),
		'IPN_VAT_RATE' =>
			array(
				0 => '0.00',
			),
		'IPN_VER' =>
			array(
				0 => '1',
			),
		'IPN_DISCOUNT' =>
			array(
				0 => '0.00',
			),
		'IPN_PROMONAME' =>
			array(
				0 => '',
			),
		'IPN_PROMOCODE' =>
			array(
				0 => '',
			),
		'IPN_ORDER_COSTS' =>
			array(
				0 => '0',
			),
		'IPN_SKU' =>
			array(
				0 => '',
			),
		'IPN_PARTNER_CODE' => '',
		'IPN_PGROUP' =>
			array(
				0 => '0',
			),
		'IPN_PGROUP_NAME' =>
			array(
				0 => '',
			),
		'MESSAGE_ID' => '250447182633',
		'MESSAGE_TYPE' => 'PENDING',
		'IPN_LICENSE_PROD' =>
			array(
				0 => '31126410',
			),
		'IPN_LICENSE_TYPE' =>
			array(
				0 => 'REGULAR',
			),
		'IPN_LICENSE_REF' =>
			array(
				0 => '',
			),
		'IPN_LICENSE_EXP' =>
			array(
				0 => '',
			),
		'IPN_LICENSE_START' =>
			array(
				0 => '',
			),
		'IPN_LICENSE_LIFETIME' =>
			array(
				0 => 'NO',
			),
		'IPN_LICENSE_ADDITIONAL_INFO' =>
			array(
				0 => '',
			),
		'IPN_DELIVEREDCODES' =>
			array(
				0 => '',
			),
		'IPN_DOWNLOAD_LINK' => '',
		'IPN_TOTAL' =>
			array(
				0 => '10.00',
			),
		'IPN_TOTALGENERAL' => '10.00',
		'IPN_SHIPPING' => '0.00',
		'IPN_SHIPPING_TAX' => '0.00',
		'AVANGATE_CUSTOMER_REFERENCE' => '',
		'EXTERNAL_CUSTOMER_REFERENCE' => '',
		'IPN_PARTNER_MARGIN_PERCENT' => '0.00',
		'IPN_PARTNER_MARGIN' => '0.00',
		'IPN_EXTRA_MARGIN' => '0.00',
		'IPN_EXTRA_DISCOUNT' => '0.00',
		'IPN_COUPON_DISCOUNT' => '0.00',
		'IPN_LINK_SOURCE' => 'WOOCOMMERCE_3_8',
		'IPN_ORIGINAL_LINK_SOURCE' =>
			array(
				0 => '',
			),
		'IPN_COMMISSION' => '0.50',
		'REFUND_TYPE' => '',
		'CHARGEBACK_RESOLUTION' => 'NONE',
		'CHARGEBACK_REASON_CODE' => '',
		'TEST_ORDER' => '0',
		'IPN_ORDER_ORIGIN' => 'API',
		'FRAUD_STATUS' => 'PENDING',
		'CARD_TYPE' => 'Visa',
		'CARD_LAST_DIGITS' => '1111',
		'CARD_EXPIRATION_DATE' => '',
		'GATEWAY_RESPONSE' => '',
		'IPN_DATE' => '20200804163753',
		'FX_RATE' => '1.125696',
		'FX_MARKUP' => '4',
		'PAYABLE_AMOUNT' => '10.69',
		'PAYOUT_CURRENCY' => 'USD',
		'VENDOR_CODE' => '250111206876',
		'PROPOSAL_ID' => '',
		'HASH' => '0fd2263ec78170040c100d851bf4644c'
	);

	public $params_invalid_ipn_mock = array(
		'GIFT_ORDER' => '0',
		'SALEDATE' => '2020-08-04 16:37:50',
		'PAYMENTDATE' => '0000-00-00 00:00:00',
		'REFNO' => '129611332',
		'REFNOEXT' => '80',
		'ORIGINAL_REFNOEXT' =>
			array(
				0 => '',
			),
		'SHOPPER_REFERENCE_NUMBER' => '',
		'ORDERNO' => '0',
		'ORDERSTATUS' => 'PENDING',
		'PAYMETHOD' => 'Visa/MasterCard',
		'PAYMETHOD_CODE' => 'CCVISAMC',
		'FIRSTNAME' => 'John',
		'LASTNAME' => 'Doe',
		'COMPANY' => '',
		'REGISTRATIONNUMBER' => '',
		'FISCALCODE' => '',
		'TAX_OFFICE' => '',
		'CBANKNAME' => '',
		'CBANKACCOUNT' => '',
		'ADDRESS1' => 'Dimitrie Pompeiu 10A',
		'ADDRESS2' => '',
		'CITY' => 'Bucharest',
		'STATE' => 'B',
		'ZIPCODE' => '020337',
		'COUNTRY' => 'Romania',
		'COUNTRY_CODE' => 'ro',
		'PHONE' => '+40770789899',
		'FAX' => '',
		'CUSTOMEREMAIL' => 'cosmin.panait@2checkout.com',
		'FIRSTNAME_D' => 'John',
		'LASTNAME_D' => 'Doe',
		'COMPANY_D' => '',
		'ADDRESS1_D' => 'Dimitrie Pompeiu 10A',
		'ADDRESS2_D' => '',
		'CITY_D' => 'Bucharest',
		'STATE_D' => 'B',
		'ZIPCODE_D' => '020337',
		'COUNTRY_D' => 'Romania',
		'COUNTRY_D_CODE' => 'ro',
		'PHONE_D' => '+40770789899',
		'EMAIL_D' => 'cosmin.panait@2checkout.com',
		'IPADDRESS' => '127.0.0.1',
		'IPCOUNTRY' => '',
		'COMPLETE_DATE' => '0000-00-00 00:00:00',
		'TIMEZONE_OFFSET' => 'GMT+03:00',
		'CURRENCY' => 'EUR',
		'LANGUAGE' => 'en',
		'ORDERFLOW' => 'REGULAR',
		'IPN_PID' =>
			array(
				0 => '31126410',
			),
		'IPN_PNAME' =>
			array(
				0 => 'Virtuemart 2CO connector',
			),
		'IPN_PCODE' =>
			array(
				0 => '',
			),
		'IPN_EXTERNAL_REFERENCE' =>
			array(
				0 => '',
			),
		'IPN_INFO' =>
			array(
				0 => '',
			),
		'IPN_QTY' =>
			array(
				0 => '1',
			),
		'IPN_PRICE' =>
			array(
				0 => '10.00',
			),
		'IPN_VAT' =>
			array(
				0 => '0.00',
			),
		'IPN_VAT_RATE' =>
			array(
				0 => '0.00',
			),
		'IPN_VER' =>
			array(
				0 => '1',
			),
		'IPN_DISCOUNT' =>
			array(
				0 => '0.00',
			),
		'IPN_PROMONAME' =>
			array(
				0 => '',
			),
		'IPN_PROMOCODE' =>
			array(
				0 => '',
			),
		'IPN_ORDER_COSTS' =>
			array(
				0 => '0',
			),
		'IPN_SKU' =>
			array(
				0 => '',
			),
		'IPN_PARTNER_CODE' => '',
		'IPN_PGROUP' =>
			array(
				0 => '0',
			),
		'IPN_PGROUP_NAME' =>
			array(
				0 => '',
			),
		'MESSAGE_ID' => '250447182633',
		'MESSAGE_TYPE' => 'PENDING',
		'IPN_LICENSE_PROD' =>
			array(
				0 => '31126410',
			),
		'IPN_LICENSE_TYPE' =>
			array(
				0 => 'REGULAR',
			),
		'IPN_LICENSE_REF' =>
			array(
				0 => '',
			),
		'IPN_LICENSE_EXP' =>
			array(
				0 => '',
			),
		'IPN_LICENSE_START' =>
			array(
				0 => '',
			),
		'IPN_LICENSE_LIFETIME' =>
			array(
				0 => 'NO',
			),
		'IPN_LICENSE_ADDITIONAL_INFO' =>
			array(
				0 => '',
			),
		'IPN_DELIVEREDCODES' =>
			array(
				0 => '',
			),
		'IPN_DOWNLOAD_LINK' => '',
		'IPN_TOTAL' =>
			array(
				0 => '10.00',
			),
		'IPN_TOTALGENERAL' => '10.00',
		'IPN_SHIPPING' => '0.00',
		'IPN_SHIPPING_TAX' => '0.00',
		'AVANGATE_CUSTOMER_REFERENCE' => '',
		'EXTERNAL_CUSTOMER_REFERENCE' => '',
		'IPN_PARTNER_MARGIN_PERCENT' => '0.00',
		'IPN_PARTNER_MARGIN' => '0.00',
		'IPN_EXTRA_MARGIN' => '0.00',
		'IPN_EXTRA_DISCOUNT' => '0.00',
		'IPN_COUPON_DISCOUNT' => '0.00',
		'IPN_LINK_SOURCE' => 'VIRTUEMART_3_8',
		'IPN_ORIGINAL_LINK_SOURCE' =>
			array(
				0 => '',
			),
		'IPN_COMMISSION' => '0.50',
		'REFUND_TYPE' => '',
		'CHARGEBACK_RESOLUTION' => 'NONE',
		'CHARGEBACK_REASON_CODE' => '',
		'TEST_ORDER' => '0',
		'IPN_ORDER_ORIGIN' => 'API',
		'FRAUD_STATUS' => 'PENDING',
		'CARD_TYPE' => 'Visa',
		'CARD_LAST_DIGITS' => '1111',
		'CARD_EXPIRATION_DATE' => '',
		'GATEWAY_RESPONSE' => '',
		'IPN_DATE' => '20200804163753',
		'FX_RATE' => '1.125696',
		'FX_MARKUP' => '4',
		'PAYABLE_AMOUNT' => '10.69',
		'PAYOUT_CURRENCY' => 'USD',
		'VENDOR_CODE' => '250111206876',
		'PROPOSAL_ID' => '',
		'HASH' => '0fd3263ec78170040c100d851bf4644c'
);
	
	public $secret_key = 'test';
	public $test_order;
	public $order_number;
	public $current_method;

	public function testIpnResponseValid(){
		$tco_ipn_helper = new TcoIpnCplus( $this->params_valid_ipn_mock, $this->order_number, $this->secret_key, $this->current_method );
		$tco_ipn_helper->isIpnResponseValid();
		$this->assertTrue($tco_ipn_helper->isIpnResponseValid());
		$tco_ipn_helper_two = new TcoIpnCplus( $this->params_invalid_ipn_mock, $this->order_number, $this->secret_key, $this->current_method );
		$this->assertFalse($tco_ipn_helper_two->isIpnResponseValid());
	}

	public function testHmac()
	{
		$tco_data_helper = new TcoData();
		$this->assertEquals('a299fb35e2c113b7d72e8f7afa7ae1f7',
			$tco_data_helper->hmac($this->secret_key, 'TestString'));
		$this->assertNotEquals('NotValid____5e2c113b7d72e8f7afa7ae1f8',
			$tco_data_helper->hmac($this->secret_key, 'TestString'));
	}

}


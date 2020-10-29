<?php

class TcoIpn {

	// NEVER catch exceptions in this file
	// they're meant to kill the process
	// if they are caught please rethrow them

	/**
	 * Ipn Constants
	 *
	 * Not all are used, however they should be left here
	 * for future reference
	 */
	const ORDER_CREATED = 'ORDER_CREATED';
	const FRAUD_STATUS_CHANGED = 'FRAUD_STATUS_CHANGED';
	const INVOICE_STATUS_CHANGED = 'INVOICE_STATUS_CHANGED';
	const REFUND_ISSUED = 'REFUND_ISSUED';
	//Order Status Values:
	const ORDER_STATUS_PENDING = 'PENDING';
	const ORDER_STATUS_PAYMENT_AUTHORIZED = 'PAYMENT_AUTHORIZED';
	const ORDER_STATUS_SUSPECT = 'SUSPECT';
	const ORDER_STATUS_INVALID = 'INVALID';
	const ORDER_STATUS_COMPLETE = 'COMPLETE';
	const ORDER_STATUS_REFUND = 'REFUND';
	const ORDER_STATUS_REVERSED = 'REVERSED';
	const ORDER_STATUS_PURCHASE_PENDING = 'PURCHASE_PENDING';
	const ORDER_STATUS_PAYMENT_RECEIVED = 'PAYMENT_RECEIVED';
	const ORDER_STATUS_CANCELED = 'CANCELED';
	const ORDER_STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';
	const FRAUD_STATUS_APPROVED = 'APPROVED';
	const FRAUD_STATUS_DENIED = 'DENIED';
	const FRAUD_STATUS_REVIEW = 'UNDER REVIEW';
	const FRAUD_STATUS_PENDING = 'PENDING';
	const PAYMENT_METHOD = 'tco_checkout';

	/**
	 * @var array
	 */
	protected $_order;

	/**
	 * @var string
	 */
	protected $_order_number;

	/**
	 * @var TcoData
	 */
	protected $_tco_helper;

	/**
	 * @var array
	 */
	protected $_ipn_params;

	/**
	 * @var string
	 */
	protected $_secret_key;

	/**
	 * @var array
	 */
	protected $_current_method;

	/**
	 * @var
	 */
	protected $_order_model;

	/**
	 * TcoIpn constructor.
	 *
	 * @param $_ipn_params
	 * @param $_secret_key
	 * @param $_current_method
	 */
	public function __construct( $_ipn_params, $_order_number, $_secret_key, $_current_method ) {
		if ( ! class_exists( 'TcoData' ) )
		{
			require VMPATH_PLUGINS . '/vmpayment/tco_inline/tco_inline/helper/TcoData.php';
		}
		$this->_tco_helper     = new TcoData();
		$this->_ipn_params     = $_ipn_params;
		$this->_order_number   = $_order_number;
		$this->_secret_key     = $_secret_key;
		$this->_current_method = $_current_method;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	public function indexAction() {
		if ( ! isset( $this->_ipn_params['REFNOEXT'] ) && ( ! isset( $this->_ipn_params['REFNO'] ) && empty( $this->_ipn_params['REFNO'] ) ) )
		{
			throw new Exception( sprintf( 'Cannot identify order: "%s".',
				$this->_ipn_params['REFNOEXT'] ) );
		}

		if ( ! $this->isIpnResponseValid() )
		{
			throw new Exception( sprintf( 'MD5 hash mismatch for 2Checkout IPN with date: "%s".',
				$this->_ipn_params['IPN_DATE'] ) );
		}

		if ( ! class_exists( 'VirtueMartModelOrders' ) )
		{
			require( VMPATH_ADMIN . DS . 'models' . DS . 'orders.php' );
		}

		$this->_order_model = VmModel::getModel( 'orders' );
		$this->_order       = $this->_order_model->getOrder( $this->_order_number );

		// do not wrap this in a try catch
		// it's intentionally left out so that the exceptions will bubble up
		// and kill the script if one should arise
		$this->_processFraud( $this->_ipn_params );

		if ( $this->_isNotFraud( $this->_ipn_params ) )
		{
			$this->_processOrderStatus( $this->_ipn_params );
		}

		echo $this->_calculateIpnResponse(
			$this->_ipn_params,
			$this->_secret_key
		);
		die;
	}

	/**
	 * @param $params
	 *
	 * @return bool
	 */
	protected function _isNotFraud( $params ) {
		return ( isset( $params['FRAUD_STATUS'] ) && trim( $params['FRAUD_STATUS'] ) === self::FRAUD_STATUS_APPROVED );
	}

	/**
	 * @return bool
	 */
	public function isIpnResponseValid() {
		$result       = '';
		$receivedHash = $this->_ipn_params['HASH'];
		foreach ( $this->_ipn_params as $key => $val )
		{

			if ( $key != "HASH" )
			{
				if ( is_array( $val ) )
				{
					$result .= $this->_tco_helper->arrayExpand( $val );
				}
				else
				{
					$size   = strlen( stripslashes( $val ) );
					$result .= $size . stripslashes( $val );
				}
			}
		}
		if ( isset( $this->_ipn_params['REFNO'] ) && ! empty( $this->_ipn_params['REFNO'] ) )
		{
			$calcHash = $this->_tco_helper->hmac( $this->_secret_key, $result );
			if ( $receivedHash === $calcHash )
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $ipn_params
	 * @param $secret_key
	 *
	 * @return string
	 */
	private function _calculateIpnResponse() {
		$resultResponse    = '';
		$ipnParamsResponse = [];
		// we're assuming that these always exist, if they don't then the problem is on avangate side
		$ipnParamsResponse['IPN_PID'][0]   = $this->_ipn_params['IPN_PID'][0];
		$ipnParamsResponse['IPN_PNAME'][0] = $this->_ipn_params['IPN_PNAME'][0];
		$ipnParamsResponse['IPN_DATE']     = $this->_ipn_params['IPN_DATE'];
		$ipnParamsResponse['DATE']         = date( 'YmdHis' );

		foreach ( $ipnParamsResponse as $key => $val )
		{
			$resultResponse .= $this->_tco_helper->arrayExpand( (array) $val );
		}

		return sprintf(
			'<EPAYMENT>%s|%s</EPAYMENT>',
			$ipnParamsResponse['DATE'],
			$this->_tco_helper->hmac( $this->_secret_key, $resultResponse )
		);
	}

	/**
	 * @param $params
	 *
	 * @throws Exception
	 */
	private function _processOrderStatus( $params ) {
		$orderStatus = $params['ORDERSTATUS'];
		if ( ! empty( $orderStatus ) )
		{
			$order = array();
			switch ( trim( $orderStatus ) )
			{
				case self::ORDER_STATUS_PENDING:
				case self::ORDER_STATUS_PURCHASE_PENDING:
				case self::ORDER_STATUS_PENDING_APPROVAL:
				case self::ORDER_STATUS_PAYMENT_AUTHORIZED:
					$order['order_status'] = $this->_current_method->status_pending;
					$order['comments']     = vmText::sprintf( 'VMPAYMENT_TCO_INLINE_PAYMENT_STATUS_PENDING', $this->_order_number );
					break;

				case self::ORDER_STATUS_COMPLETE:
					$order['order_status'] = $this->_current_method->status_success;
					$order['comments']     = vmText::sprintf( 'VMPAYMENT_TCO_INLINE_PAYMENT_STATUS_CONFIRMED', $this->_order_number, $params['REFNO'] );
					break;

				case self::ORDER_STATUS_INVALID:
					$order['order_status'] = $this->_current_method->status_canceled;
					$order['comments']     = vmText::sprintf( 'VMPAYMENT_TCO_INLINE_PAYMENT_STATUS_INVALID', $this->_order_number );
					break;

				case self::ORDER_STATUS_REFUND:
					$order['order_status'] = $this->_current_method->status_refunded;
					$order['comments']     = vmText::sprintf( 'VMPAYMENT_TCO_INLINE_PAYMENT_STATUS_REFUNDED', $this->_order_number );
					break;

				default:
					throw new Exception( 'Cannot handle Ipn message type for message' );
			}

			$this->_order_model->updateStatusForOneOrder( $this->_order_number, $order, true );
		}
	}

	/**
	 * @param $params
	 */
	private function _processFraud( $params ) {
		if ( isset( $params['FRAUD_STATUS'] ) )
		{
			$order = array();
			switch ( trim( $params['FRAUD_STATUS'] ) )
			{
				case self::FRAUD_STATUS_DENIED:
				case self::ORDER_STATUS_SUSPECT:
				case self::ORDER_STATUS_INVALID:
					$order['order_status'] = $this->_current_method->status_fraud;
					$order['comments']     = vmText::sprintf( 'VMPAYMENT_TCO_INLINE_PAYMENT_FRAUD_STATUS_DENIED', $this->_order_number );
					break;

				case self::FRAUD_STATUS_APPROVED:
					if ( $this->_order['details']['BT']->order_status == 'C' )
					{
						$order['order_status'] = $this->_current_method->status_success;
					}
					else
					{
						$order['order_status'] = $this->_current_method->status_pending;
					}
					$order['comments'] = vmText::sprintf( 'VMPAYMENT_TCO_INLINE_PAYMENT_FRAUD_STATUS_APPROVED', $this->_order_number );
					break;
			}

			$this->_order_model->updateStatusForOneOrder( $this->_order_number, $order, true );
		}
	}

}

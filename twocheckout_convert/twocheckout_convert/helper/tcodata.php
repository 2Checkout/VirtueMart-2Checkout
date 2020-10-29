<?php

class TcoData {

	/**
	 * @var array
	 */
	private $_signParams = [
		'return-url',
		'return-type',
		'expiration',
		'order-ext-ref',
		'item-ext-ref',
		'lock',
		'cust-params',
		'customer-ref',
		'customer-ext-ref',
		'currency',
		'prod',
		'price',
		'qty',
		'tangible',
		'type',
		'opt',
		'coupon',
		'description',
		'recurrence',
		'duration',
		'renewal-price',
	];

	/**
	 * @var array
	 */
	protected $_supportedCurrencyCodes = [
		'AFN',
		'ALL',
		'DZD',
		'ARS',
		'AUD',
		'AZN',
		'BSD',
		'BDT',
		'BBD',
		'BZD',
		'BMD',
		'BOB',
		'BWP',
		'BRL',
		'GBP',
		'BND',
		'BGN',
		'CAD',
		'CLP',
		'CNY',
		'COP',
		'CRC',
		'HRK',
		'CZK',
		'DKK',
		'DOP',
		'XCD',
		'EGP',
		'EUR',
		'FJD',
		'GTQ',
		'HKD',
		'HNL',
		'HUF',
		'INR',
		'IDR',
		'ILS',
		'JMD',
		'JPY',
		'KZT',
		'KES',
		'LAK',
		'MMK',
		'LBP',
		'LRD',
		'MOP',
		'MYR',
		'MVR',
		'MRO',
		'MUR',
		'MXN',
		'MAD',
		'NPR',
		'TWD',
		'NZD',
		'NIO',
		'NOK',
		'PKR',
		'PGK',
		'PEN',
		'PHP',
		'PLN',
		'QAR',
		'RON',
		'RUB',
		'WST',
		'SAR',
		'SCR',
		'SGF',
		'SBD',
		'ZAR',
		'KRW',
		'LKR',
		'SEK',
		'CHF',
		'SYP',
		'THB',
		'TOP',
		'TTD',
		'TRY',
		'UAH',
		'AED',
		'USD',
		'VUV',
		'VND',
		'XOF',
		'YER'
	];

	/**
	 * array of 2CO allowed currency
	 * @param $currency
	 * @return bool
	 */
	public function canUseCurrency($currency)
	{

		return in_array(strtoupper($currency), $this->_supportedCurrencyCodes);
	}

	/**
	 * @return array
	 */
	public function getSupportedCurrency()
	{
		return $this->_supportedCurrencyCodes;
	}

	/**
	 * @param $params
	 * @param $secretWord
	 * @param bool $fromResponse
	 *
	 * @return string
	 */
	public function generateSignature(
		$params,
		$secretWord,
		$fromResponse = false
	) {
		if (!$fromResponse) {
			$signParams = array_filter($params, function ($k) {
				return in_array($k, $this->_signParams);
			}, ARRAY_FILTER_USE_KEY);
		} else {
			$signParams = $params;
			if (isset($signParams['signature'])) {
				unset($signParams['signature']);
			}
		}

		ksort($signParams); // order by key
		// Generate Hash
		$string = '';
		foreach ($signParams as $key => $value) {
			$string .= strlen($value) . $value;
		}

		return bin2hex(hash_hmac('sha256', $string, $secretWord, true));
	}

	/**
	 * @param $array
	 *
	 * @return string
	 */
	public function arrayExpand($array)
	{
		$retval = '';
		foreach ($array as $key => $value) {
			$size = strlen(stripslashes($value));
			$retval .= $size . stripslashes($value);
		}
		return $retval;
	}

	/**
	 * @param $key
	 * @param $data
	 *
	 * @return string
	 */
	public function hmac($key, $data)
	{
		$b = 64; // byte length for md5
		if (strlen($key) > $b) {
			$key = pack("H*", md5($key));
		}

		$key = str_pad($key, $b, chr(0x00));
		$ipad = str_pad('', $b, chr(0x36));
		$opad = str_pad('', $b, chr(0x5c));
		$k_ipad = $key ^ $ipad;
		$k_opad = $key ^ $opad;

		return md5($k_opad . pack("H*", md5($k_ipad . $data)));
	}

	/**
	 * @param $merchantId
	 * @param $secretKey
	 *
	 * @return array
	 */
	public function generateHeaders($merchantId, $secretKey)
	{
		$gmtDate = gmdate('Y-m-d H:i:s');
		$string = strlen($merchantId) . $merchantId . strlen($gmtDate) . $gmtDate;
		$hash = hash_hmac('md5', $string, $secretKey);
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Accept: application/json';
		$headers[] = sprintf('X-Avangate-Authentication: code="%s" date="%s" hash="%s"',
			$merchantId, $gmtDate, $hash);

		return $headers;
	}

	/**
	 * @param $vendorCode
	 * @param $secret
	 * @param $requestDateTime
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function generateHash($vendorCode, $secret, $requestDateTime)
	{
		return hash_hmac('md5',
			sprintf('%s%s%s%s', strlen($vendorCode), $vendorCode,
				strlen($requestDateTime), $requestDateTime), $secret);
	}

	/**
	 * @param $sub
	 * @param $iat
	 * @param $exp
	 * @param $buyLinkSecretWord
	 *
	 * @return string
	 */
	public function generateJWTToken($sub, $iat, $exp, $buyLinkSecretWord)
	{
		$header = $this->encode(json_encode(['alg' => 'HS512', 'typ' => 'JWT']));
		$payload = $this->encode(json_encode(['sub' => $sub, 'iat' => $iat, 'exp' => $exp]));
		$signature = $this->encode(
			hash_hmac('sha512', "$header.$payload", $buyLinkSecretWord, true)
		);

		return implode('.', [
			$header,
			$payload,
			$signature
		]);
	}

	/**
	 * @param $data
	 *
	 * @return string|string[]
	 */
	private function encode($data)
	{
		return str_replace('=', '', strtr(base64_encode($data), '+/', '-_'));
	}

	/**
	 * @param $merchantId
	 * @param $buyLinkSecretWord
	 * @param $payload
	 * @return mixed
	 * @throws Exception
	 */

	public function getInlineSignature($merchantId, $buyLinkSecretWord, $payload)
	{
		$jwtToken = $this->generateJWTToken(
			$merchantId,
			time(),
			time() + 3600,
			$buyLinkSecretWord
		);

		$curl = curl_init();

		curl_setopt_array($curl, [
			CURLOPT_URL            => "https://secure.2checkout.com/checkout/api/encrypt/generate/signature",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => json_encode($payload),
			CURLOPT_HTTPHEADER     => [
				'content-type: application/json',
				'cache-control: no-cache',
				'merchant-token: ' . $jwtToken,
			],
		]);
		$response = curl_exec($curl);
		$err      = curl_error($curl);
		curl_close($curl);

		if ($err) {
			throw new Exception(sprintf('Unable to get proper response from signature generation API. In file %s at line %s', __FILE__, __LINE__));
		}

		$response = json_decode($response, true);
		if (JSON_ERROR_NONE !== json_last_error() || !isset($response['signature'])) {
			throw new Exception(sprintf('Unable to get proper response from signature generation API. Signature not set. In file %s at line %s', __FILE__, __LINE__));
		}

		return $response['signature'];

	}


}
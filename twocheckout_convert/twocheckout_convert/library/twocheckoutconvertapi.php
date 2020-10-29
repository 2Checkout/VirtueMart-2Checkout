<?php

/**
 * @package     TwocheckoutApi
 *
 * @since       version
 */
class TwoCheckoutConvertApi
{

    const   API_URL = 'https://api.2checkout.com/rest/';
    const   API_VERSION = '6.0';

    /**
     * @var
     * @since version
     */
    private $sellerId;

    /**
     * @var
     * @since version
     */
    private $secretKey;

    /**
     * @var
     * @since version
     */
    private $testOrder;


    /**
     *
     * @return mixed
     *
     * @since version
     */
    public function getTestOrder()
    {
        return $this->testOrder;
    }

    /**
     * @param bool $testOrder
     *
     *
     * @since version
     */
    public function setTestOrder(bool $testOrder)
    {
        $this->testOrder = $testOrder;
    }

    /**
     *
     * @return mixed
     *
     * @since version
     */
    public function getSellerId()
    {
        return $this->sellerId;
    }

    /**
     * @param $sellerId
     *
     * @return $this
     *
     * @since version
     */
    public function setSellerId($sellerId)
    {
        $this->sellerId = $sellerId;

        return $this;
    }

    /**
     *
     * @return mixed
     *
     * @since version
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }

    /**
     * @param $secretKey
     *
     * @return $this
     *
     * @since version
     */
    public function setSecretKey($secretKey)
    {
        $this->secretKey = $secretKey;

        return $this;
    }

    /**
     *
     * @return mixed
     *
     * @throws Exception
     * @since version
     */
    private function getHeaders()
    {
        if (!$this->sellerId || !$this->secretKey) {
            throw new Exception('Merchandiser needs a valid 2Checkout SellerId and SecretKey to authenticate!');
        }
        $gmtDate = gmdate('Y-m-d H:i:s');
        $string = strlen($this->sellerId) . $this->sellerId . strlen($gmtDate) . $gmtDate;
        $hash = hash_hmac('md5', $string, $this->secretKey);

        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept: application/json';
        $headers[] = 'X-Avangate-Authentication: code="' . $this->sellerId . '" date="' . $gmtDate . '" hash="' . $hash . '"';

        return $headers;
    }

    /**
     * @param string $endpoint
     * @param array  $params
     * @param string $method
     *
     * @return mixed
     *
     * @throws Exception
     * @since version
     */
    public function call(string $endpoint, array $params, $method = 'POST')
    {
        // if endpoint does not starts or end with a '/' we add it, as the API needs it
        if ($endpoint[0] !== '/') {
            $endpoint = '/' . $endpoint;
        }
        if ($endpoint[-1] !== '/') {
            $endpoint = $endpoint . '/';
        }
        $headers = $this->getHeaders();
        try {
            $url = self::API_URL . self::API_VERSION . $endpoint;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
            }
            $response = curl_exec($ch);

            if ($response === false) {
                exit(curl_error($ch));
            }
            curl_close($ch);

            return json_decode($response, true);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}

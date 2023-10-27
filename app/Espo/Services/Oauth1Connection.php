<?php

namespace Espo\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use OAuth\Common\Http\Uri\UriInterface;
use OAuth\OAuth1\Token\TokenInterface;

trait Oauth1Connection
{
    public function getBasicAuthorizationHeaderInfo($consumerKey)
    {
        $dateTime = new \DateTime();
        $headerParameters = [
            'oauth_consumer_key' => $consumerKey,
            'oauth_signature_method' => 'HMAC-SHA256',
            'oauth_nonce' => $this->generateNonce(),
            'oauth_timestamp' => $dateTime->format('U'),
            'oauth_version' => '1.0'
        ];

        return $headerParameters;
    }

    protected function generateNonce($length = 32)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';

        $nonce = '';
        $maxRand = strlen($characters) - 1;
        for ($i = 0; $i < $length; $i++) {
            $nonce .= $characters[rand(0, $maxRand)];
        }

        return $nonce;
    }

    public function getSignature($url, array $params, $method = 'POST',$consumerSecret = null, $tokenSecret = null)
    {
        $dataUri = parse_url($url);

        if(!empty($dataUri['query'])){
            parse_str($dataUri['query'], $queryStringData);
        }

        foreach (array_merge($queryStringData ?? [], $params) as $key => $value) {
            $signatureData[rawurlencode($key)] = rawurlencode($value);
        }

        ksort($signatureData);
        $baseString = strtoupper($method) . '&';
        $baseString .= rawurlencode($this->getBaseUri($dataUri)) . '&';
        $baseString .= rawurlencode($this->buildSignatureDataString($signatureData));
        return base64_encode($this->hash($baseString, $consumerSecret, $tokenSecret));
    }

    protected function buildSignatureDataString(array $signatureData)
    {
        $signatureString = '';
        $delimiter = '';
        foreach ($signatureData as $key => $value) {
            $signatureString .= $delimiter . $key . '=' . $value;
            $delimiter = '&';
        }

        return $signatureString;
    }


    protected function getSigningKey($consumeSecret, $tokenSecret = null)
    {
        $signingKey = rawurlencode($consumeSecret) . '&';
        if ($tokenSecret) {
            $signingKey .= rawurlencode($tokenSecret);
        }
        return $signingKey;
    }

    protected function hash($data, $consumerSecret, $tokenSecret =null)
    {
        return hash_hmac('sha256', $data, $this->getSigningKey($consumerSecret, $tokenSecret), true);

    }

    /**
     * @param $responseBody
     * @return array
     * @throws \Exception
     * @example  ['oauth_token'=> '', 'oauth_token_secret' => '']
     */
    public function parseResponseBody($responseBody)
    {
        if (!is_string($responseBody)) {
            throw new \Exception("Response body is expected to be a string.");
        }
        parse_str($responseBody, $data);
        if (null === $data || !is_array($data)) {
            throw new \Exception('Unable to parse response.');
        } elseif (isset($data['error'])) {
            throw new \Exception("Error occurred: '{$data['error']}'");
        }
        return $data;
    }

    public function request($method, string $url, array $headers, array $bodyParams = [])
    {
        $curlHeader = ['Content-Type: application/x-www-form-urlencoded'];
        foreach ($headers as $key => $header) {
            $curlHeader[] = "$key: $header";
        }

        $curl = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $curlHeader
        ];
        if($method ===' POST' && !empty($bodyParams)){
            $options[CURLOPT_CUSTOMREQUEST] = http_build_query($bodyParams);
        }
        curl_setopt_array($curl,$options);

        $response = curl_exec($curl);
        $curlInfo = curl_getinfo($curl);
        curl_close($curl);

        if($curlInfo['http_code'] !== 200){
            throw new BadRequest($response." ApiStatusCode: ".$curlInfo['http_code']);
        }

        return $this->parseResponseBody($response);
    }

    public function buildAuthorizationHeader($parameters){
        $authorizationHeader = 'OAuth ';
        $delimiter = '';
        foreach ($parameters as $key => $value) {
            $authorizationHeader .= $delimiter . rawurlencode($key) . '="' . rawurlencode($value) . '"';
            $delimiter = ', ';
        }

        return $authorizationHeader;
    }

    protected function buildAuthorizationHeaderForAPIRequest(Entity $connection,$method, $url, $bodyParams = []) : array {

        $authParameters = $this->getBasicAuthorizationHeaderInfo($connection->get('oauthConsumerKey'));
        if (isset($authParameters['oauth_callback'])) {
            unset($authParameters['oauth_callback']);
        }

        $authParameters['oauth_token'] = $connection->get('oauthToken');

        $signatureParams = array_merge($authParameters, $bodyParams) ;
        $authParameters['oauth_signature'] = $this->getSignature($url, $signatureParams, $method, $connection->get('oauthConsumerSecret'), $connection->get('oauthTokenSecret'));

        if (is_array($bodyParams) && isset($bodyParams['oauth_session_handle'])) {
            $authParameters['oauth_session_handle'] = $bodyParams['oauth_session_handle'];
            unset($bodyParams['oauth_session_handle']);
        }

        return ["Authorization"  => $this->buildAuthorizationHeader($authParameters)];
    }

    public function apiRequest(Entity  $connection, $method, $url, $bodyParams = []){
        return $this->request(
            $method,
            $url,
            $this->buildAuthorizationHeaderForAPIRequest($connection, $method, $url, $bodyParams),
            $bodyParams
        );
    }

    private function getBaseUri($data){
        $baseUri = $data['scheme'] . '//';
        if(!empty($data['user'])){
            $baseUri .= $data['user'];
        }
        if(!empty($data['pass'])){
            $baseUri .= ":".$data['pass'] . "@";
        }
        $baseUri .= $data['host'];
        if(!empty($data['port'])){
            $baseUri .= ":".$data['port'];
        }
        if(!empty($data['path'])){
            $baseUri .= $data['path'];
        }
        return $baseUri;
    }

}
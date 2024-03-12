<?php

namespace Espo\EntryPoints;

use Atro\ConnectionType\ConnectionOauth1;
use Atro\EntryPoints\AbstractEntryPoint;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\ORM\Entity;

class Oauth1Callback extends AbstractEntryPoint
{
    public static bool $authRequired = false;
    public static bool $notStrictAuth = false;
    private $consumerKey;
    private $consumerSecret;
    private $requestTokenUrl;
    private $accessTokenUrl;
    /**
    
    /**
     * @var ConnectionOauth1
     */
    private $connectionOauth1;

    public function run()
    {
        if (empty($_GET['connectionId'])) {
            throw new BadRequest();
        }

        $type = $_GET['type'];
        $this->connectionOauth1 = $this->getContainer()->get(ConnectionOauth1::class);
        $connectionId = $this->getServiceFactory()
            ->create('Connection')
            ->decryptPassword($_GET['connectionId']);
        $connection = $this->getEntityManager()->getEntity('Connection', $connectionId);

        if (empty($connection) || $connection->get('type') !== 'oauth1') {
            throw new BadRequest();
        }

        if ($type === 'callback') {
            $this->runCallback($connection);
        } else {
            $this->runTokenExchange($connection);
        }

    }

    private function runCallback(Entity $connection)
    {
        $connection->set('oauthConsumerKey', $_POST['oauth_consumer_key']);
        $connection->set('oauthConsumerSecret', $_POST['oauth_consumer_secret']);
        $connection->set('oauthVerifier', $_POST['oauth_verifier']);
        $connection->set('storeUrl', $_POST['store_base_url']);
        $this->getEntityManager()->saveEntity($connection);
    }

    private function runTokenExchange(Entity $connection)
    {
        $consumerKey = $_GET['oauth_consumer_key'] ?? $_GET['?oauth_consumer_key'];

        $this->requestTokenUrl = $connection->get('requestTokenUrl');
        $this->accessTokenUrl = $connection->get('accessTokenUrl');
        $this->consumerKey = $connection->get('oauthConsumerKey');
        $this->consumerSecret = $connection->get('oauthConsumerSecret');

        if ($consumerKey !== $this->consumerKey) {
            throw  new BadRequest("Mismatch of consumerKey send in callback and link url, please check again", 400);
        }

        $requestToken = $this->requestRequestToken();
        $accessToken = $this->requestAccessToken($connection->get('oauthVerifier'), $requestToken);

        $connection->set('oauthToken', $accessToken['oauth_token']);
        $connection->set(
            'oauthTokenSecret',
            $this->getServiceFactory()
                ->create('Connection')
                ->encryptPassword($accessToken['oauth_token_secret'])
        );
        $this->getEntityManager()->saveEntity($connection);
    }

    public function requestRequestToken()
    {
        $headers = ['Authorization' => $this->buildAuthorizationHeaderForTokenRequest()];
        $requestToken = $this->request('POST', $this->requestTokenUrl, $headers);

        return $requestToken;
    }

    public function requestAccessToken($verifier, $requestToken)
    {
        $bodyParams = [
            'oauth_verifier' => $verifier,
        ];
        $headers = [
            'Authorization' => $this->buildAuthorizationHeaderForAccessTokenReqest(
                'POST',
                $this->accessTokenUrl,
                $requestToken,
                $bodyParams
            )
        ];
        $token = $this->request('POST', $this->accessTokenUrl, $headers, $bodyParams);

        return $token;
    }

    protected function buildAuthorizationHeaderForTokenRequest()
    {
        $parameters = $this->connectionOauth1->getBasicAuthorizationHeaderInfo($this->consumerKey);
        $parameters['oauth_signature'] = $this->connectionOauth1->getSignature(
            $this->requestTokenUrl,
            $parameters,
            'POST',
            $this->consumerSecret
        );

        return $this->connectionOauth1->buildAuthorizationHeader($parameters);
    }


    protected function buildAuthorizationHeaderForAccessTokenReqest($method, $url, $requestToken, $bodyParams = null)
    {
        $authParameters = $this->connectionOauth1->getBasicAuthorizationHeaderInfo($this->consumerKey);
        $authParameters['oauth_token'] = $requestToken['oauth_token'];

        if (!empty($bodyParams['oauth_verifier'])) {
            $authParameters['oauth_verifier'] = $bodyParams['oauth_verifier'];
        }

        $signatureParams = (is_array($bodyParams)) ? array_merge($authParameters, $bodyParams) : $authParameters;
        $authParameters['oauth_signature'] = $this->connectionOauth1->getSignature(
            $url,
            $signatureParams,
            $method,
            $this->consumerSecret,
            $requestToken['oauth_token_secret']
        );

        return $this->connectionOauth1->buildAuthorizationHeader($authParameters);
    }

    private function request($method, string $url, array $headers, array $bodyParams = [])
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

        if ($method === ' POST' && !empty($bodyParams)) {
            $options[CURLOPT_CUSTOMREQUEST] = http_build_query($bodyParams);
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $curlInfo = curl_getinfo($curl);

        curl_close($curl);

        if ($curlInfo['http_code'] !== 200) {
            throw new BadRequest($response . " ApiStatusCode: " . $curlInfo['http_code']);
        }

        return $this->parseResponseBody($response, $curlInfo);
    }
    
    private function parseResponseBody($responseBody, $curlInfo = [])
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


}
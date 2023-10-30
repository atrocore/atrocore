<?php

namespace Espo\EntryPoints;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\ORM\Entity;
use Espo\Services\Connection;

class Oauth1Callback extends AbstractEntryPoint
{
    public static $authRequired = false;
    public static $notStrictAuth = false;
    private $consumerKey;
    private $consumerSecret;
    private $requestTokenUrl;
    private $accessTokenUrl;
    /**
     * @var Connection
     */
    private $connectionService;

    public function run()
    {
        if (empty($_GET['connectionId'])) {
            throw new BadRequest();
        }

        $type = $_GET['type'];
        $this->connectionService = $this->getServiceFactory()->create('Connection');
        $connectionId = $this->connectionService->decryptPassword($_GET['connectionId']);
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
        $connection->set('oauthTokenSecret', $this->connectionService->encryptPassword($accessToken['oauth_token_secret']));

        $this->getEntityManager()->saveEntity($connection);
    }

    public function requestRequestToken()
    {

        $headers = ['Authorization' => $this->buildAuthorizationHeaderForTokenRequest()];
        $requestToken = $this->connectionService->request('POST', $this->requestTokenUrl, $headers);

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
        $token = $this->connectionService->request('POST', $this->accessTokenUrl, $headers, $bodyParams);

        return $token;
    }

    protected function buildAuthorizationHeaderForTokenRequest()
    {
        $parameters = $this->connectionService->getBasicAuthorizationHeaderInfo($this->consumerKey);
        $parameters['oauth_signature'] = $this->connectionService->getSignature(
            $this->requestTokenUrl,
            $parameters,
            'POST',
            $this->consumerSecret
        );

        return $this->connectionService->buildAuthorizationHeader($parameters);
    }


    protected function buildAuthorizationHeaderForAccessTokenReqest($method, $url, $requestToken, $bodyParams = null)
    {
        $authParameters = $this->connectionService->getBasicAuthorizationHeaderInfo($this->consumerKey);
        $authParameters['oauth_token'] = $requestToken['oauth_token'];
        if (!empty($bodyParams['oauth_verifier'])) {
            $authParameters['oauth_verifier'] = $bodyParams['oauth_verifier'];
        }
        $signatureParams = (is_array($bodyParams)) ? array_merge($authParameters, $bodyParams) : $authParameters;
        $authParameters['oauth_signature'] = $this->connectionService->getSignature(
            $url,
            $signatureParams,
            $method,
            $this->consumerSecret,
            $requestToken['oauth_token_secret']
        );

        return $this->connectionService->buildAuthorizationHeader($authParameters);
    }


}
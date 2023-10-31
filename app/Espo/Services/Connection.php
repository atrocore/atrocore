<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

namespace Espo\Services;

use Atro\ConnectionType\ConnectionInterface;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class Connection extends Base
{
    protected $mandatorySelectAttributeList = ['data'];

    public function testConnection(string $id): bool
    {
        $connection = $this->getRepository()->get($id);
        if (empty($connection)) {
            throw new NotFound();
        }

        $this->connect($connection);

        return true;
    }

    public function connect(Entity $connectionEntity)
    {
        $connection = $this->getInjection('container')->get('\\Atro\\ConnectionType\\Connection' . ucfirst($connectionEntity->get('type')));

        if (empty($connection) || !$connection instanceof ConnectionInterface) {
            throw new BadRequest(sprintf($this->exception('connectionFailed'), $this->exception('noSuchType')));
        }

        return $connection->connect($connectionEntity);
    }

    public function createEntity($attachment)
    {
        $this->encryptPasswordFields($attachment);

        return parent::createEntity($attachment);
    }

    public function updateEntity($id, $data)
    {
        $this->encryptPasswordFields($data);

        return parent::updateEntity($id, $data);
    }

    public function encryptPassword(string $password): string
    {
        return openssl_encrypt($password, $this->getCypherMethod(), $this->getSecretKey(), 0, $this->getByteSecretIv());
    }

    public function decryptPassword(string $hash): string
    {
        return openssl_decrypt($hash, $this->getCypherMethod(), $this->getSecretKey(), 0, $this->getByteSecretIv());
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        foreach ($entity->getDataFields() as $name => $value) {
            $entity->set($name, $value);
        }
        if($entity->get('type') === 'oauth1'){
            $callbackUrl  = $this->getConfig()->get('siteUrl') . '?entryPoint=oauth1Callback&connectionId=' . $this->encryptPassword($entity->get('id')).'&type=callback';
            $linkUrl = $this->getConfig()->get('siteUrl') . '?entryPoint=oauth1Callback&connectionId=' . $this->encryptPassword($entity->get('id')).'&type=link';
            $entity->set('callbackUrl', $callbackUrl);
            $entity->set('linkUrl', $linkUrl);
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('container');
    }

    protected function encryptPasswordFields(\stdClass $inputData): void
    {
        foreach ($this->getMetadata()->get(['entityDefs', 'Connection', 'fields'], []) as $field => $fieldData) {
            if (!empty($fieldData['type']) && $fieldData['type'] === 'password' && property_exists($inputData, $field)) {
                $inputData->$field = $this->encryptPassword((string)$inputData->$field);
            }
        }
    }

    protected function getByteSecretIv(): string
    {
        $ivFile = 'data/byte-secret-iv-' . strtolower($this->getCypherMethod()) . '.txt';
        if (file_exists($ivFile)) {
            $iv = file_get_contents($ivFile);
        } else {
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->getCypherMethod()));
            file_put_contents($ivFile, $iv);
        }

        return $iv;
    }

    protected function getCypherMethod(): string
    {
        return $this->getConfig()->get('cypherMethod', 'AES-256-CBC');
    }

    protected function getSecretKey(): string
    {
        return $this->getConfig()->get('passwordSalt', 'ATRO');
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        return true;
    }

    protected function exception(string $name, string $scope = 'Connection'): string
    {
        return $this->getInjection('language')->translate($name, 'exceptions', $scope);
    }

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

    public function getSignature($url, array $params, $method = 'POST', $consumerSecret = null, $tokenSecret = null)
    {
        $dataUri = parse_url($url);

        if (!empty($dataUri['query'])) {
            $parameters = explode('&', urldecode($dataUri['query']));
            foreach ($parameters as $param) {
                if (strpos($param, '=') === false) continue;
                $tmp = explode("=", $param);
                $queryStringData[$tmp[0]] = $tmp[1];
            }
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

    protected function hash($data, $consumerSecret, $tokenSecret = null)
    {
        return hash_hmac('sha256', $data, $this->getSigningKey($consumerSecret, $tokenSecret), true);

    }

    public function buildAuthorizationHeader($parameters, $withTokenType = true)
    {
        $authorizationHeader = $withTokenType ? 'OAuth ' : '';
        $delimiter = '';
        foreach ($parameters as $key => $value) {
            $authorizationHeader .= $delimiter . rawurlencode($key) . '="' . rawurlencode($value) . '"';
            $delimiter = ', ';
        }

        return $authorizationHeader;
    }

    public function buildAuthorizationHeaderForAPIRequest(Entity $connection, $method, $url)
    {
        $authParameters = $this->getBasicAuthorizationHeaderInfo($connection->get('oauthConsumerKey'));
        $authParameters['oauth_token'] = $connection->get('oauthToken');
        $authParameters['oauth_signature'] = $this->getSignature($url, $authParameters, $method, $connection->get('oauthConsumerSecret'), $this->decryptPassword($connection->get('oauthTokenSecret')));

        return $this->buildAuthorizationHeader($authParameters, false);
    }

    private function getBaseUri($data)
    {
        $baseUri = $data['scheme'] . '://';
        if (!empty($data['user'])) {
            $baseUri .= $data['user'];
        }
        if (!empty($data['pass'])) {
            $baseUri .= ":" . $data['pass'] . "@";
        }
        $baseUri .= $data['host'];
        if (!empty($data['port'])) {
            $baseUri .= ":" . $data['port'];
        }
        if (!empty($data['path'])) {
            $baseUri .= $data['path'];
        }
        return $baseUri;
    }
}

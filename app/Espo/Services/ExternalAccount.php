<?php

namespace Espo\Services;

use \Espo\ORM\Entity;

use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\NotFound;

class ExternalAccount extends Record
{
    protected function getClient($integration, $id)
    {
        $integrationEntity = $this->getEntityManager()->getEntity('Integration', $integration);

        if (!$integrationEntity) {
            throw new NotFound();
        }
        $d = $integrationEntity->toArray();

        if (!$integrationEntity->get('enabled')) {
            throw new Error("{$integration} is disabled.");
        }

        $factory = new \Espo\Core\ExternalAccount\ClientManager($this->getEntityManager(), $this->getMetadata(), $this->getConfig());
        return $factory->create($integration, $id);
    }

    public function getExternalAccountEntity($integration, $userId)
    {
        return $this->getEntityManager()->getEntity('ExternalAccount', $integration . '__' . $userId);
    }

    public function ping($integration, $userId)
    {
        $entity = $this->getExternalAccountEntity($integration, $userId);
        try {
            $client = $this->getClient($integration, $userId);
            if ($client) {
                return $client->ping();
            }
        } catch (\Exception $e) {}
    }

    public function authorizationCode($integration, $userId, $code)
    {
        $entity = $this->getExternalAccountEntity($integration, $userId);
        if (!$entity) {
            throw new NotFound();
        }
        $entity->set('enabled', true);
        $this->getEntityManager()->saveEntity($entity);

        $client = $this->getClient($integration, $userId);
        if ($client instanceof \Espo\Core\ExternalAccount\Clients\OAuth2Abstract) {
            $result = $client->getAccessTokenFromAuthorizationCode($code);
            if (!empty($result) && !empty($result['accessToken'])) {
                $entity->clear('accessToken');
                $entity->clear('refreshToken');
                $entity->clear('tokenType');
                foreach ($result as $name => $value) {
                    $entity->set($name, $value);
                }
                $this->getEntityManager()->saveEntity($entity);
                return true;
            } else {
                throw new Error("Could not get access token for {$integration}.");
            }
        } else {
            throw new Error("Could not load client for {$integration}.");
        }
    }
}


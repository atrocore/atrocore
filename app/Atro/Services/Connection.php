<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Services;

use Atro\ConnectionType\ConnectionInterface;
use Atro\ConnectionType\TestConnectionInterface;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class Connection extends Base
{
    protected $mandatorySelectAttributeList = ['data'];

    public function testConnection(string $id): bool
    {
        $connectionEntity = $this->getRepository()->get($id);
        if (empty($connectionEntity)) {
            throw new NotFound();
        }

        $connection = $this->createConnection($connectionEntity);
        if ($connection instanceof TestConnectionInterface) {
            return $connection->testConnection($connectionEntity);
        }

        $connection->connect($connectionEntity);

        return true;
    }

    public function connect(Entity $connectionEntity)
    {
        return $this->createConnection($connectionEntity)->connect($connectionEntity);
    }

    public function createConnection($connectionEntity): ConnectionInterface
    {
        $connection = $this->getInjection('container')->get('connectionFactory')->create($connectionEntity);
        if (empty($connection) || !$connection instanceof ConnectionInterface) {
            throw new BadRequest(sprintf($this->exception('connectionFailed'), $this->exception('noSuchType')));
        }

        return $connection;
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

        $this->getRepository()->setDataFields($entity);

        if ($entity->get('type') === 'oauth1') {
            $callbackUrl = $this->getConfig()->get('siteUrl') . '?entryPoint=oauth1Callback&connectionId=' . $this->encryptPassword($entity->get('id')) . '&type=callback';
            $linkUrl = $this->getConfig()->get('siteUrl') . '?entryPoint=oauth1Callback&connectionId=' . $this->encryptPassword($entity->get('id')) . '&type=link';
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
}

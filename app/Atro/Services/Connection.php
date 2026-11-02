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
use Atro\ConnectionType\ConnectionSmtp;
use Atro\ConnectionType\TestConnectionInterface;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Atro\Core\Utils\Encrypter;
use Espo\ORM\Entity;
use Espo\ORM\IEntity;

class Connection extends Base
{
    public function sendTestEmail(string $id, string $toEmail): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $connectionEntity = $this->getSubtypeRecord($id);

        $connection = $this->createConnection($connectionEntity);
        if (!($connection instanceof ConnectionSmtp)) {
            throw new BadRequest($this->exception('onlySmtpConnectionsSupported'));
        }

        return $connection->sendTestEmail($connectionEntity, $toEmail);
    }

    public function testConnection(string $id): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $connectionEntity = $this->getSubtypeRecord($id);

        $connection = $this->createConnection($connectionEntity);
        if ($connection instanceof TestConnectionInterface) {
            return $connection->testConnection($connectionEntity);
        }

        $connection->connect($connectionEntity);

        return true;
    }

    /**
     * The connection types work with the subtype record, it contains the settings of the connection.
     */
    public function connect(Entity $connectionEntity)
    {
        if ($connectionEntity->getEntityName() === 'Connection') {
            $connectionEntity = $connectionEntity->getSubtype();
        }

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

    public function readEntity(string $id, ?string $withRelationships = null): ?IEntity
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return parent::readEntity($id, $withRelationships);
    }

    public function findEntities($params)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return parent::findEntities($params);
    }

    public function updateEntity(string $id, \stdClass $data): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return parent::updateEntity($id, $data);
    }

    public function deleteEntity(string $id): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return parent::deleteEntity($id);
    }

    public function encryptPassword(string $password): string
    {
        return $this->getEncrypter()->encryptPassword($password);
    }

    public function decryptPassword(string $hash): string|bool
    {
        return $this->getEncrypter()->decryptPassword($hash);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('container');
    }

    protected function getSubtypeRecord(string $id): Entity
    {
        $connectionEntity = $this->getRepository()->get($id);
        if (empty($connectionEntity) || empty($subtypeRecord = $connectionEntity->getSubtype())) {
            throw new NotFound();
        }

        return $subtypeRecord;
    }

    protected function exception(string $name, string $scope = 'Connection'): string
    {
        return $this->getInjection('language')->translate($name, 'exceptions', $scope);
    }

    protected function getEncrypter(): Encrypter
    {
        return $this->getInjection('container')->get(Encrypter::class);
    }
}

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

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Services\Subtype;
use Espo\ORM\Entity;
use Espo\ORM\IEntity;

/**
 * The service of the connection subtypes: the connection records are available to administrators only and
 * the passwords are stored encrypted.
 */
class ConnectionSubtype extends Subtype
{
    public function readEntity(string $id, ?string $withRelationships = null): ?IEntity
    {
        $this->checkAdmin();

        return parent::readEntity($id, $withRelationships);
    }

    public function findEntities($params)
    {
        $this->checkAdmin();

        return parent::findEntities($params);
    }

    public function createEntity(\stdClass $attachment): string
    {
        $this->checkAdmin();

        $this->encryptPasswordFields($attachment);

        return parent::createEntity($attachment);
    }

    public function updateEntity(string $id, \stdClass $data): bool
    {
        $this->checkAdmin();

        $this->encryptPasswordFields($data);

        return parent::updateEntity($id, $data);
    }

    protected function checkAdmin(): void
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }
    }

    protected function encryptPasswordFields(\stdClass $inputData): void
    {
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $fieldData) {
            if (($fieldData['type'] ?? null) === 'password' && property_exists($inputData, $field)) {
                $inputData->$field = $this->getConnectionService()->encryptPassword((string)$inputData->$field);
            }
        }
    }

    protected function getConnectionService(): Connection
    {
        return $this->getServiceFactory()->create('Connection');
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        return true;
    }
}

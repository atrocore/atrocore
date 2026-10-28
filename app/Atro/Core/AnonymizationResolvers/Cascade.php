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

namespace Atro\Core\AnonymizationResolvers;

use Atro\Core\Exceptions\Error;
use Espo\Core\ServiceFactory;
use Espo\ORM\Entity;
use Espo\Services\RecordService;

class Cascade extends AbstractAnonymizationResolver
{
    public function supports(Entity $entity, string $field): bool
    {
        return in_array($this->getFieldDefs($entity, $field)['type'] ?? null, self::LINK_TYPES);
    }

    public function resolve(Entity $entity, string $field, array &$processed = []): bool
    {
        $foreignEntityType = $this->getForeignEntityType($entity, $field);
        if (empty($foreignEntityType) || empty($this->getMetadata()->get(['scopes', $foreignEntityType, 'containsPersonalData']))) {
            return false;
        }

        foreach ($this->getLinkedEntities($entity, $field) as $foreign) {
            $this->getService($foreignEntityType)->erasePersonalData($foreign->id, $processed);
        }

        return true;
    }

    protected function getServerFactory(): ServiceFactory
    {
        return $this->getContainer()->get('serviceFactory');
    }

    protected function getService(string $serviceName): RecordService
    {
        if ($this->getServerFactory()->checkExists($serviceName)) {
            return $this->getServerFactory()->create($serviceName);
        }

        throw new Error("Service '$serviceName' does not exist");
    }
}

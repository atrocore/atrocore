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

namespace Atro\Core\Templates\Services;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Espo\ORM\Entity;
use Espo\Services\Record;

class Relation extends Record
{
    public function inheritRelation(\stdClass $data): Entity
    {
        if (!property_exists($data, 'entityType') || !property_exists($data, 'entityId') || !property_exists($data, 'relation') || !property_exists($data, 'relId')) {
            throw new BadRequest('Invalid input data.');
        }

        $mainEntity = $this->getEntityManager()->getRepository($data->entityType)->get($data->entityId);
        if (empty($mainEntity)) {
            throw new NotFound();
        }

        $keySet = $this->getRepository()->getMapper()->getKeys($mainEntity, $data->relation);

        $entity = $this->getRepository()
            ->where([
                $keySet['nearKey']    => $mainEntity->get('id'),
                $keySet['distantKey'] => $data->relId
            ])
            ->findOne();

        if (empty($entity)) {
            throw new NotFound();
        }

        $additionalFields = $this->getRepository()->getAdditionalFieldsNames();
        if (empty($additionalFields)) {
            return $entity;
        }

        $parentsIds = $mainEntity->getLinkMultipleIdList('parents');
        if (empty($parentsIds[0])) {
            return $entity;
        }

        $parentCollection = $this->getRepository()
            ->where([
                $keySet['nearKey']    => $parentsIds,
                $keySet['distantKey'] => $data->relId
            ])
            ->find();

        foreach ($parentCollection as $parentItem) {
            $input = new \stdClass();
            foreach ($additionalFields as $additionalField) {
                $input->{$additionalField} = $parentItem->get($additionalField);
            }

            return $this->updateEntity($entity->get('id'), $input);
        }

        return $entity;
    }
}

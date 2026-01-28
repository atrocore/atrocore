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

namespace Atro\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;


class SelectionItem extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {

        if ($this->getMetadata()->get(['scopes', $entity->get('entityName'), 'selectionDisabled'])) {
            throw new BadRequest(str_replace('%s', $entity->get('entityName'), $this->getLanguage()->translate('selectionDisabledForEntity', 'messages', 'SelectionItem')));
        }

        $record = $this->getEntityManager()->getRepository($entity->get('entityType'))
            ->select(['id'])
            ->where(['id' => $entity->get('entityId')])
            ->findOne();

        if (empty($record)) {
            throw new Error("Record in selection Item not found");
        }

        parent::beforeSave($entity, $options);
    }

    public function save(Entity $entity, array $options = [])
    {
        try {
            return parent::save($entity, $options);
        } catch (NotUnique $e) {
            throw new NotUnique("Selection record already exists");
        }
    }

    public function hasDeletedRecordsToClear(): bool
    {
        return true;
    }

    public function clearDeletedRecords(): void
    {
        parent::clearDeletedRecords();

        $records = $this->getConnection()->createQueryBuilder()
            ->select('entity_name')
            ->distinct()
            ->from('selection_item')
            ->fetchAllAssociative();

        foreach ($records as $record) {
            $entityName = $record['entity_name'];
            $tableName = $this->getConnection()->quoteIdentifier(Util::toUnderScore(lcfirst($entityName)));

            $this->getConnection()->createQueryBuilder()
                ->delete('selection_item', 'ci')
                ->where("ci.entity_name=:entityName AND NOT EXISTS (SELECT 1 FROM $tableName e WHERE e.id=ci.entity_id)")
                ->setParameter('entityName', $entityName)
                ->executeQuery();
        }
    }
}

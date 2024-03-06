<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\Relation;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;

class ExtensibleEnumExtensibleEnumOption extends Relation
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew() && $entity->get('sorting') === null) {
            $entity->set('sorting', time() - (new \DateTime('2023-01-01'))->getTimestamp());
        }

        parent::beforeSave($entity, $options);
    }



    public function updateSortOrder(string $extensibleEnumId, array $extensibleEnumOptionIds): void
    {
        $collection = $this->where(['extensibleEnumId' => $extensibleEnumId, 'extensibleEnumOptionId' => $extensibleEnumOptionIds])->find();
        if (empty($collection[0])) {
            return;
        }

        foreach ($extensibleEnumOptionIds as $k => $id) {
            $sortOrder = (int)$k * 10;
            foreach ($collection as $entity) {
                if ($entity->get('extensibleEnumOptionId') !== (string)$id) {
                    continue;
                }
                $entity->set('sorting', $sortOrder);
                $this->save($entity);
            }
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $this->validateSystemOptions($entity);
        $this->validateOptionsBeforeUnlink($entity);
        parent::beforeRemove($entity, $options);
    }


    public function validateSystemOptions(Entity $entity): void
    {
        foreach ($this->getMetadata()->get(['app', 'extensibleEnumOptions']) as $v) {
            if ($entity->get('extensibleEnumOptionId') === $v['id']) {
                throw new BadRequest(
                    sprintf(
                        $this->getLanguage()
                            ->translate(
                                'extensibleEnumOptionIsSystem',
                                'exceptions',
                                'ExtensibleEnumOption'
                            ),
                        $entity->get('name')
                    )
                );
            }
        }
    }

    public function validateOptionsBeforeUnlink(Entity $entity): void
    {
        foreach ($this->getMetadata()->get(['entityDefs']) as $entityName => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue;
            }
            foreach ($entityDefs['fields'] as $field => $fieldDef) {
                if (
                    empty($fieldDef['notStorable'])
                    && !empty($fieldDef['extensibleEnumId'])
                    && $fieldDef['extensibleEnumId'] === $entity->get('extensibleEnumId')
                ) {
                    $column = Util::toUnderScore($field);

                    $qb = $this->getConnection()
                        ->createQueryBuilder()
                        ->select('t.*')
                        ->from(
                            $this->getConnection()->quoteIdentifier(Util::toUnderScore(lcfirst($entityName))),
                            't'
                        );
                    if ($fieldDef['type'] === 'extensibleEnum') {
                        $qb->where("t.$column = :itemId")
                            ->setParameter('itemId', $entity->get('extensibleEnumOptionId'));
                    } else {
                        $qb->where("t.$column LIKE :itemId")
                            ->setParameter('itemId', "%\"{$entity->get('extensibleEnumOptionId')}\"%");
                    }
                    $qb->andWhere('t.deleted = :false')
                        ->setParameter('false', false, ParameterType::BOOLEAN);

                    $record = $qb->fetchAssociative();

                    if (!empty($record)) {
                        $extensibleEnumOption = $this->getEntityManager()
                            ->getRepository('ExtensibleEnumOption')
                            ->get($entity->get('extensibleEnumOptionId'));

                        throw new BadRequest(
                            sprintf(
                                $this->getLanguage()->translate('extensibleEnumOptionIsUsed', 'exceptions', 'ExtensibleEnumOption'),
                                $extensibleEnumOption->get('name'),
                                $this->getLanguage()->translate($field, 'fields', $extensibleEnumOption->getEntityType()),
                                $entityName,
                                $record['name'] ?? ''
                            )
                        );
                    }
                }

            }
        }
    }


}

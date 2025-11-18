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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;


class SelectionRecord extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        $select = ['id'];

        if ($this->getMetadata()->get(['entityDefs', $entity->get('entityType'), 'fields', 'name'])) {
            $select[] = 'name';
        }

        $record = $this->getEntityManager()->getRepository($entity->get('entityType'))
            ->select($select)
            ->where(['id' => $entity->get('entityId')])
            ->findOne();

        if (empty($record)) {
            throw new Error("Selection record not found");
        }

        $entity->set('name', $record->get('name') ?? $record->get('id'));


        if ($entity->isNew() || $entity->isAttributeChanged('entityId')) {
            if (!$entity->isNew()) {
                $entity->loadLinkMultipleField('selections');
            }
            if (!empty($entity->get('selectionsIds'))) {
                foreach ($entity->get('selectionsIds') as $key => $id) {
                    $exists = $this->getConnection()->createQueryBuilder()
                        ->select('1')
                        ->from('selection_selection_record', 'ssr')
                        ->join('ssr', 'selection_record', 'sr', 'ssr.selection_record_id = sr.id and sr.deleted = :false')
                        ->where('ssr.selection_id = :selectionId and ssr.deleted = :false')
                        ->andWhere('sr.entity_id = :entityId and sr.entity_type = :entityType')
                        ->setParameter('false', false, ParameterType::BOOLEAN)
                        ->setParameter('entityId', $entity->get('entityId'))
                        ->setParameter('entityType', $entity->get('entityType'))
                        ->setParameter('selectionId', $id)
                        ->fetchOne();

                    if (!empty($exists)) {
                        $values = $entity->get('selectionsIds');
                        unset($values[$key]);
                        $entity->set('selectionsIds', array_values($values));
                    }
                }

                if (empty($entity->get('selectionsIds'))) {
                    throw new NotUnique("Selection already exists");
                }
            }
        }

        parent::beforeSave($entity, $options);
    }

    public function save(Entity $entity, array $options = [])
    {
        try {
            return parent::save($entity, $options);
        } catch (NotUnique $e) {
            throw new BadRequest("Selection record already exists");
        }
    }
}

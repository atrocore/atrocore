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

use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Util;
use Atro\Entities\Matching as MatchingEntity;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity as OrmEntity;
use Espo\ORM\EntityCollection;

class Matching extends ReferenceData
{
    public function beforeSave(OrmEntity $entity, array $options = []): void
    {
        if ($entity->isAttributeChanged('entity') && $entity->get('type') === 'duplicate') {
            $entity->set('stagingEntity', $entity->get('entity'));
            $entity->set('masterEntity', $entity->get('entity'));
        }

        parent::beforeSave($entity, $options);
    }

    public function findRelated(OrmEntity $entity, string $link, array $selectParams): EntityCollection
    {
        if ($link === 'matchingRules') {
            $selectParams['whereClause'] = [['matchingId=' => $entity->get('id')]];

            return $this->getEntityManager()->getRepository('MatchingRule')->find($selectParams);
        }

        return parent::findRelated($entity, $link, $selectParams);
    }

    public function countRelated(OrmEntity $entity, string $relationName, array $params = []): int
    {
        if ($relationName === 'matchingRules') {
            $params['offset'] = 0;
            $params['limit'] = \PHP_INT_MAX;

            return count($this->findRelated($entity, $relationName, $params));
        }

        return parent::countRelated($entity, $relationName, $params);
    }

    public function getMatchedRecords(MatchingEntity $matching, string $entityName, string $entityId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $select = ['master_entity', 'score', 't.id', 't.name'];
        if (!empty($this->getConfig()->get('isMultilangActive'))) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $code) {
                $select[] = 't.name_' . strtolower($code);
            }
        }

        $res = $conn->createQueryBuilder()
            ->select(implode(',', $select))
            ->from('matched_record', 'mr')
            ->leftJoin('mr', $conn->quoteIdentifier(Util::toUnderScore($matching->get('masterEntity'))), 't', 'mr.master_entity_id = t.id AND t.deleted = :false')
            ->where('mr.matching_id = :matchingId')
            ->andWhere('mr.staging_entity = :stagingEntity')
            ->andWhere('mr.staging_entity_id = :stagingEntityId')
            ->andWhere('t.id IS NOT NULL')
            ->setParameter('matchingId', $matching->get('id'))
            ->setParameter('stagingEntity', $entityName)
            ->setParameter('stagingEntityId', $entityId)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        return [
            'entityName' => $matching->get('masterEntity'),
            'list' => Util::arrayKeysToCamelCase($res)
        ];
    }
}

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

namespace Atro\Core\AttributeFieldTypes;

use Atro\Core\Container;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Language;
use Atro\Entities\User;
use Espo\Core\ORM\Entity;
use Espo\Core\SelectManagerFactory;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityManager;
use Espo\ORM\IEntity;

abstract class AbstractFieldType implements AttributeFieldTypeInterface
{
    protected Config $config;
    protected User $user;
    protected EntityManager $em;
    protected Language $language;
    protected mixed $selectManagerFactory;

    public function __construct(Container $container)
    {
        $this->config = $container->get('config');
        $this->user = $container->get('user');
        $this->em = $container->get('entityManager');
        $this->language = $container->get('language');
        $this->selectManagerFactory = $container->get('selectManagerFactory');
    }

    public function getWherePart(IEntity $entity, Entity $attribute, array &$item): void
    {
        $attributeId = $attribute->get('id');

        $where = [
            'type' => 'and',
            'value' => [
                [
                    'type' => 'equals',
                    'attribute' => 'attributeId',
                    'value' => $attributeId
                ],
            ]
        ];

        $where['value'][] = $this->convertWhere($entity, $item);
        $attributeValueEntity = "{$entity->getEntityType()}AttributeValue";
        $avRepo = $this->em->getRepository($attributeValueEntity);

        $sp = $this->getSelectManagerFactory()
            ->create($attributeValueEntity)
            ->getSelectParams(['where' => [$where]], true, true);

        $sp['select'] = [lcfirst($entity->getEntityType()) . 'Id'];

        $qb1 = $avRepo->getMapper()->createSelectQueryBuilder($avRepo->get(), $sp);

        $operator = 'IN';
        if (isset($item['type']) && $item['type'] === 'arrayNoneOf') {
            $operator = 'NOT IN';
        }

        $mainTableAlias = $this->em->getRepository($entity->getEntityType())
            ->getMapper()
            ->getQueryConverter()
            ->getMainTableAlias();

        $innerSql = str_replace($mainTableAlias, "t_{$attributeId}", $qb1->getSql());

        $item = [
            'type' => 'innerSql',
            'value' => [
                "sql" => "$mainTableAlias.id $operator ({$innerSql})",
                "parameters" => $qb1->getParameters()
            ]
        ];
    }

    protected function prepareKey(string $nameKey, array $row): string
    {
        if (!empty($localeId = $this->user->get('localeId'))) {
            $currentLocale = $this->em->getEntity('Locale', $localeId);
            if (!empty($currentLocale)) {
                $languageNameKey = $nameKey . '_' . strtolower($currentLocale->get('languageCode'));
                if (!empty($row[$languageNameKey])) {
                    $nameKey = $languageNameKey;
                }
            }
        }

        return $nameKey;
    }

    protected function convertSubquery(IEntity $entity, string $foreignEntity, array &$item): void
    {
        if (empty($item['subQuery'])) {
            return;
        }

        $foreignRepository = $this->em->getRepository($foreignEntity);

        $sp = $this->getSelectManagerFactory()
            ->create($foreignEntity)
            ->getSelectParams(['where' => $item['subQuery']], true, true);

        $sp['select'] = ['id'];

        $qb1 = $foreignRepository->getMapper()->createSelectQueryBuilder($foreignRepository->get(), $sp, true);

        $item['value'] = [
            "innerSql" => [
                "sql" => str_replace(
                    $this->em->getRepository($entity->getEntityType())->getMapper()->getQueryConverter()->getMainTableAlias(),
                    'sbq_' . Util::generateId(), $qb1->getSql()
                ),
                "parameters" => $qb1->getParameters()
            ]
        ];
    }

    protected function convertWhere(IEntity $entity, array $item): array
    {
        return [];
    }


    protected function getSelectManagerFactory(): SelectManagerFactory
    {
        return $this->selectManagerFactory;
    }
}

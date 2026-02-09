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
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Util;
use Atro\Entities\User;
use Doctrine\DBAL\ParameterType;
use Espo\Core\SelectManagerFactory;
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

    public function getWherePart(IEntity $entity, array $attribute, array &$item): void
    {
        $attributeId = $attribute['id'];

        $mainTableAlias = $this->em->getRepository($entity->getEntityType())
            ->getMapper()
            ->getQueryConverter()
            ->getMainTableAlias();

        if (in_array($item['type'], ['isLinked', 'isNotLinked'])) {
            // we select records that are linked or not linked with the attribute
            $operator = $item['type'] === 'isLinked' ? 'EXISTS' : 'NOT EXISTS';
            $tableName = Util::toUnderScore(lcfirst($entity->getEntityType()));
            $attributeAlias = IdGenerator::unsortableId();
            $aliasMiddle = IdGenerator::unsortableId();
            $subQb = $this->em->getConnection()->createQueryBuilder()
                ->select('1')
                ->from("{$tableName}_attribute_value", $aliasMiddle)
                ->join($aliasMiddle, 'attribute', $attributeAlias, "$aliasMiddle.attribute_id = $attributeAlias.id AND $attributeAlias.deleted = :false")
                ->where("$aliasMiddle.{$tableName}_id= $mainTableAlias.id")
                ->andWhere("$aliasMiddle.attribute_id= :{$attributeAlias}AttributeId")
                ->andWhere("$aliasMiddle.deleted = :false")
                ->setParameter("{$attributeAlias}AttributeId", $attributeId)
                ->setParameter("false", false, ParameterType::BOOLEAN);

            $item = [
                'type'  => 'innerSql',
                'value' => [
                    'sql'        => "$operator ({$subQb->getSQL()})",
                    'parameters' => $subQb->getParameters(),
                ]

            ];
            return;
        }

        $where = [
            'type'  => 'and',
            'value' => [
                [
                    'type'      => 'equals',
                    'attribute' => 'attributeId',
                    'value'     => $attributeId
                ],
            ]
        ];

        $where['value'][] = $this->convertWhere($entity, $attribute, $item);
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

        $innerSql = str_replace($mainTableAlias, IdGenerator::unsortableId(), $qb1->getSql());

        $item = [
            'type'  => 'innerSql',
            'value' => [
                "sql"        => "$mainTableAlias.id $operator ($innerSql)",
                "parameters" => $qb1->getParameters()
            ]
        ];

        if ($operator === 'NOT IN') {
            // we ensure that the results are also linked to the attributes
            $item = [
                'type'  => 'and',
                'value' => [
                    $item,
                    [
                        'type'        => 'isLinked',
                        'attribute'   => $attributeId,
                        'isAttribute' => true
                    ]
                ]
            ];
        }
    }

    protected function prepareKey(string $nameKey, array $row): string
    {
        if (!empty($localeId = Language::detectLocale($this->config, $this->user))) {
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
                "sql"        => str_replace(
                    $this->em->getRepository($entity->getEntityType())->getMapper()->getQueryConverter()->getMainTableAlias(),
                    'sbq_' . IdGenerator::unsortableId(), $qb1->getSql()
                ),
                "parameters" => $qb1->getParameters()
            ]
        ];
    }

    protected function convertWhere(IEntity $entity, array $attribute, array $item): array
    {
        return [];
    }

    protected function prepareConditionalProperties(array $row): ?array
    {
        $keyMap = [
            'conditional_required'        => 'required',
            'conditional_visible'         => 'visible',
            'conditional_protected'       => 'protected',
            'conditional_read_only'       => 'readOnly',
            'conditional_disable_options' => 'disableOptions'
        ];

        $conditions = [];
        foreach ($keyMap as $key => $property) {
            if (!empty($row[$key])) {
                $value = @json_decode($row[$key], true);
                if (!empty($value)) {
                    $conditions[$property] = $value;
                }
            }
        }

        if (empty($conditions)) {
            return null;
        }

        return $conditions;
    }

    protected function getSelectManagerFactory(): SelectManagerFactory
    {
        return $this->selectManagerFactory;
    }
}

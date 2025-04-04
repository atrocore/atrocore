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

namespace Atro\Core;

use Atro\Core\AttributeFieldTypes\AttributeFieldTypeInterface;
use Atro\Core\Exceptions\Error;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\IEntity;

class AttributeFieldConverter
{
    protected Metadata $metadata;
    protected Config $config;
    protected Connection $conn;
    private Container $container;

    public function __construct(Container $container)
    {
        $this->metadata = $container->get('metadata');
        $this->config = $container->get('config');
        $this->conn = $container->get('connection');
        $this->container = $container;
    }

    public function putAttributesToEntity(IEntity $entity): void
    {
        if (!$this->metadata->get("scopes.{$entity->getEntityType()}.hasAttribute")) {
            return;
        }

        $tableName = Util::toUnderScore(lcfirst($entity->getEntityType()));

        $select = 'a.*, av.id as av_id, av.bool_value, av.date_value, av.datetime_value, av.int_value, av.int_value1, av.float_value, av.float_value1, av.varchar_value, av.text_value, av.reference_value, av.json_value, f.name as file_name';
        if (!empty($this->config->get('isMultilangActive'))) {
            foreach ($this->config->get('inputLanguageList', []) as $code) {
                $select .= ',av.varchar_value_' . strtolower($code);
                $select .= ',av.text_value_' . strtolower($code);
            }
        }

        $res = $this->conn->createQueryBuilder()
            ->select($select)
            ->from("{$tableName}_attribute_value", 'av')
            ->leftJoin('av', $this->conn->quoteIdentifier('attribute'), 'a', 'a.id=av.attribute_id')
            ->leftJoin('av', $this->conn->quoteIdentifier('file'), 'f', 'f.id=av.reference_value AND a.type=:fileType')
            ->where('av.deleted=:false')
            ->andWhere('a.deleted=:false')
            ->andWhere("av.{$tableName}_id=:id")
            ->orderBy('a.sort_order', 'ASC')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('id', $entity->get('id'))
            ->setParameter('fileType', 'file')
            ->fetchAllAssociative();

        $attributesDefs = [];

        foreach ($res as $row) {
            $id = $row['av_id'];
            $name = "attr_{$id}";

            $this->getFieldType($row['type'])->convert($entity, $id, $name, $row, $attributesDefs);
        }

        $entity->set('attributesDefs', $attributesDefs);
        $entity->setAsFetched();
    }

    protected function getFieldType(string $type): AttributeFieldTypeInterface
    {
        $className = "\\Atro\\Core\\AttributeFieldTypes\\" . ucfirst($type) . "Type";
        if (!class_exists($className)) {
            $className = "\\Atro\\Core\\AttributeFieldTypes\\VarcharType";
        }

        if (!is_a($className, AttributeFieldTypeInterface::class, true)) {
            throw new Error("No such attribute field type '$type'.");
        }

        return $this->container->get($className);
    }
}
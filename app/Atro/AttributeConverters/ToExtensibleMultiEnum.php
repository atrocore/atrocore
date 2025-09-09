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

namespace Atro\AttributeConverters;

use Atro\Core\Utils\Util;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class ToExtensibleMultiEnum implements AttributeConverterInterface
{
    public const LIMIT = 5000;

    protected Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function convert(Entity $attribute): void
    {

        $tableName = Util::toUnderScore(lcfirst($attribute->get('entityId')));

        while (true) {
            $res = $this->connection->createQueryBuilder()
                ->select('id, varchar_value')
                ->from("{$tableName}_attribute_value")
                ->where('attribute_id = :attributeId')
                ->andWhere('varchar_value IS NOT NULL')
                ->setParameter('attributeId', $attribute->get('id'))
                ->setFirstResult(0)
                ->setMaxResults(self::LIMIT)
                ->fetchAllAssociative();

            if (empty($res)) {
                break;
            }

            foreach ($res as $v) {
                $this->connection->createQueryBuilder()
                    ->update("{$tableName}_attribute_value")
                    ->set('json_value', ':jsonValue')
                    ->set('varchar_value', ':nullValue')
                    ->setParameter('jsonValue', $this->prepareJsonValue($v['varchar_value']))
                    ->setParameter('nullValue', null, ParameterType::NULL)
                    ->where('id = :id')
                    ->setParameter('id', $v['id'])
                    ->executeQuery();
            }
        }

        $offset = 0;
        while (true) {
            $res = $this->connection->createQueryBuilder()
                ->select('id, data')
                ->from('classification_attribute')
                ->where('attribute_id=:attributeId')
                ->setParameter('attributeId', $attribute->get('id'))
                ->setFirstResult($offset)
                ->setMaxResults(self::LIMIT)
                ->fetchAllAssociative();

            $offset = $offset + self::LIMIT;

            if (empty($res)) {
                break;
            }

            foreach ($res as $v) {
                $data = @json_decode((string)$v['data'], true);
                if (!is_array($data)) {
                    $data = [];
                }

                if (!isset($data['default'])) {
                    continue;
                }

                unset($data['default']);

                $this->connection->createQueryBuilder()
                    ->update('classification_attribute')
                    ->set('data', ':data')
                    ->where('id=:id')
                    ->setParameter('id', $v['id'])
                    ->setParameter('data', json_encode($data))
                    ->executeQuery();
            }
        }
    }

    protected function prepareJsonValue($varcharValue): ?string
    {
        if ($varcharValue === null) {
            return null;
        }

        return "[\"{$varcharValue}\"]";
    }
}
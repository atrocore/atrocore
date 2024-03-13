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

namespace Atro\Core\Factories;

use Atro\Core\Container;
use Atro\Core\Utils\Database\DBAL\FieldTypes\JsonArrayType;
use Atro\Core\Utils\Database\DBAL\FieldTypes\JsonObjectType;
use Doctrine\DBAL\Types\Type;

class Connection implements FactoryInterface
{
    protected static array $drivers
        = [
            'pdo_pgsql' => '\Atro\Core\Utils\Database\DBAL\Driver\PDO\PgSQL\Driver',
        ];

    public function create(Container $container)
    {
        return self::createConnection($container->get('config')->get('database'));
    }

    public static function createConnection(array $params): \Doctrine\DBAL\Connection
    {
        if (!empty(self::$drivers[$params['driver']])) {
            $params['driverClass'] = self::$drivers[$params['driver']];
        }

        if(!Type::hasType('jsonArray')){
            Type::addType('jsonArray', JsonArrayType::class);
        }

        if(!Type::hasType('jsonObject')){
            Type::addType('jsonObject', JsonObjectType::class);
        }

        return \Doctrine\DBAL\DriverManager::getConnection($params, new \Doctrine\DBAL\Configuration());
    }
}

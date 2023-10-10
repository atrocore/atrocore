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

namespace Atro\Core\Factories;

use Atro\Core\Container;

class Connection implements FactoryInterface
{
    protected static array $drivers
        = [
//            'pdo_mysql' => '\Espo\Core\Utils\Database\DBAL\Driver\PDOMySql\Driver',
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

        return \Doctrine\DBAL\DriverManager::getConnection($params, new \Doctrine\DBAL\Configuration());
    }
}

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

namespace Atro\Core\Utils\Database\DBAL\Driver\PDO\MySQL;

use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Platforms\AbstractPlatform;

final class Driver extends AbstractMySQLDriver
{
    public function connect(array $params)
    {
        return (new \Doctrine\DBAL\Driver\PDO\MySQL\Driver())->connect($params);
    }

    public function getSchemaManager(\Doctrine\DBAL\Connection $conn, AbstractPlatform $platform)
    {
        return new \Atro\Core\Utils\Database\DBAL\Schema\MySQLSchemaManager($conn, $platform);
    }
}

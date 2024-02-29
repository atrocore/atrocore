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

namespace Atro\ConnectionType;

use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class ConnectionMysql extends AbstractConnection implements ConnectionInterface
{
    public function connect(Entity $connection)
    {
        try {
            $port = !empty($connection->get('port')) ? ';port=' . $connection->get('port') : '';
            $dsn = 'mysql:host=' . $connection->get('host') . $port . ';dbname=' . $connection->get('dbName') . ';';
            $result = new \PDO(
                $dsn,
                $connection->get('user'),
                $this->decryptPassword($connection->get('password')),
                [
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION
                ]
            );
        } catch (\PDOException $e) {
            throw new BadRequest(sprintf($this->exception('connectionFailed'), $e->getMessage()));
        }

        return $result;
    }
}

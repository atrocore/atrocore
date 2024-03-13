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

namespace Atro\ConnectionType;

use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class ConnectionMsql extends AbstractConnection implements ConnectionInterface
{
    public function connect(Entity $connection)
    {
        if (!function_exists('sqlsrv_connect')) {
            throw new BadRequest($this->exception('sqlsrvMissing'));
        }

        $serverName = "{$connection->get('host')},{$connection->get('port')}";
        $connectionInfo = [
            "Database"               => $connection->get('dbName'),
            "Uid"                    => $connection->get('user'),
            "PWD"                    => $this->decryptPassword($connection->get('password')),
            "LoginTimeout"           => 5
        ];

        if (!empty($connection->get('additionalParameters'))) {
            foreach (explode(";", $connection->get('additionalParameters')) as $part) {
                if (!empty($part)) {
                    $values = explode("=", $part);
                    if (!empty($values) && count($values) == 2) {
                        $connectionInfo[$values[0]] = $values[1];
                    }
                }
            }
        }

        $result = \sqlsrv_connect($serverName, $connectionInfo);

        if ($result === false) {
            throw new BadRequest(
                sprintf($this->exception('connectionFailed'), implode(', ', array_column(\sqlsrv_errors(), 'message')))
            );
        }

        return $result;
    }
}

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

class ConnectionOracle extends AbstractConnection implements ConnectionInterface
{
    public function connect(Entity $connection)
    {
        if (!extension_loaded('pdo_oci')) {
            throw new BadRequest($this->exception('pdoOciMissing'));
        }

        try {
            $port = !empty($connection->get('port')) ? $connection->get('port') : '1521';
            $dsn = 'oci:dbname=//' . $connection->get('host') . ':' . $port . '/' . $connection->get('dbName') . ';charset=AL32UTF8';
            // an empty or undecryptable password is false, the login fails then with an error of Oracle
            $password = (string)$this->decryptPassword((string)$connection->get('password'));
            $result = new \PDO($dsn, $connection->get('user'), $password);
            $result->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // the dates are returned in the ISO format instead of the one of the server, e.g. "11-NOV-26"
            $result->exec(
                "ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'"
                . " NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:SS'"
                . " NLS_TIMESTAMP_TZ_FORMAT = 'YYYY-MM-DD HH24:MI:SS TZH:TZM'"
            );
        } catch (\PDOException $e) {
            throw new BadRequest(sprintf($this->exception('connectionFailed'), $e->getMessage()));
        }

        return $result;
    }
}

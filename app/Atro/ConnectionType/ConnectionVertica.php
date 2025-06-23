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

class ConnectionVertica extends AbstractConnection implements ConnectionInterface
{
    public function connect(Entity $connectionEntity)
    {
        if (!extension_loaded('odbc')) {
            throw new BadRequest($this->exception('ODBC extension is not loaded'));
        }

        $driver = $this->container->get('config')->get('verticaDriver', '/opt/vertica/lib64/libverticaodbc.so');

        $dsn = "Driver={$driver};Servername={$connectionEntity->get('host')};Database={$connectionEntity->get('dbName')};Port={$connectionEntity->get('port')};" .
            "UserName={$connectionEntity->get('user')};Password={$this->decryptPassword($connectionEntity->get('password'))};";

        $conn = odbc_connect($dsn, '', '');

        if ($conn === false) {
            throw new BadRequest(sprintf($this->exception('connectionFailed'), odbc_error() . ': ' . odbc_errormsg()));
        }

        return $conn;
    }
}

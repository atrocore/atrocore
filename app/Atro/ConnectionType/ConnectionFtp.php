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
use Lazzard\FtpClient\Connection\FtpConnection;
use Lazzard\FtpClient\Connection\FtpSSLConnection;
use Lazzard\FtpClient\Config\FtpConfig;
use Lazzard\FtpClient\FtpClient;

class ConnectionFtp extends AbstractConnection implements ConnectionInterface
{
    public function connect(Entity $connection): FtpClient
    {
        $port = 21;
        if (!empty($connection->get('port'))) {
            $port = $connection->get('port');
        }

        $className = FtpConnection::class;
        if (!empty($connection->get('ftpSsl'))) {
            $className = FtpSSLConnection::class;
        }

        try {
            $connection = new $className($connection->get('host'), $connection->get('user'), $this->decryptPassword($connection->get('password')), $port);
            $connection->open();

            $config = new FtpConfig($connection);
            $config->setPassive(true);

            $client = new FtpClient($connection);

        } catch (\Throwable $e) {
            throw new BadRequest(sprintf($this->exception('connectionFailed'), $e->getMessage()));
        }

        return $client;
    }
}

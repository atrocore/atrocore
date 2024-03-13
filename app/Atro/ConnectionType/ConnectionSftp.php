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

class ConnectionSftp extends AbstractConnection implements ConnectionInterface
{
    public function connect(Entity $connection)
    {
        try {
            $result = new \phpseclib3\Net\SFTP($connection->get('host'), empty($connection->get('port')) ? 22 : (int)$connection->get('port'));
            $login = $result->login($connection->get('user'), $this->decryptPassword($connection->get('password')));
        } catch (\Throwable $e) {
            throw new BadRequest(sprintf($this->exception('connectionFailed'), $e->getMessage()));
        }
        if ($login === false) {
            throw new BadRequest(sprintf($this->exception('connectionFailed'), 'Wrong auth data.'));
        }

        return $result;
    }
}

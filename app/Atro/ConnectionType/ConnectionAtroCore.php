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

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class ConnectionAtroCore extends ConnectionHttp implements ConnectionInterface, HttpConnectionInterface
{
    public function connect(Entity $connection)
    {
        $response = $this->request("{$this->connectionEntity->get('atrocoreUrl')}/api/v1/User?offset=0&maxSize=1");
        if (is_array(@json_decode($response->getOutput(), true))) {
            return true;
        }

        throw new BadRequest('Invalid credentials');
    }

    protected function getHeaders(): array
    {
        return [
            "Content-Type: application/json",
            "Authorization-Token: {$this->decryptPassword($this->connectionEntity->get('atrocoreToken'))}"
        ];
    }
}

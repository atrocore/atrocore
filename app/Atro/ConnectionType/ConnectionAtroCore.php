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

class ConnectionAtroCore extends ConnectionHttp implements ConnectionInterface, HttpConnectionInterface
{
    public function connect(Entity $connection)
    {
        $response = $this->request(rtrim($this->connectionEntity->get('atrocoreUrl'), '/') . "/api/v1/User?offset=0&maxSize=1");
        if (is_array(@json_decode($response->getOutput(), true))) {
            return true;
        }

        throw new BadRequest('Invalid credentials');
    }

    public function generateUrlForEntity(string $entityName): string
    {
        return rtrim($this->connectionEntity->get('atrocoreUrl'), '/') . "/api/v1/$entityName?maxSize={{ limit }}&offset={{ offset }}&sortBy=createdAt&asc=false{% if payload.entityId is not empty %}&where[0][type]=equals&where[0][attribute]=id&where[0][value]={{ payload.entityId }}{% endif %}";
    }

    protected function getHeaders(): array
    {
        return [
            "Content-Type: application/json",
            "Authorization-Token: {$this->decryptPassword($this->connectionEntity->get('atrocoreToken'))}"
        ];
    }
}

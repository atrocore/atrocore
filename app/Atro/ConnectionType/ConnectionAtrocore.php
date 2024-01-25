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

class ConnectionAtrocore extends AbstractConnection implements ConnectionInterface, HttpConnectionInterface
{
    public function connect(Entity $connection)
    {
        $output = $this->request($connection, "{$connection->get('atrocoreUrl')}/api/v1/User");
        if (is_array(@json_decode($output, true))) {
            return true;
        }

        throw new BadRequest('Invalid credentials');
    }

    public function request(?Entity $connection, string $url, string $method = 'GET', array $headers = [], string $body = null): string
    {
        $connectionHeaders = [
            "Content-Type: application/json",
            "Authorization-Token: {$this->decryptPassword($connection->get('atrocoreToken'))}"
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, $connectionHeaders));
        $output = curl_exec($ch);
        if ($output === false) {
            throw new BadRequest('Curl error: ' . curl_error($ch));
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!in_array($httpCode, [200, 201, 204])) {
            throw new BadRequest("Response Code: $httpCode Body: $output");
        }

        return $output;
    }
}

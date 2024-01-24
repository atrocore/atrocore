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

class ConnectionAtrocore extends AbstractConnection implements HttpConnectionInterface
{
    public function connect(Entity $connection)
    {
        $ch = curl_init("{$connection->get('atrocoreUrl')}/api/v1/User");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders($this->getConnectionData($connection)));
        $output = curl_exec($ch);
        if ($output === false) {
            throw new BadRequest('AtroCore curl error: ' . curl_error($ch));
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $output = json_decode($output, true);

        if (!empty($output)) {
            if ($httpCode == 200) {
                return $output;
            } else {
                throw new BadRequest($output['error']['message']);
            }
        }

        throw new BadRequest('Invalid credentials');
    }

    public function getConnectionData(Entity $connection): array
    {
        return [
            'authToken' => $this->decryptPassword($connection->get('atrocoreToken'))
        ];
    }

    public function getHeaders(array $connectionData): array
    {
        $headers = ["Content-Type: application/json"];
        if (!empty($connectionData['authToken'])) {
            $headers[] = "Authorization-Token: {$connectionData['authToken']}";
        }

        return $headers;
    }
}

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

use Atro\DTO\HttpResponseDTO;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class ConnectionHttp extends AbstractConnection implements ConnectionInterface, HttpConnectionInterface
{
    public function connect(Entity $connection)
    {
        return true;
    }

    public function request(string $url, string $method = 'GET', array $headers = [], string $body = null): HttpResponseDTO
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, $this->getHeaders()));
        $output = curl_exec($ch);
        if ($output === false) {
            throw new BadRequest('Curl error: ' . curl_error($ch));
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new BadRequest("Response Code: $httpCode Body: $output");
        }

        return new HttpResponseDTO($httpCode, $output);
    }

    protected function getHeaders(): array
    {
        return [];
    }
}

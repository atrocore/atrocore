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

use Atro\DTO\HttpResponseDTO;
use Atro\Core\Exceptions\BadRequest;

class ConnectionHttp extends AbstractConnection implements HttpConnectionInterface
{
    public function request(string $url, string $method = 'GET', array $headers = [], string $body = null, bool $validate = true): HttpResponseDTO
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($method !== 'GET' && !empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_values(array_unique(array_merge($headers, $this->getHeaders()))));
        if (isset($this->connectionEntity) && $this->connectionEntity->get('verifySsl') === false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // nosemgrep:php.lang.security.curl-ssl-verifypeer-off.curl-ssl-verifypeer-off
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $rawOutput = curl_exec($ch);
        if ($rawOutput === false) {
            throw new BadRequest('Curl error: ' . curl_error($ch));
        }
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $responseHeaders = [];
        foreach (explode("\r\n", substr($rawOutput, 0, $headerSize)) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $responseHeaders[trim($key)] = trim($value);
            }
        }
        $output = substr($rawOutput, $headerSize);

        if (!empty($validate) && ($httpCode < 200 || $httpCode >= 300)) {
            $this->processError($httpCode, $output);
        }

        return new HttpResponseDTO($httpCode, $output, $responseHeaders);
    }

    public function processError(int $httpCode, ?string $output)
    {
        throw new BadRequest("Response Code: $httpCode Body: $output");
    }

    public function generateUrlForEntity(string $entityName): string
    {
        return "https://unknown.domain/api/$entityName?limit={{ limit }}&offset={{ offset }}{% if payload.entityId is not empty %}&entityId={{ payload.entityId }}{% endif %}";
    }

    protected function getHeaders(): array
    {
        return [];
    }
}

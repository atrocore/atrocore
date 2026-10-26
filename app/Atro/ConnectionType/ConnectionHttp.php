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

use Atro\DTOs\HttpResponseDTO;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Utils\Config;

class ConnectionHttp extends AbstractConnection implements HttpConnectionInterface
{
    public function request(string $url, string $method = 'GET', array $headers = [], string $body = null, bool $validate = true): HttpResponseDTO
    {
        return $this->send($url, $method, $headers, $body, $validate, true);
    }

    /**
     * The request with the settings of the connection, such as the SSL check and the HTTP Basic authentication.
     * $withConnectionHeaders is false for the call that gets the credentials of the connection (a login): the
     * headers of those connections are built from its result.
     */
    protected function send(string $url, string $method, array $headers, ?string $body, bool $validate, bool $withConnectionHeaders): HttpResponseDTO
    {
        if ($withConnectionHeaders) {
            $headers = array_merge($headers, $this->getHeaders());
        }

        $headers = $this->addBasicAuthorization(array_values(array_unique($headers)));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$this->getConfig()->get('httpRequestTimeout', 120));
        if ($method !== 'GET' && !empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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

        // a header that is sent more than once (Set-Cookie) keeps all its values, joined with ';'
        $responseHeaders = [];
        $names = [];
        foreach (explode("\r\n", substr($rawOutput, 0, $headerSize)) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $key = trim($key);
                $lowerKey = strtolower($key);

                if (isset($names[$lowerKey])) {
                    $responseHeaders[$names[$lowerKey]] .= ';' . trim($value);
                } else {
                    $names[$lowerKey] = $key;
                    $responseHeaders[$key] = trim($value);
                }
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

    /**
     * Adds the HTTP Basic authentication of the connection, when it is switched on, unless the request has an
     * Authorization header of its own, which then is the one that is sent.
     */
    protected function addBasicAuthorization(array $headers): array
    {
        if (!isset($this->connectionEntity) || empty($this->connectionEntity->get('httpBasicEnabled'))) {
            return $headers;
        }

        foreach ($headers as $header) {
            if (preg_match('/^\s*authorization\s*:/i', (string)$header)) {
                return $headers;
            }
        }

        $password = $this->decryptPassword((string)$this->connectionEntity->get('httpBasicPassword'));

        $headers[] = 'Authorization: Basic ' . base64_encode($this->connectionEntity->get('httpBasicUsername') . ':' . ($password === false ? '' : $password));

        return $headers;
    }

    protected function getHeaders(): array
    {
        return [];
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }
}

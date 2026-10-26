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

use Atro\Core\Twig\Twig;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class ConnectionCookie extends ConnectionHttp implements ConnectionInterface
{
    public function buildBody(Entity $connection): array
    {
        $payload = $this->getTwig()->renderTemplate($connection->get('payload'), [
            'username' => $connection->get('user'),
            'password' => $this->decryptPassword($connection->get('password')),
        ]);

        $payload = json_decode($payload, true);

        if (empty($payload)) {
            throw new BadRequest(sprintf($this->exception('connectionFailed'), 'Invalid Json payload.'));
        }

        return $payload;
    }

    public function connect(Entity $connectionEntity): array
    {
        $body = $this->buildBody($connectionEntity);
        $url = $connectionEntity->get('loginUrl');

        $response = null;
        $httpCode = 0;
        try {
            $response = $this->send($url, 'POST', ['Content-Type: application/json'], json_encode($body), false, false);
            $httpCode = $response->getCode();
        } catch (BadRequest $e) {
            $message = $e->getMessage();
        }

        if (!empty($response) && $httpCode == 200) {
            $cookies = $this->extractCookiesFromHeaders($response->getHeaders());
            if (!empty($cookies)) {
                return [
                    'cookie' => $cookies
                ];
            }
        }

        throw new BadRequest(sprintf($this->exception('connectionFailed'), $message ?? 'Connection failed.'));
    }

    /**
     * The values of all Set-Cookie headers are joined with ';' in the headers of the response, so the attributes of
     * the cookies (Path, Expires, ...) are left out and what remains are the cookies as name=value.
     */
    public function extractCookiesFromHeaders(array $headers): string
    {
        $setCookie = '';
        foreach ($headers as $name => $value) {
            if (strtolower((string)$name) === 'set-cookie') {
                $setCookie = (string)$value;
                break;
            }
        }

        $attributes = ['expires', 'max-age', 'domain', 'path', 'secure', 'httponly', 'samesite', 'partitioned', 'priority'];

        $cookies = [];
        foreach (explode(';', $setCookie) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || !str_contains($segment, '=')) {
                continue;
            }

            if (in_array(strtolower(explode('=', $segment, 2)[0]), $attributes, true)) {
                continue;
            }

            $cookies[] = $segment;
        }

        return join('; ', $cookies);
    }

    public function extractCookiesFromResponse(string $result): string
    {
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
        $cookies = array();
        foreach ($matches[1] as $item) {
            $cookies[] = $item;
        }

        return join("; ", $cookies);
    }

    public function getHeaders(): array
    {
        $connectionData = $this->connect($this->connectionEntity);

        return ["Cookie: {$connectionData['cookie']}"];
    }

    private function getTwig(): Twig
    {
        return $this->container->get('twig');
    }
}

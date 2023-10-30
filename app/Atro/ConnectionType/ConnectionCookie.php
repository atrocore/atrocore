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

use Atro\Core\Twig\Twig;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class ConnectionCookie extends AbstractConnection
{
    public function buildBody(Entity $connection): array
    {
        $payload = $this->getTwig()->renderTemplate($connection->get('payload'), [
            'username' => $connection->get('user'),
            'password' => $this->decryptPassword($connection->get('password')),
        ]);

        $payload = json_decode($payload, true);

        if (empty($payload)) {
            throw new BadRequest(sprintf($this->exception('invalidJson'), 'Connection failed.'));
        }

        return $payload;
    }

    public function connect(Entity $connection): array
    {
        $body = $this->buildBody($connection);
        $url = $connection->get('loginUrl');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!empty($response) && $httpCode == 200) {
            $cookies = $this->extractCookiesFromResponse($response);
            if (!empty($cookies)) {
                return [
                    'cookie' => $cookies
                ];
            }
        }

        throw new BadRequest(sprintf($this->exception('connectionFailed'), 'Connection failed.'));
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

    public function getHeaders(array $connectionData): array
    {
        return ["Cookie: {$connectionData['cookie']}"];
    }

    private function getTwig(): Twig
    {
        return $this->container->get('twig');
    }
}

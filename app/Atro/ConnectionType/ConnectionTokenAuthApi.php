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
use Atro\Core\Twig\Twig;
use Espo\ORM\Entity;

class ConnectionTokenAuthApi extends ConnectionHttp implements ConnectionInterface
{
    public function connect(Entity $connectionEntity)
    {
        $body = $this->buildBody($connectionEntity);

        $response = null;
        $httpCode = 0;
        try {
            $result = $this->send($connectionEntity->get('loginUrl'), 'POST', ['Content-Type: application/json'], json_encode($body), false, false);
            $response = $result->getOutput();
            $httpCode = $result->getCode();
        } catch (BadRequest $e) {
            $message = $e->getMessage();
        }

        if (!empty($response) && $httpCode == 200) {
            return @json_decode($response, true) ?? $response;
        }

        $GLOBALS['log']->error('Connection Token Auth Failed: ' . ($message ?? $response));
        throw new BadRequest(sprintf($this->exception('connectionFailed'), $message ?? 'Connection failed.'));
    }


    public function getHeaders(): array
    {
        $connectionData = $this->connect($this->connectionEntity);

        $headerString = $this->getTwig()->renderTemplate($this->connectionEntity->get('headers'), [
            'response' => $connectionData
        ]);

        $decoded = @json_decode($headerString, true) ?? [];

        $headers = [];
        foreach ($decoded as $key => $value) {
            $headers[] = is_string($key) ? "$key: $value" : $value;
        }

        return $headers;
    }

    protected function buildBody(Entity $connection): array
    {
        $payload = $this->getTwig()->renderTemplate($connection->get('payload'), [
            'username' => $connection->get('user'),
            'password' => $this->decryptPassword($this->connectionEntity->get('password')),
        ]);

        $payload = json_decode($payload, true);

        if (empty($payload)) {
            throw new BadRequest(sprintf($this->exception('connectionFailed'), 'Invalid Json payload.'));
        }

        return $payload;
    }

    private function getTwig(): Twig
    {
        return $this->container->get('twig');
    }
}

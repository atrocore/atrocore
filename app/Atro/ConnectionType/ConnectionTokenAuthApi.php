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
    public function connect(Entity $connection)
    {
        $body = $this->buildBody($connection);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$connection->get('loginUrl'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = curl_exec($ch);
        if ($response === false) {
            $message = curl_error($ch);
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);


        if (!empty($response) && $httpCode == 200) {
            return @json_decode($response, true);
        }

        $GLOBALS['log']->error('Connection Token Auth Failed: '.$response);
        throw new BadRequest(sprintf($this->exception('connectionFailed'), 'Connection failed.'));
    }


    public function getHeaders(): array
    {
        $connectionData = $this->connect($this->connectionEntity);

        $headerString = $this->getTwig()->renderTemplate($this->connectionEntity->get('headers'), [
            'response' => $connectionData
        ]);

        return @json_decode($headerString, true) ?? [];
    }

    protected function buildBody(Entity $connection): array
    {
        $payload = $this->getTwig()->renderTemplate($connection->get('payload'), [
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

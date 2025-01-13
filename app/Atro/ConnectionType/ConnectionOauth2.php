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
use Atro\Core\DataManager;
use Espo\ORM\Entity;

class ConnectionOauth2 extends ConnectionHttp implements ConnectionInterface
{
    public function connect(Entity $connection)
    {
        $body = ['grant_type' => $connection->get('oauthGrantType')];

        switch ($body['grant_type']) {
            case 'client_credentials':
                $body['client_id'] = $connection->get('oauthClientId');
                $body['client_secret'] = $this->decryptPassword($connection->get('oauthClientSecret'));
                break;
            default:
                throw new BadRequest(sprintf($this->exception('connectionFailed'), 'Connection failed.'));
        }

        $dataManager = $this->getDataManager();

        $key = $this->getCacheKey();
        $hash = md5($connection->get('oauthUrl') . '-' . json_encode($body));

        $data = $dataManager->getCacheData($key);
        if (!empty($data['expires_at']) && !empty($data['hash']) &&
            $data['hash'] === $hash && (time() < $data['expires_at'])) {
            return $data;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $connection->get('oauthUrl'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!empty($response)) {
            $result = @json_decode($response, true);
            if (isset($result['access_token'])) {
                // save in cache
                if (!empty($result['expires_in'])) {
                    $result['expires_at'] = time() + ((int)$result['expires_in']) - 60;
                    $result['hash'] = $hash;
                    $dataManager->setCacheData($key, $result);
                }
                return $result;
            }
        }

        throw new BadRequest(sprintf($this->exception('connectionFailed'), 'Connection failed.'));
    }

    public function processError(int $httpCode, ?string $output)
    {
        if ($httpCode === 401) {
            $this->getDataManager()->removeCacheData($this->getCacheKey());
        }
        parent::processError($httpCode, $output);
    }

    public function getCacheKey(): string
    {
        return 'cron_conn_' . $this->connectionEntity->get('id');
    }

    public function getHeaders(): array
    {
        $connectionData = $this->connect($this->connectionEntity);

        return ["Authorization: {$connectionData['token_type']} {$connectionData['access_token']}"];
    }

    public function getDataManager(): DataManager
    {
        return $this->container->get('dataManager');
    }
}

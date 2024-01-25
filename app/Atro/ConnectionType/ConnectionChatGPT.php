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

class ConnectionChatGPT extends AbstractConnection
{
    public function getConnectionData(Entity $connection): array
    {
        $orgId = $connection->get('openAiOrganizationId');
        $apiKey = $connection->get('openAiApiKey');

        return [
            'organizationId' => $orgId,
            'apiKey'         => $this->decryptPassword($apiKey)
        ];
    }

    public function connect(Entity $connection): array
    {
        $ch = curl_init("https://api.openai.com/v1/models/{$connection->get('openAiModel')}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders($this->getConnectionData($connection)));
        $output = curl_exec($ch);
        if ($output === false) {
            throw new BadRequest('Chatgpt curl error: ' . curl_error($ch));
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

    public function getHeaders(array $connectionData): array
    {
        $headers = ["Content-Type: application/json", "Authorization: Bearer {$connectionData['apiKey']}"];
        if (!empty($connectionData['organizationId'])) {
            $headers[] = "OpenAI-Organization: {$connectionData['organizationId']}";
        }
        return $headers;
    }
}

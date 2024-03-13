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

namespace Atro\Core\ChatGpt;

use Atro\ConnectionType\ConnectionChatgpt;
use Atro\Core\Container;
use Atro\Core\Twig\AbstractTwigFilter;
use Atro\Core\Twig\AbstractTwigFunction;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Connection;

class ChatGptClient
{
    protected Container $container;
    protected ConnectionChatgpt $connection;

    protected static string $completionUrl = "https://api.openai.com/v1/chat/completions";

    public function __construct(Container $container, ConnectionChatgpt $connection)
    {
        $this->container = $container;
        $this->connection = $connection;
    }

    public function createCompletion(string $prompt): ?string
    {
        /**@var $connectionEntity Connection * */
        $connectionEntity = $this->getEntityManager()->getRepository('Connection')->where(['type=' => 'chatgpt'])->findOne();
        if (empty($connectionEntity)) {
            throw new Error("No Chatgpt connection is configured");
        }

        $connectionData = $this->connection->getConnectionData($connectionEntity);

        $data = [
            "model"       => $connectionEntity->get('openAiModel'),
            "messages"    => [["role" => "user", "content" => $prompt]],
            "temperature" => $connectionEntity->get('openAiTemperature')
        ];
        $result = $this->makeRequest(self::$completionUrl, $data, $this->connection->getHeaders($connectionData));
        return $result['choices'][0]['message']['content'];
    }

    protected function makeRequest(string $url, array $data, array $headers)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $output = curl_exec($ch);
        if ($output === false) {
            throw new BadRequest('Chatgpt curl error: ' . curl_error($ch));
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $output = json_decode($output, true);

        if ($httpCode == 200) {
            return $output;
        }

        // process error
        throw new BadRequest("Chatgpt error");
    }

    /**
     * @return EntityManager
     **/
    protected function getEntityManager()
    {
        return $this->container->get('entityManager');
    }
}

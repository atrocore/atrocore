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

namespace Atro\EntryPoints;

use Atro\ActionTypes\TypeInterface;
use Atro\Entities\Action;
use Espo\ORM\Entity;

class Webhook extends AbstractEntryPoint
{
    public static bool $authRequired = false;

    public function run()
    {
        $id = $_GET['id'] ?? null;
        if (empty($id)) {
            $this->show404();
        }

        /** @var Entity $webhook */
        $webhook = $this->getEntityManager()->getEntity('Webhook', $id);
        if (!$webhook) {
            $this->show404();
        }

        if (empty($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== $webhook->get('httpMethod')) {
            $this->show404();
        }

        /** @var Action $webhook */
        $action = $webhook->get('action');
        if (!empty($action) && !empty($handler = $this->getActionType($action->get('type')))) {
            $input = new \stdClass();
            $input->webhookRequest['headers'] = getallheaders();
            $input->webhookRequest['body'] = file_get_contents('php://input');
            $input->webhookRequest['queryParameters'] = [];
            foreach ($_GET as $key => $value) {
                if ($key !== 'atroq') {
                    $input->webhookRequest['queryParameters'][$key] = $value;
                }
            }

            $handler->executeNow($action, $input);
        }

        http_response_code(200);
        header('Content-Type: text/plain');
        echo 'OK';
        exit;
    }

    protected function getActionType(string $type): ?TypeInterface
    {
        $className = $this->getMetadata()->get(['action', 'types', $type]);
        if (empty($className)) {
            return null;
        }

        return $this->container->get($className);
    }

    protected function show404(): void
    {
        header("HTTP/1.0 404 Not Found");
        echo "<h1>404 Not Found</h1>";
        echo "The page that you have requested could not be found.";
        exit;
    }
}

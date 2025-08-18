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
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;
use Atro\Entities\Action;
use Espo\ORM\Entity;

class Webhook extends AbstractEntryPoint
{
    public static bool $authRequired = false;

    public function run()
    {
        $id = $_GET['id'] ?? null;
        if (empty($id)) {
            throw new NotFound();
        }

        /** @var Entity $webhook */
        $webhook = $this->getEntityManager()->getEntity('Webhook', $id);
        if (!$webhook) {
            throw new NotFound();
        }

        /** @var Action $webhook */
        $action = $webhook->get('action');
        if (!empty($action)) {
            $input = new \stdClass();
            $this->getActionType($action->get('type'))->executeNow($action, $input);
        }

        http_response_code(200);
        header('Content-Type: text/plain');
        echo 'OK';
        exit;
    }

    protected function getActionType(string $type): TypeInterface
    {
        $className = $this->getMetadata()->get(['action', 'types', $type]);
        if (empty($className)) {
            throw new Error("No such action type '$type'.");
        }

        return $this->container->get($className);
    }
}

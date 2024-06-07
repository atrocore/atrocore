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

namespace Atro\Services;

use Espo\Core\Utils\Json;
use Espo\ORM\Entity;

class QueueManagerUpsert extends QueueManagerBase
{
    public function run(array $data = []): bool
    {
        try {
            $result = $this->getContainer()->get('serviceFactory')->create('MassActions')->upsert((array)Json::decode(Json::encode($data)));
            $message = Json::encode($result);
            $result = true;
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $result = false;
        }

        $this->qmItem->set('message', $message);
        $this->getEntityManager()->saveEntity($this->qmItem, ['skipAll' => true]);

        return $result;
    }

    public function getNotificationMessage(Entity $queueItem): string
    {
        return '';
    }
}

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

namespace Atro\Console;

use Espo\ORM\EntityManager;

/**
 * Class QueueItem
 */
class QueueItem extends AbstractConsole
{
    /**
     * @inheritdoc
     */
    public static function getDescription(): string
    {
        return 'Run Queue Manager job item.';
    }

    /**
     * @inheritdoc
     */
    public function run(array $data): void
    {
        if (empty($this->getConfig()->get('isInstalled'))) {
            exit(1);
        }

        /** @var EntityManager $entityManager */
        $entityManager = $this->getContainer()->get('entityManager');

        // get item
        $item = $entityManager->getEntity('QueueItem', $data['id']);
        if (empty($item)) {
            self::show('No such queue item!', self::ERROR, true);
        }

        $user = $item->get('createdBy');

        // set user
        $this->getContainer()->setUser($user);
        $entityManager->setUser($user);

        // create service
        $service = $this->getContainer()->get('serviceFactory')->create($item->get('serviceName'));

        // get data
        $data = json_decode(json_encode($item->get('data')), true);

        if (method_exists($service, 'setQueueItem')) {
            $service->setQueueItem($item);
        }
        $service->run($data);

        self::show('Queue Manager item ran!', self::SUCCESS, true);
    }
}

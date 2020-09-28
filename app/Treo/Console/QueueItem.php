<?php

declare(strict_types=1);

namespace Treo\Console;

use Espo\ORM\EntityManager;

/**
 * Class QueueItem
 *
 * @author r.ratsun <r.ratsun@gmail.com>
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

        // set user
        $this->getContainer()->setUser($item->get('createdBy'));
        $entityManager->setUser($item->get('createdBy'));

        // create service
        $service = $this->getContainer()->get('serviceFactory')->create($item->get('serviceName'));

        // get data
        $data = json_decode(json_encode($item->get('data')), true);

        // run
        $service->run($data);

        self::show('Queue Manager item ran!', self::SUCCESS, true);
    }
}

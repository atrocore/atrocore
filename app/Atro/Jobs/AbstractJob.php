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

namespace Atro\Jobs;

use Atro\Core\Container;
use Atro\Core\EventManager\Manager;
use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Core\Twig\Twig;
use Atro\Core\Utils\Language;
use Espo\Core\ServiceFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

abstract class AbstractJob
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    protected function getContainer(): Container
    {
        return $this->container;
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }

    protected function getServiceFactory(): ServiceFactory
    {
        return $this->getContainer()->get('serviceFactory');
    }

    protected function getConfig(): Config
    {
        return $this->getContainer()->get('config');
    }

    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }

    protected function getUser(): User
    {
        return $this->getContainer()->get('user');
    }

    protected function getLanguage(): Language
    {
        return $this->getContainer()->get('language');
    }

    protected function translate(string $label, string $category = 'labels', string $scope = 'Global'): string
    {
        return $this->getLanguage()->translate($label, $category, $scope);
    }

    public function getMemoryStorage(): StorageInterface
    {
        return $this->getContainer()->get('memoryStorage');
    }

    protected function getEventManager(): Manager
    {
        return $this->getContainer()->get('eventManager');
    }

    protected function twig(): Twig
    {
        return $this->getContainer()->get('twig');
    }

    protected function createNotification(Entity $job, string $message): void
    {
        $notification = $this->getEntityManager()->getEntity('Notification');
        $notification->set('type', 'Message');
        $notification->set('relatedType', 'Job');
        $notification->set('relatedId', $job->get('id'));
        $notification->set('message', $message);
        $notification->set('userId', $job->get('createdById'));

        $this->getEntityManager()->saveEntity($notification);
    }
}
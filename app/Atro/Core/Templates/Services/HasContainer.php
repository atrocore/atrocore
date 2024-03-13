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

namespace Atro\Core\Templates\Services;

use Atro\Core\Container;
use Atro\Core\EventManager\Event;
use Espo\Core\Services\Base;
use Espo\Core\Utils\Config;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/**
 * Class HasContainer
 */
class HasContainer extends Base
{
    /**
     * @var string[]
     */
    protected $dependencies = ['container', 'memoryStorage'];

    protected function getContainer(): Container
    {
        return $this->getInjection('container');
    }

    protected function reloadDependency(string $name): void
    {
        $this->getContainer()->reload($name);
    }

    protected function rebuild(): void
    {
        $this->reloadDependency('entityManager');
        $this->getContainer()->get('dataManager')->rebuild();
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }

    protected function getConfig(): Config
    {
        return $this->getContainer()->get('config');
    }

    protected function getUser(): User
    {
        return $this->getContainer()->get('user');
    }

    protected function dispatch(string $target, string $action, Event $event): Event
    {
        return $this->getContainer()->get('eventManager')->dispatch($target, $action, $event);
    }

    protected function translate(string $label, string $category = 'labels', string $scope = 'Global'): string
    {
        return $this->getContainer()->get('language')->translate($label, $category, $scope);
    }
}

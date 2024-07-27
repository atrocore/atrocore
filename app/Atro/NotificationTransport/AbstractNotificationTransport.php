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
namespace Atro\NotificationTransport;

use Atro\Core\Container;
use Atro\Core\Twig\Twig;
use Atro\Entities\NotificationTemplate;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Entities\User;

abstract class AbstractNotificationTransport
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    abstract public function send(User $user, NotificationTemplate $template, array $params): void;

    protected function getTwig(): Twig
    {
        return $this->container->get('twig');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getUserLanguage(User $user): string
    {
        $preferences = $this->getEntityManager()->getEntity('Preferences', $user->get('id'));
        return Language::detectLanguage($this->getConfig(), $preferences);
    }
}
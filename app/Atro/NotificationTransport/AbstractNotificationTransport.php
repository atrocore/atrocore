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
use Espo\ORM\Entity;

abstract class AbstractNotificationTransport
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    abstract public function send(User $user, NotificationTemplate $template, array $params): void;

    protected function getUserLanguage(User $user): string
    {
        $preferences = $this->getEntityManager()->getEntity('Preferences', $user->get('id'));
        return Language::detectLanguage($this->getConfig(), $preferences);
    }

    protected function addEntitiesAdditionalData(array &$params, string $language): void
    {
        $languageManager = $this->getLanguage();
        $initialLanguage = $languageManager->getLanguage();
        $languageManager->setLanguage($language);
        foreach ($params as $key => $param) {
            if ($param instanceof Entity) {
                $params[$key . 'Type'] = $param->getEntityType();
                $params[$key . 'Name'] = $languageManager->translate($param->getEntityType(), $language, 'scopeNames');
                $params[$key . 'Url'] = $this->getConfig()->get('siteUrl') . '/#' . $param->getEntityType() . '/view/' . $param->get('id');
            }
        }
        $languageManager->setLanguage($initialLanguage);
    }


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

    protected function getLanguage(): Language
    {
        return $this->container->get('language');
    }
}
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
use Atro\Core\Utils\NotificationManager;
use Atro\Entities\NotificationTemplate;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\ORM\Entity;

abstract class AbstractNotificationTransport
{
    protected Container $container;

    protected ?array $regeneratedParams = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    abstract public function send(User $user, Entity $template, array $params): void;

    protected function getUserLanguage(User $user): string
    {
        $preferences = $this->getEntityManager()->getEntity('Preferences', $user->get('id'));
        return Language::detectLanguage($this->getConfig(), $preferences);
    }

    protected function addEntitiesAdditionalData(array &$params, string $language, bool $shouldCleanEntities = false): void
    {
        $languageManager = $this->getLanguage();
        $initialLanguage = $languageManager->getLanguage();
        $languageManager->setLanguage($language);
        $params['language'] = $language;
        foreach ($params as $key => $param) {
            if ($param instanceof Entity) {
                $params[$key . 'Id'] = $param->get('id');
                $params[$key . 'Type'] = $param->getEntityType();
                $params[$key . 'Name'] = $languageManager->translate($param->getEntityType(), $language, 'scopeNames');
                $params[$key . 'Url'] = $this->getConfig()->get('siteUrl') . '/#' . $param->getEntityType() . '/view/' . $param->get('id');
                if($shouldCleanEntities){
                    $params['entityKeys'][] = $key;
                    unset($params[$key]);
                }
            }
        }
        $languageManager->setLanguage($initialLanguage);
    }

    public function renderTemplate(string $content,array $params): string
    {
        return $this->getTwig()->renderTemplate($content, $this->getRegeneratedParams($params));
    }

    protected function getRegeneratedParams($params): array
    {
        if(!empty($this->regeneratedParams)) {
            return $this->regeneratedParams;
        }
        $notificationParams = $params;
        $entityKeys = !empty($notificationParams['entityKeys']) ? $notificationParams['entityKeys'] : [];
        foreach ($entityKeys as $key) {
            if(!empty($notificationParams[$key])) {
                continue;
            }
            if(!empty($notificationParams[$key . 'Type']) && !empty($notificationParams[$key . 'Id'])){
                $notificationParams[$key] = $this->getEntityManager()->getEntity($notificationParams[$key . 'Type'], $notificationParams[$key . 'Id']);
            }
        }
        unset($notificationParams['entityKeys']);
        if(!empty($notificationParams['entity']) && !empty($notificationParams['occurrence']) && $notificationParams['occurrence'] === NotificationOccurrence::UPDATE) {
            $updateData = $this->container->get(NotificationManager::class)->getUpdateData($notificationParams['entity']);
            if(!empty($updateData)){
                $notificationParams['updateData'] = $updateData;
            }
        }

        return $this->regeneratedParams = $notificationParams;
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
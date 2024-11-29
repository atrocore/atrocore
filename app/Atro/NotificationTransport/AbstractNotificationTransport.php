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
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\ORM\Entity;

abstract class AbstractNotificationTransport
{
    protected Container $container;

    protected array $regeneratedParams = [];

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
        $hash = md5(json_encode($params));
        if(!empty($this->regeneratedParams[$hash])) {
            return $this->regeneratedParams[$hash];
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

        if(!empty($notificationParams['entity'])
            && !empty($notificationParams['occurrence'])
            && $notificationParams['occurrence'] === NotificationOccurrence::UPDATE
            && !empty($notificationParams['changedFieldsData'])) {
            $updateData = $this->getUpdateData($notificationParams['entity'], $notificationParams['changedFieldsData']);
            if(!empty($updateData)){
                $notificationParams['updateData'] = $updateData;
            }
        }

        return $this->regeneratedParams[$hash] = $notificationParams;
    }

    protected function getUpdateData(Entity $entity, array $data): ?array
    {

        if (empty($data['fields']) || empty($data['attributes']['was']) || empty($data['attributes']['became'])) {
            return null;
        }

        if(count($data['fields']) === 1 && in_array('modifiedBy',$data['fields'])){
            return null;
        }

        $data = json_decode(json_encode($data));

        $tmpEntity = $this->getEntityManager()->getEntity('Note');

        $this->container->get('serviceFactory')->create('Stream')->handleChangedData($data, $tmpEntity, $entity->getEntityType());

        $data = json_decode(json_encode($data), true);

        foreach ($tmpEntity->get('fieldDefs') as $key => $fieldDefs) {
            if (!empty($fieldDefs['type'])) {
                $data['fieldTypes'][$key] = $fieldDefs['type'];
            }
            if ($fieldDefs['type'] == 'link') {
                $data['linkDefs'][$key] = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', $key]);
            }
        }

        $data['diff'] = $tmpEntity->get('diff');
        $data['fieldDefs'] = $tmpEntity->get('fieldDefs');
        sort($data['fields']);

        return $data;
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
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
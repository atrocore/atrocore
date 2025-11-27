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

use Atro\Core\Application;
use Atro\Core\AttributeFieldConverter;
use Atro\Core\DataManager;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Mail\Sender;
use Atro\Core\Utils\Metadata;
use Espo\Core\Acl;
use Espo\ORM\Entity;

class App extends AbstractService
{
    public function prepareScriptFields(string $entityName, array $fields): array
    {
        $entity = $this->getEntityManager()->getRepository($entityName)->get();

        $res = [];

        foreach ($fields as $item) {
            foreach ($entity->fields ?? [] as $field => $defs) {
                if ($field === $item && empty($defs['notStorable'])) {
                    $res[$field] = null;
                }

                if (!empty($defs['originalName']) && $defs['originalName'] === $item) {
                    if ((!empty($defs['isLinkMultipleIdList']) || empty($defs['notStorable'])) && empty($defs['isLinkEntityName'])) {
                        $res[$field] = null;
                    }
                }
            }
        }

        return [
            'text' => !empty($res) ? json_encode($res) : ''
        ];
    }

    public function prepareScriptAttributes(string $entityName, array $attributesIds): array
    {
        $entity = $this->getEntityManager()->getRepository($entityName)->get();

        $attributes = $this->getEntityManager()->getRepository('Attribute')->getAttributesByIds($attributesIds);

        $attributesDefs = [];
        foreach ($attributes as $attribute) {
            $this->getAttributeFieldConverter()->convert($entity, $attribute, $attributesDefs);
        }

        $res = [];
        foreach ($entity->fields ?? [] as $field => $defs) {
            if (!empty($defs['attributeId'])) {
                $res[$field] = null;
            }
        }

        if (!empty($res)) {
            $res['__attributes'] = $attributesIds;
            return [
                'text' => json_encode($res)
            ];
        }

        return [
            'text' => ''
        ];
    }

    public static function createRebuildNotification(): void
    {
        DataManager::pushPublicData('isNeedToRebuildDatabase', true);
    }

    public function rebuild(): void
    {
        if (Application::isSystemUpdating()) {
            self::createRebuildNotification();
        } else {
            $this->getDataManager()->rebuild();
        }
    }

    public function getUserData(): array
    {
        $user = $this->getUser();
        if (!$user->has('teamsIds')) {
            $user->loadLinkMultipleField('teams');
        }

        $ids = [];
        $names = new \stdClass();
        foreach ($user->get('roles') ?? [] as $role) {
            $ids[] = $role->get('id');
            $names->{$role->get('id')} = $role->get('name');
        }
        $user->set('rolesIds', $ids);
        $user->set('rolesNames', $names);

        $userData = $user->getValueMap();

        $settings = (object)[];
        foreach ($this->getConfig()->get('userItems') as $item) {
            $settings->$item = $this->getConfig()->get($item);
        }

        if ($this->getUser()->isAdmin()) {
            foreach ($this->getConfig()->get('adminItems') as $item) {
                if ($this->getConfig()->has($item)) {
                    $settings->$item = $this->getConfig()->get($item);
                }
            }
        }

        $settingsFieldDefs = $this->getMetadata()->get('entityDefs.Settings.fields', []);
        foreach ($settingsFieldDefs as $field => $d) {
            if ($d['type'] === 'password') {
                unset($settings->$field);
            }
        }

        unset($userData->authTokenId);
        unset($userData->password);

        return [
            'user'        => $userData,
            'acl'         => $this->getAcl()->getMap(),
            'preferences' => $this->getPreferencesData(),
            'token'       => $user->get('token'),
            'settings'    => $settings,
            'appParams'   => [
                'maxUploadSize' => $this->getMaxUploadSize() / 1024.0 / 1024.0,
            ],
        ];
    }

    public function sendTestEmail(array $data): bool
    {
        $this
            ->getMailSender()
            ->send(
                [
                    'subject' => 'Test Email',
                    'body'    => 'Test Email',
                    'isHtml'  => false,
                    'to'      => $data['emailAddress'],
                ]
            );

        return true;
    }

    public function getPreferencesData(): \stdClass
    {
        $user = $this->getUser();

        $this->getUserProfileService()->prepareLayoutProfileData($user);

        $preferencesData = new \stdClass();
        $preferencesData->id = $user->id;
        $preferencesData->language = $user->getLanguage();
        $preferencesData->locale = $user->get('localeId');
        $preferencesData->dashboardLayout = $user->get('dashboardLayout');
        $preferencesData->dashletsOptions = $user->get('dashletsOptions');
        $preferencesData->favoritesList = $user->get('favoritesList');
        $preferencesData->followCreatedEntities = $user->get('followCreatedEntities');
        $preferencesData->followEntityOnStreamPost = $user->get('followEntityOnStreamPost');
        $preferencesData->useCustomTabList = $user->get('useCustomTabList');
        $preferencesData->lpNavigation = $user->get('lpNavigation');
        $preferencesData->hideShowFullList = $user->get('hideShowFullList');
        $preferencesData->layoutProfileId = $user->get('layoutProfileId');
        $preferencesData->dashboardLayout = $user->get('dashboardLayout');
        $preferencesData->dashletsOptions = $user->get('dashletsOptions');
        $preferencesData->favoritesList = $user->get('favoritesList');
        $preferencesData->closedPanelOptions = $user->get('closedPanelOptions');

        if (!empty($locale = $user->getLocale())) {
            $preferencesData->decimalMark = $locale->get('decimalMark');
            $preferencesData->timeFormat = $locale->get('timeFormat');
            $preferencesData->thousandSeparator = $locale->get('thousandSeparator');
            $preferencesData->weekStart = $locale->get('weekStart') === 'monday' ? 1 : 0;
            $preferencesData->dateFormat = $locale->get('dateFormat');
            $preferencesData->timeZone = $locale->get('timeZone');
            $preferencesData->fallbackLanguage = $locale->get('fallbackLanguageCode');
        } else {
            if (!empty($this->getConfig()->get('locale'))) {
                $locale = $this->getEntityManager()->getRepository('Locale')->get($this->getConfig()->get('locale'));
                if (!empty($locale)) {
                    $preferencesData->language = $locale->get('language');
                    $preferencesData->fallbackLanguage = $locale->get('fallbackLanguageCode');
                }
            }
        }

        if (!empty($style = $user->getStyle())) {
            $preferencesData->styleId = $style->get('id');
            $preferencesData->styleName = $style->get('name');
        }

        return $preferencesData;
    }

    public function recalculateScriptField(\stdClass $data): Entity
    {
        if (!property_exists($data, 'field') || !property_exists($data, 'id') || !property_exists($data, 'scope')) {
            throw new BadRequest();
        }

        $id = $data->id;
        $field = $data->field;
        $scope = $data->scope;

        if (in_array($field, $this->getAcl()->getScopeForbiddenAttributeList($scope, 'edit'))) {
            throw new Forbidden();
        }

        $repository = $this->getEntityManager()->getRepository($scope);
        $entity = $repository->get($id);

        if (empty($entity)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $this->getAttributeFieldConverter()->putAttributesToEntity($entity);

        $fieldDefs = $entity->entityDefs['fields'][$field];

        if (empty($fieldDefs)) {
            throw new BadRequest('No such Field');
        }

        if (!empty($fieldDefs['type']) && $fieldDefs['type'] === 'script' && !empty($fieldDefs['script'])) {
            $repository->calculateScriptFields($entity, $field);
        }

        return $entity;
    }

    protected function getMaxUploadSize(): int
    {
        $maxSize = 0;

        $postMaxSize = $this->convertPHPSizeToBytes(ini_get('post_max_size'));
        if ($postMaxSize > 0) {
            $maxSize = $postMaxSize;
        }
        $attachmentUploadMaxSize = $this->getConfig()->get('attachmentUploadMaxSize');
        if ($attachmentUploadMaxSize && (!$maxSize || $attachmentUploadMaxSize < $maxSize)) {
            $maxSize = $attachmentUploadMaxSize;
        }

        return $maxSize;
    }

    /**
     * @param mixed $size
     *
     * @return int
     */
    protected function convertPHPSizeToBytes($size): int
    {
        if (is_numeric($size)) {
            return $size;
        }

        $suffix = substr($size, -1);
        $value = substr($size, 0, -1);
        switch (strtoupper($suffix)) {
            case 'P':
                $value *= 1024;
            case 'T':
                $value *= 1024;
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
                break;
        }

        return $value;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function getUserProfileService(): UserProfile
    {
        return $this->getService('UserProfile');
    }

    protected function getService(string $name)
    {
        return $this->getInjection('container')->get('serviceFactory')->create($name);
    }

    protected function getAcl(): Acl
    {
        return $this->getInjection('container')->get('acl');
    }

    protected function getMetadata(): Metadata
    {
        return $this->getInjection('container')->get('metadata');
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('container')->get('dataManager');
    }

    protected function getMailSender(): Sender
    {
        return $this->getInjection('container')->get('mailSender');
    }

    protected function getAttributeFieldConverter(): AttributeFieldConverter
    {
        return $this->getInjection('container')->get(AttributeFieldConverter::class);
    }
}

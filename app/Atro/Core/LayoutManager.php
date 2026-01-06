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

namespace Atro\Core;

use Atro\Core\EventManager\Manager;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\ORM\Repositories\RDB;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\FileManager;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Util;
use Atro\Core\EventManager\Event;
use Atro\Entities\User;
use Atro\Services\AbstractService;
use Doctrine\DBAL\ParameterType;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Json;
use Espo\ORM\IEntity;

/**
 * Class LayoutManager
 */
class LayoutManager
{
    protected Container $container;
    protected ?IEntity $defaultProfile;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Get a full path of the file
     *
     * @param string | array $folderPath - Folder path, Ex. myfolder
     * @param string         $filePath - File path, Ex. file.json
     *
     * @return string
     */
    public function concatPath($folderPath, $filePath = null)
    {
        return Util::concatPath($folderPath, $filePath);
    }

    /**
     * Get Layout context
     *
     * @param string $scope
     * @param string $viewType
     *
     * @return json|string
     */
    public function get(string $scope, string $viewType, ?string $relatedEntity = null, ?string $relatedLink = null, ?string $layoutProfileId = null, bool $isAdminPage = false): array
    {
        // prepare scope
        $scope = $this->sanitizeInput($scope);

        // prepare name
        $viewType = $this->sanitizeInput($viewType);

        if ($this->getMetadata()->get("scopes.$scope.primaryEntityId")) {
            $derivativeScope = $scope;
            $scope = $this->getMetadata()->get("scopes.$scope.primaryEntityId");
        }

        if (empty($relatedEntity)) {
            $relatedEntity = null;
        }

        if (empty($relatedLink)) {
            $relatedLink = null;
        }

        $selectedProfileId = $this->getEntityManager()->getConnection()
            ->createQueryBuilder()
            ->select('layout_profile_id')
            ->from('user_entity_layout', 'uel')
            ->where('uel.user_id=:userId and uel.entity=:entity and uel.view_type=:viewType and '
                . (empty($relatedEntity) ? "uel.related_entity is null" : "uel.related_entity=:relatedEntity")
                . ' and '
                . (empty($relatedLink) ? "uel.related_link is null" : "uel.related_link=:relatedLink")
                . ' and deleted=:false')
            ->setParameters([
                'entity'        => $scope,
                'viewType'      => $viewType,
                'relatedEntity' => $relatedEntity,
                'relatedLink'   => $relatedLink,
                'userId'        => $this->getUser()->id,
            ])
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchOne();

        $storedProfiles = $this->getEntityManager()->getConnection()
            ->createQueryBuilder()
            ->select('lp.id', 'lp.name')
            ->from('layout', 'l')
            ->innerJoin('l', 'layout_profile', 'lp', 'l.layout_profile_id=lp.id')
            ->where("l.entity=:entity and l.view_type=:viewType and "
                . (empty($relatedEntity) ? "l.related_entity is null" : "l.related_entity=:relatedEntity")
                . ' and '
                . (empty($relatedLink) ? "l.related_link is null" : "l.related_link=:relatedLink")
                . " and l.deleted=:false and lp.deleted=:false")
            ->setParameters([
                'entity'        => $scope,
                'viewType'      => $viewType,
                'relatedEntity' => $relatedEntity,
                'relatedLink'   => $relatedLink,
            ])->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        if (!empty($selectedProfileId) && !in_array($selectedProfileId, array_column($storedProfiles, 'id'))) {
            $selectedProfileId = null;
        }

        if (!empty($selectedProfileId) && empty($layoutProfileId)) {
            $layoutProfileId = $selectedProfileId;
        }

        $layoutProfile = null;
        if (!empty($layoutProfileId)) {
            $layoutProfile = $this->getEntityManager()->getEntity('LayoutProfile', $layoutProfileId);
            if (empty($layoutProfile)) {
                $layoutProfileId = null;
            }
        }

        // compose
        list($layout, $storedProfile) = $this->compose($scope, $viewType, $relatedEntity, $relatedLink, $layoutProfileId);

        // remove fields from layout if this fields not exist in metadata
        $layout = $this->disableNotExistingFields($scope, $relatedEntity, $relatedLink, $viewType, $layout);

        if (empty($isAdminPage) && in_array($viewType, ['list', 'detail'])) {
            $layout = $this->injectMultiLanguageFields($layout, $viewType, $scope, $relatedEntity, $relatedLink);
        }

        if ($viewType === 'list') {
            foreach ($layout as $k => $row) {
                if (!empty($row['name']) && empty($row['notSortable']) && !empty($this->getMetadata()->get(['entityDefs', $scope, 'fields', $row['name'], 'notStorable']))) {
                    $layout[$k]['notSortable'] = true;
                }
            }
        }

        if (!empty($derivativeScope)) {
            if ($viewType === 'detail') {
                array_unshift($layout[0]['rows'], [['name' => 'derivativeStatus'], ['name' => 'primaryRecord']]);
            } elseif ($viewType === 'list') {
                $layout[] = ['name' => 'derivativeStatus'];
                $layout[] = ['name' => 'primaryRecord'];
            }
        }

        $event = new Event([
            'target' => $scope . 'Layout',
            'params' => [
                'scope'           => $scope,
                'derivativeScope' => $derivativeScope ?? null,
                'viewType'        => $viewType,
                'relatedEntity'   => $relatedEntity,
                'relatedLink'     => $relatedLink,
                'layoutProfileId' => $layoutProfileId,
                'isAdminPage'     => $isAdminPage,
                'isCustom'        => !empty($storedProfile),
            ],
            'result' => $layout]);

        $layout = $this->getEventManager()->dispatch('Layout', 'afterGetLayoutContent', $event)
            ->getArgument('result');

        return [
            'layout'            => $layout,
            'storedProfile'     => empty($storedProfile) ? [] : ['id' => $storedProfile->get('id'), 'name' => $storedProfile->get('name')],
            'storedProfiles'    => $storedProfiles,
            'selectedProfileId' => empty($selectedProfileId) ? null : $selectedProfileId,
            'canEdit'           => empty($layoutProfile) ? false : $this->getAcl()->check($layoutProfile, 'edit')
        ];
    }

    public function getRelationScope(string $scope, ?string $relatedScope, ?string $relatedLink): ?string
    {
        $relationScope = null;
        if (!empty($relatedLink)) {
            $linkData = $this->getMetadata()->get(['entityDefs', $relatedScope, 'links', $relatedLink]) ?? [];
            if (!empty($linkData['entity']) && $linkData['entity'] === $scope && !empty($linkData['relationName'])) {
                $relationScope = ucfirst($linkData['relationName']);
            }
        }

        return $relationScope;
    }


    public function saveUserPreference(string $scope, string $viewType, ?string $relatedEntity = null, ?string $relatedLink = null, ?string $layoutProfileId = null): bool
    {
        /* @var $repository RDB */
        $repository = $this->getEntityManager()->getRepository('UserEntityLayout');
        $record = $repository
            ->where([
                'userId'        => $this->getUser()->id,
                'entity'        => $scope,
                'viewType'      => $viewType,
                'relatedEntity' => empty($relatedEntity) ? null : $relatedEntity,
                'relatedLink'   => empty($relatedLink) ? null : $relatedLink
            ])
            ->findOne();

        if (empty($layoutProfileId)) {
            if (!empty($record)) {
                $repository->remove($record);
            }
        } else {
            if (empty($record)) {
                $record = $repository->get();
                $record->set('userId', $this->getUser()->id);
                $record->set('entity', $scope);
                $record->set('viewType', $viewType);
                if (!empty($relatedEntity)) {
                    $record->set('relatedEntity', $relatedEntity);
                }
                if (!empty($relatedLink)) {
                    $record->set('relatedLink', $relatedLink);
                }
            }
            $record->set('layoutProfileId', $layoutProfileId);
            $repository->save($record);
        }

        return true;
    }

    /**
     * @param string $scope
     * @param string $name
     *
     * @return json|string
     */
    public function resetToDefault(string $scope, string $name, string $relatedScope, string $relatedLink, string $layoutProfileId)
    {
        $scope = $this->sanitizeInput($scope);
        $name = $this->sanitizeInput($name);

        $layoutRepo = $this->getEntityManager()->getRepository('Layout');
        $layoutRepo->where(['entity'          => $scope,
                            'viewType'        => $name,
                            'relatedEntity'   => empty($relatedScope) ? null : $relatedScope,
                            'relatedLink'     => empty($relatedLink) ? null : $relatedLink,
                            'layoutProfileId' => $layoutProfileId])
            ->removeCollection();

        $this->getDataManager()->clearCache(true);

        return $this->get($scope, $name, $relatedScope, $layoutProfileId);
    }

    public function resetAllToDefault(string $layoutProfileId): bool
    {
        $layoutRepo = $this->getEntityManager()->getRepository('Layout');
        $layoutRepo->where(['layoutProfileId' => $layoutProfileId])->removeCollection();

        $this->getDataManager()->clearCache(true);

        return true;
    }

    public function checkLayoutProfile(string $layoutProfileId): void
    {
        $profile = $this->getEntityManager()->getEntity('LayoutProfile', $layoutProfileId);
        if (empty($profile)) {
            throw new NotFound();
        }
        if (!$this->getAcl()->checkEntity($profile, 'edit')) {
            throw new Forbidden();
        }
    }

    /**
     * Save changes
     *
     * @return bool
     */
    public function save(string $scope, string $layoutName, ?string $relatedEntity, ?string $relatedLink, string $layoutProfileId, array $layoutData): bool
    {
        $layoutRepo = $this->getEntityManager()->getRepository('Layout');
        $where = ['entity' => $scope, 'viewType' => $layoutName, 'layoutProfileId' => $layoutProfileId];
        if (in_array($layoutName, ['list', 'detail'])) {
            if (!empty($relatedEntity)) {
                // validate related entity
                if ($this->getMetadata()->get(['entityDefs', $relatedEntity, 'links', $relatedLink, 'entity']) !== $scope) {
                    throw new BadRequest("The relation  $relatedEntity($relatedLink) on $scope dont exist");
                }
            }

            $where['relatedEntity'] = empty($relatedEntity) ? null : $relatedEntity;
            $where['relatedLink'] = empty($relatedLink) ? null : $relatedLink;
        }

        $selectedFields = [];
        if ($layoutName === 'detail') {
            foreach ($layoutData as $data) {
                if (!empty($data['rows'])) {
                    foreach ($data['rows'] as $row) {
                        if (!empty($row)) {
                            foreach ($row as $cell) {
                                if (empty($cell['name'])) {
                                    continue;
                                }
                                $selectedFields[] = $cell['name'];
                            }
                        }
                    }
                }
            }

            foreach ($this->getMetadata()->get(['entityDefs', $scope, 'fields'], []) as $field => $fieldDef) {
                if (!empty($fieldDef['multilangField'])) {
                    continue;
                }
                if (!empty($fieldDef['layoutRemoveDisabled'])) {
                    if (!in_array($field, $selectedFields)) {
                        throw  new BadRequest(sprintf($this->getLanguage()->translate("cannotRemoveFieldFromLayout", 'messages', 'Layout'), $field));
                    }
                }
            }
        }

        $layoutEntity = $layoutRepo->where($where)->findOne();
        if (empty($layoutEntity)) {
            $layoutEntity = $layoutRepo->get();
            $layoutEntity->set($where);
            $layoutRepo->save($layoutEntity);
        }
        return $layoutRepo->saveContent($layoutEntity, $layoutData);
    }

    protected function getDefaultLayoutProfileId(): ?string
    {
        if (!isset($this->defaultProfile)) {
            $this->defaultProfile = $this->getEntityManager()->getRepository('LayoutProfile')->where(['isDefault' => true])->findOne();
        }

        return empty($this->defaultProfile) ? null : $this->defaultProfile->get('id');
    }


    protected function getCustomLayout(string $scope, string $name, ?string $relatedEntity, ?string $relatedLink, ?string $layoutProfileId, bool $keepIds = true): array
    {
        $layoutRepo = $this->getEntityManager()->getRepository('Layout');
        $defaultLayoutProfileId = $this->getDefaultLayoutProfileId();
        $isOriginal = false;

        $where = [
            'entity'        => $scope,
            'viewType'      => $name,
            'relatedEntity' => empty($relatedEntity) ? null : $relatedEntity,
            'relatedLink'   => empty($relatedLink) ? null : $relatedLink,
        ];

        $profileIds = [];
        if (!empty($defaultLayoutProfileId)) {
            $profileIds[] = $defaultLayoutProfileId;
        }

        if (empty($layoutProfileId)) {
            $layoutProfile = $this->getUser()->get('layoutProfile');
        } else {
            $layoutProfile = $this->getEntityManager()->getEntity('LayoutProfile', $layoutProfileId);
        }

        if (!empty($layoutProfile)) {
            $parent = $layoutProfile->get('parent');
            if (!empty($parent)) {
                $profileIds[] = $parent->get('id');
            }
            $profileIds[] = $layoutProfile->get('id');
        }

        $profileIds = array_reverse(array_unique($profileIds));

        foreach ($profileIds as $profileId) {
            if (empty($profileId)) {
                continue;
            }
            $layout = $layoutRepo->where(array_merge($where, ['layoutProfileId' => $profileId]))->findOne();
            if (!empty($layout)) {
                if ($keepIds) {
                    $isOriginal = $profileId === $layoutProfileId;
                }
                break;
            }
        }

        if (empty($layout) && !empty($relatedEntity)) {
            list($layout) = $this->getCustomLayout($scope, $name, null, null, $layoutProfileId, false);
            return [$layout, null];
        }

        if (!empty($layout)) {
            return [$layout->getData($isOriginal), $layout->get('layoutProfile')];
        }
        return [[], null];
    }

    protected function compose(string $scope, string $name, ?string $relatedEntity, ?string $relatedLink, ?string $layoutProfileId): array
    {
        // from custom layout
        list ($customLayout, $storedProfile) = $this->getCustomLayout($scope, $name, $relatedEntity, $relatedLink, $layoutProfileId);
        if (!empty($customLayout)) {
            return [$customLayout, $storedProfile];
        }

        // prepare data
        $data = [];

        if (!empty($relatedEntity)) {
            if ($name == 'list') {
                $data = $this->getLayoutFromFiles($scope, "listIn{$relatedEntity}For" . ucfirst($relatedLink));
                if (empty($data)) {
                    $data = $this->getLayoutFromFiles($scope, "listIn$relatedEntity");
                }
            } elseif ($name == 'detail') {
                $data = $this->getLayoutFromFiles($scope, "detailIn{$relatedEntity}For" . ucfirst($relatedLink));
            }
        }

        if (empty($data)) {
            $data = $this->getLayoutFromFiles($scope, $name);
        }


        // default by method
        if (empty($data)) {
            $type = $this->getMetadata()->get(['scopes', $scope, 'type']);
            $method = "getDefaultFor{$type}EntityType";
            if (!empty($this->getMetadata()->get(['scopes', $scope, 'associatesForEntity']))) {
                $method = "getDefaultForAssociates";
            }
            if (method_exists($this, $method)) {
                $data = $this->$method($scope, $name);
            } else {
                $fileFullPath = $this->concatPath(CORE_PATH . '/Atro/Core/Templates/Layouts/' . $type, $name . '.json');

                if (file_exists($fileFullPath)) {
                    // get file data
                    $fileData = $this->getFileManager()->getContents($fileFullPath);

                    // prepare data
                    $data = Json::decode($fileData, true);
                }
            }
        }

        if (empty($data) && $name === 'summary') {
            $data = [
                [
                    'rows' => []
                ]
            ];

            if (!empty($this->getMetadata()->get(['entityDefs', $scope, 'fields', 'created', 'type']))) {
                $data[0]['rows'][] = [['name' => 'created', 'fullWidth' => true]];
            }
            if (!empty($this->getMetadata()->get(['entityDefs', $scope, 'fields', 'modified', 'type']))) {
                $data[0]['rows'][] = [['name' => 'modified', 'fullWidth' => true]];
            }
        }

        if ($name === 'relationships' && !empty($this->getMetadata()->get(['scopes', $scope, 'hasAssociate']))) {
            $data = $data ?? [];

            $strData = json_encode($data);
            if (!str_contains($strData, "associatedItems") && !str_contains($strData, "associatingItems")) {
                $data[] = ['name' => "associatedItems"];
                $data[] = ['name' => "associatingItems"];
            }
        }

        return [$data, null];
    }

    public function getLayoutFromFiles(string $scope, string $name): array
    {
        $data = [];
        $filePath = $this->concatPath(CORE_PATH . '/Atro/Resources/layouts', $scope);
        $fileFullPath = $this->concatPath($filePath, $name . '.json');
        if (file_exists($fileFullPath)) {
            // get file data
            $fileData = $this->getFileManager()->getContents($fileFullPath);

            // prepare data
            $data = Json::decode($fileData, true);
        }

        // from modules data
        foreach ($this->getMetadata()->getModules() as $module) {
            $module->loadLayouts($scope, $name, $data);
        }

        return $data;
    }


    protected function getDefaultForAssociates(string $scope, string $name): array
    {
        $mainField = "associatingItem";
        $relatedField = "associatedItem";

        $data = [];

        switch ($name) {
            case 'list':
                $data = [
                    [
                        'name' => 'association'
                    ],
                    [
                        'name' => $mainField
                    ],
                    [
                        'name' => $relatedField
                    ]
                ];
                break;
            case 'detail':
                $data = [
                    [
                        "label" => "Associate",
                        "rows"  => [
                            [
                                [
                                    "name" => "association"
                                ],
                                [
                                    "name" => "reverseAssociation"
                                ]
                            ],
                            [
                                [
                                    "name" => $mainField
                                ],
                                [
                                    "name" => $relatedField
                                ]
                            ],
                            [
                                [
                                    "name" => "sorting"
                                ],
                                [
                                    "name" => "associateEverything"
                                ]
                            ]
                        ]
                    ]
                ];
                break;
        }

        return $data;
    }

    protected function getDefaultForRelationEntityType(string $scope, string $name): array
    {
        $relationFields = [];
        foreach ($this->getMetadata()->get(['entityDefs', $scope, 'fields']) as $field => $fieldDefs) {
            if (!empty($fieldDefs['relationField'])) {
                $relationFields[] = $field;
            }
        }

        $data = [];

        switch ($name) {
            case 'list':
                $data = [
                    [
                        'name' => $relationFields[0]
                    ],
                    [
                        'name' => $relationFields[1]
                    ],
                ];
                break;
            case 'detail':
                $data = [
                    [
                        "label" => "Relationship",
                        "rows"  => [
                            [
                                [
                                    "name" => $relationFields[0]
                                ],
                                [
                                    "name" => $relationFields[1]
                                ]
                            ]
                        ]
                    ]
                ];
                break;
        }

        return $data;
    }

    protected function injectMultiLanguageFields(array $data, string $viewType, string $scope, ?string $relatedEntity, ?string $relatedLink): array
    {
        $multiLangFields = $this->getMultiLangFields($scope);
        $relationScope = $this->getRelationScope($scope, $relatedEntity, $relatedLink);
        if (!empty($relationScope)) {
            $multiLangFields = array_merge($multiLangFields, $this->getMultiLangFields($relationScope));
        }

        if (empty($multiLangFields)) {
            return $data;
        }

        $result = [];

        if ($viewType === 'detail') {
            foreach ($data as $k => $panel) {
                $result[$k] = $panel;

                if (isset($panel['rows']) || !empty($panel['rows'])) {
                    $rows = [];
                    $skip = false;

                    foreach ($panel['rows'] as $key => $row) {
                        if ($skip) {
                            $skip = false;
                            continue;
                        }

                        $newRow = [];
                        $fullWidthRow = count($row) == 1;

                        foreach ($row as $field) {
                            $newRow[] = $field;

                            if (is_array($field) && in_array($field['name'], $multiLangFields)) {
                                array_pop($newRow);
                                $localeFields = $this->getMultiLangLocalesFields($field['name']);

                                if (!empty($needToAdd = $localeFields)) {
                                    $nextRow = $key != count($panel['rows']) - 1 ? $panel['rows'][$key + 1] : null;

                                    if (!$fullWidthRow && !empty($nextRow)) {
                                        if (in_array(false, $nextRow)) {
                                            $item = null;
                                            foreach ($nextRow as $f) {
                                                if (is_array($f)) {
                                                    $item = $f;
                                                }
                                            }
                                        }
                                    }

                                    foreach ($needToAdd as $item) {
                                        $newField = $field;
                                        $newField['name'] = $item;
                                        $newRow[] = $newField;
                                    }
                                }
                            }
                        }

                        if (!$fullWidthRow && count($newRow) % 2 != 0) {
                            if ($newRow[count($newRow) - 1] === false) {
                                array_pop($newRow);
                            } else {
                                $newRow[] = false;
                            }
                        }

                        $rows = array_merge($rows, array_chunk($newRow, $fullWidthRow ? 1 : 2));
                    }

                    $result[$k]['rows'] = $rows;
                }
            }
        }

        if ($viewType === 'list') {
            foreach ($data as $field) {
                if (is_array($field) && in_array($field['name'], $multiLangFields)) {
                    foreach ($this->getMultiLangLocalesFields($field['name']) as $localeField) {
                        $result[] = array_merge($field, ['name' => $localeField]);
                    }
                } else {
                    $result[] = $field;
                }
            }
        }


        return $result;
    }

    protected function getMultiLangFields(string $scope): array
    {
        $result = [];

        foreach ($this->getMetadata()->get(['entityDefs', $scope, 'fields'], []) as $field => $data) {
            if (!empty($data['isMultilang'])) {
                $result[] = $field;
            }
        }

        return $result;
    }


    public static function getSystemMainLocaleCode(Config $config)
    {
        return $config->get('mainLanguage') ?? 'en_US';
    }

    public static function getUserLanguages(User $user, EntityManager $entityManager, Config $config): array
    {
        $disabledLanguages = $user->get('disabledLanguages');
        if (!is_array($disabledLanguages)) {
            $disabledLanguages = [];
        }

        $localeId = AbstractService::getHeader('Locale-Id');
        if (!empty($localeId)) {
            $userLocale = $entityManager->getEntity('Locale', $localeId);
        }
        if (empty($userLocale) && !empty($user->get('localeId'))) {
            $userLocale = $entityManager->getEntity('Locale', $user->get('localeId'));
        }
        if (empty($userLocale) && !empty($config->get('locale'))) {
            $userLocale = $entityManager->getEntity('Locale', $config->get('locale'));
        }


        $systemLocales = $config->get('inputLanguageList', []);
        $mainLocaleCode = self::getSystemMainLocaleCode($config);
        $systemLocales[] = $mainLocaleCode;

        $locales = array_diff($systemLocales, $disabledLanguages);

        if (!empty($userLocale) && in_array($userLocale->get('languageCode'), $systemLocales)) {
            array_unshift($locales, $userLocale->get('languageCode'));
        } else {
            array_unshift($locales, $mainLocaleCode);
        }

        return array_unique($locales);
    }

    protected function getPreparedLocalesCodes(): array
    {
        $result = [];

        $mainLocaleCode = self::getSystemMainLocaleCode($this->getConfig());

        foreach (self::getUserLanguages($this->getUser(), $this->getEntityManager(), $this->getConfig()) as $locale) {
            if ($locale === $mainLocaleCode) {
                $result[] = '';
            } else {
                $result[] = ucfirst(Util::toCamelCase(strtolower($locale)));
            }
        }

        return $result;
    }

    protected function getMultiLangLocalesFields(string $fieldName): array
    {
        $result = [];

        foreach ($this->getPreparedLocalesCodes() as $locale) {
            $result[] = $fieldName . $locale;
        }

        return $result;
    }

    /**
     * Disable fields from layout if this fields are multilang or not exist in metadata
     *
     * @param string $scope
     * @param string $name
     * @param array  $data
     *
     * @return array
     */
    protected function disableNotExistingFields($scope, $relatedScope, $relatedLink, $name, $data): array
    {
        // get entityDefs
        $entityDefs = $this->getMetadata()->get('entityDefs')[$scope] ?? [];

        // check if entityDefs exists
        if (!empty($entityDefs)) {
            // get fields for entity
            $fields = array_keys(array_filter($entityDefs['fields'], fn($defs) => empty($defs['multilangField'])));
            if (!empty($relatedScope) && in_array($name, ['list', 'detail'])) {
                $linkData = $this->getMetadata()->get(['entityDefs', $relatedScope, 'links', $relatedLink]) ?? [];
                if (!empty($linkData['entity']) && $linkData['entity'] === $scope && !empty($linkData['relationName'])) {
                    $relationScope = ucfirst($linkData['relationName']);
                    $relationFields = $this->getMetadata()->get(['entityDefs', $relationScope, 'fields']) ?? [];
                    $relationFields = array_keys(array_filter($relationFields, fn($defs) => empty($defs['multilangField'])));
                    $relationFields[] = 'id';
                    $relationFields = array_map(fn($f) => "{$relationScope}__{$f}", $relationFields);
                    $fields = array_merge($fields, $relationFields);
                }
            }

            $fields[] = 'id';

            // remove fields from layout if this fields not exist in metadata
            switch ($name) {
                case 'filters':
                case 'massUpdate':
                    $data = array_values(array_intersect($data, $fields));
                    break;
                case 'detail':
                    for ($key = 0; $key < count($data[0]['rows']); $key++) {
                        foreach ($data[0]['rows'][$key] as $fieldKey => $fieldData) {
                            if (isset($fieldData['name']) && !in_array($fieldData['name'], $fields)) {
                                $data[0]['rows'][$key][$fieldKey] = false;
                            }
                        }
                    }
                    break;
                case 'list':
                case 'selection':
                    $attributesIds = array_column($data, 'attributeId');
                    if (!empty($attributesIds)) {
                        $attributesDefs = $this
                            ->container
                            ->get('serviceFactory')
                            ->create('Attribute')
                            ->getAttributesDefs($scope, $attributesIds);

                        foreach ($attributesDefs as $attrField => $attributeDefs) {
                            $fields[] = $attrField;
                            foreach ($data as $key => $row) {
                                if ($row['name'] === $attrField) {
                                    $data[$key]['label'] = $attributeDefs['detailViewLabel'] ?? $attributeDefs['label'];
                                    $data[$key]['notSortable'] = !empty($attributeDefs['notSortable']);
                                    $data[$key]['attributeDefs'] = array_merge($attributeDefs, ['name' => $attrField]);
                                    if (!empty($attributeDefs['channelName'])) {
                                        $data[$key]['label'] .= ' / ' . $attributeDefs['channelName'];
                                    }
                                    $data[$key]['customLabel'] = $data[$key]['label'];
                                }
                            }
                        }
                    }

                    foreach ($data as $key => $row) {
                        if (isset($row['name']) && !in_array($row['name'], $fields) && empty($row['attributeId'])) {
                            array_splice($data, $key, 1);
                        }
                    }
                    break;
            }
        }

        return $data;
    }

    protected function sanitizeInput(string $name): string
    {
        return preg_replace("([\.]{2,})", '', $name);
    }

    protected function getFileManager(): FileManager
    {
        return $this->container->get('fileManager');
    }

    protected function getMetadata(): \Atro\Core\Utils\Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getEntityManager(): \Espo\Core\Orm\EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getUser(): User
    {
        return $this->container->get('user');
    }

    private function getAcl(): \Espo\Core\Acl
    {
        return $this->container->get('acl');
    }

    protected function getDataManager(): DataManager
    {
        return $this->container->get('dataManager');
    }

    protected function getEventManager(): Manager
    {
        return $this->container->get('eventManager');
    }

    protected function getLanguage(): Language
    {
        return $this->container->get('language');
    }
}

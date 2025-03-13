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
use Atro\Core\Utils\FileManager;
use Atro\Core\Utils\Util;
use Atro\Core\EventManager\Event;
use Doctrine\DBAL\ParameterType;
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
     * @param string         $filePath   - File path, Ex. file.json
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
    public function get(string $scope, string $viewType, ?string $relatedEntity = null, ?string $layoutProfileId = null, bool $isAdminPage = false): array
    {
        // prepare scope
        $scope = $this->sanitizeInput($scope);

        // prepare name
        $viewType = $this->sanitizeInput($viewType);

        if (empty($relatedEntity)) {
            $relatedEntity = null;
        }

        $selectedProfileId = $this->getEntityManager()->getConnection()
            ->createQueryBuilder()
            ->select('layout_profile_id')
            ->from('user_entity_layout', 'uel')
            ->where('uel.user_id=:userId and uel.entity=:entity and uel.view_type=:viewType and '
                . (empty($relatedEntity) ? "uel.related_entity is null" : "uel.related_entity=:relatedEntity")
                . ' and deleted=:false')
            ->setParameters([
                'entity'        => $scope,
                'viewType'      => $viewType,
                'relatedEntity' => $relatedEntity,
                'userId'        => $this->getUser()->id,
            ])
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchOne();

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
        list($layout, $storedProfile) = $this->compose($scope, $viewType, $relatedEntity, $layoutProfileId);

        // remove fields from layout if this fields not exist in metadata
        $layout = $this->disableNotExistingFields($scope, $relatedEntity, $viewType, $layout);

        if ($viewType === 'list') {
            foreach ($layout as $k => $row) {
                if (!empty($row['name']) && empty($row['notSortable']) && !empty($this->getMetadata()->get(['entityDefs', $scope, 'fields', $row['name'], 'notStorable']))) {
                    $layout[$k]['notSortable'] = true;
                }
            }
        }

        $event = new Event([
            'target' => $scope . 'Layout',
            'params' => [
                'scope'           => $scope,
                'viewType'        => $viewType,
                'relatedEntity'   => $relatedEntity,
                'layoutProfileId' => $layoutProfileId,
                'isAdminPage'     => $isAdminPage,
                'isCustom'        => !empty($storedProfile),
            ],
            'result' => $layout]);

        $layout = $this->getEventManager()->dispatch('Layout', 'afterGetLayoutContent', $event)
            ->getArgument('result');

        $storedProfiles = $this->getEntityManager()->getConnection()
            ->createQueryBuilder()
            ->select('lp.id', 'lp.name')
            ->from('layout', 'l')
            ->innerJoin('l', 'layout_profile', 'lp', 'l.layout_profile_id=lp.id')
            ->where("l.entity=:entity and l.view_type=:viewType and "
                . (empty($relatedEntity) ? "l.related_entity is null" : "l.related_entity=:relatedEntity")
                . " and l.deleted=:false and lp.deleted=:false")
            ->setParameters([
                'entity'        => $scope,
                'viewType'      => $viewType,
                'relatedEntity' => $relatedEntity
            ])->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        return [
            'layout'            => $layout,
            'storedProfile'     => empty($storedProfile) ? [] : ['id' => $storedProfile->get('id'), 'name' => $storedProfile->get('name')],
            'storedProfiles'    => $storedProfiles,
            'selectedProfileId' => empty($selectedProfileId) ? null : $selectedProfileId,
            'canEdit'           => empty($layoutProfile) ? false : $this->getAcl()->check($layoutProfile, 'edit')
        ];
    }


    public function saveUserPreference(string $scope, string $viewType, ?string $relatedScope = null, ?string $layoutProfileId = null): bool
    {
        /* @var $repository RDB */
        $repository = $this->getEntityManager()->getRepository('UserEntityLayout');
        $record = $repository
            ->where([
                'userId'        => $this->getUser()->id,
                'entity'        => $scope,
                'viewType'      => $viewType,
                'relatedEntity' => empty($relatedScope) ? null : $relatedScope
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
                if (!empty($relatedScope)) {
                    $record->set('relatedEntity', $relatedScope);
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
    public function resetToDefault(string $scope, string $name, string $relatedScope, string $layoutProfileId)
    {
        $scope = $this->sanitizeInput($scope);
        $name = $this->sanitizeInput($name);

        $layoutRepo = $this->getEntityManager()->getRepository('Layout');
        $layoutRepo->where(['entity' => $scope, 'viewType' => $name, 'relatedEntity' => empty($relatedScope) ? null : $relatedScope, 'layoutProfileId' => $layoutProfileId])->removeCollection();

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
    public function save(string $scope, string $layoutName, string $relatedEntity, string $layoutProfileId, array $layoutData): bool
    {
        $layoutRepo = $this->getEntityManager()->getRepository('Layout');
        $where = ['entity' => $scope, 'viewType' => $layoutName, 'layoutProfileId' => $layoutProfileId];
        if (in_array($layoutName, ['list', 'detail'])) {
            if (!empty($relatedEntity)) {
                // validate related entity
                $isValid = false;
                foreach ($this->getMetadata()->get(['entityDefs', $relatedEntity, 'links']) ?? [] as $link => $data) {
                    if (!empty($data['entity']) && $data['entity'] === $scope) {
                        $isValid = true;
                        break;
                    }
                }

                if (empty($isValid)) {
                    throw new BadRequest("There is no relation between $relatedEntity and $scope");
                }
            }

            $where['relatedEntity'] = empty($relatedEntity) ? null : $relatedEntity;
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


    protected function getCustomLayout(string $scope, string $name, ?string $relatedEntity, ?string $layoutProfileId): array
    {
        $layoutRepo = $this->getEntityManager()->getRepository('Layout');
        $defaultLayoutProfileId = $this->getDefaultLayoutProfileId();
        $isOriginal = false;

        $where = [
            'entity' => $scope, 'viewType' => $name, 'relatedEntity' => empty($relatedEntity) ? null : $relatedEntity,
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
                $isOriginal = $profileId === $layoutProfileId;
                break;
            }
        }

        if (empty($layout) && !empty($relatedEntity)) {
            return $this->getCustomLayout($scope, $name, null, $layoutProfileId);
        }

        if (!empty($layout)) {
            return [$layout->getData($isOriginal), $layout->get('layoutProfile')];
        }
        return [[], null];
    }

    protected function compose(string $scope, string $name, ?string $relatedEntity, ?string $layoutProfileId): array
    {
        // from custom layout
        list ($customLayout, $storedProfile) = $this->getCustomLayout($scope, $name, $relatedEntity, $layoutProfileId);
        if (!empty($customLayout)) {
            return [$customLayout, $storedProfile];
        }

        // prepare data
        $data = [];

        if (!empty($relatedEntity)) {
            if ($name == 'list') {
                $data = $this->getLayoutFromFiles($scope, "listIn$relatedEntity");
            }
        }

        if (empty($data)) {
            $data = $this->getLayoutFromFiles($scope, $name);
        }


        // default by method
        if (empty($data)) {
            $type = $this->getMetadata()->get(['scopes', $scope, 'type']);
            $method = "getDefaultFor{$type}EntityType";
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

        if ($name === 'detail') {
            $data = $this->injectMultiLanguageFields($data, $scope);
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

    protected function injectMultiLanguageFields(array $data, string $scope): array
    {
        if (empty($multiLangFields = $this->getMultiLangFields($scope))) {
            return $data;
        }

        $exists = [];
        foreach ($data as $k => $panel) {
            // skip if no rows
            if (empty($panel['rows'])) {
                continue 1;
            }
            foreach ($panel['rows'] as $row) {
                foreach ($row as $field) {
                    if (!empty($field['name'])) {
                        $exists[] = $field['name'];
                    }
                }
            }
        }

        $result = [];
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
                            $localeFields = $this->getMultiLangLocalesFields($field['name']);

                            if (!empty($needToAdd = array_diff($localeFields, $exists))) {
                                $nextRow = $key != count($panel['rows']) - 1 ? $panel['rows'][$key + 1] : null;

                                if (!$fullWidthRow && !empty($nextRow)) {
                                    if (in_array(false, $nextRow)) {
                                        $item = null;
                                        foreach ($nextRow as $f) {
                                            if (is_array($f)) {
                                                $item = $f;
                                            }
                                        }

                                        if (in_array($item['name'], $localeFields)) {
                                            $newField = $field;
                                            $newField['name'] = array_shift($needToAdd);
                                            $newRow[] = $newField;
                                            $newRow[] = $item;

                                            $skip = true;
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

    protected function getPreparedLocalesCodes(): array
    {
        $result = [];

        foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
            $result[] = ucfirst(Util::toCamelCase(strtolower($locale)));
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
     * Disable fields from layout if this fields not exist in metadata
     *
     * @param string $scope
     * @param string $name
     * @param array  $data
     *
     * @return array
     */
    protected function disableNotExistingFields($scope, $relatedScope, $name, $data): array
    {
        // get entityDefs
        $entityDefs = $this->getMetadata()->get('entityDefs')[$scope] ?? [];

        // check if entityDefs exists
        if (!empty($entityDefs)) {
            // get fields for entity
            $fields = array_keys($entityDefs['fields']);
            if (!empty($relatedScope) && in_array($name, ['list', 'detail'])) {
                foreach ($this->getMetadata()->get(['entityDefs', $relatedScope, 'links']) ?? [] as $linkData) {
                    if (!empty($linkData['entity']) && $linkData['entity'] === $scope && !empty($linkData['relationName'])) {
                        $relationScope = ucfirst($linkData['relationName']);
                        $relationFields = array_keys($this->getMetadata()->get(['entityDefs', $relationScope, 'fields']) ?? []);
                        $relationFields = array_map(fn($f) => "{$relationScope}__{$f}", $relationFields);
                        $fields = array_merge($fields, $relationFields);
                        break;
                    }
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
                    foreach ($data as $key => $row) {
                        if (isset($row['name']) && !in_array($row['name'], $fields)) {
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

    protected function getMetadata(): \Espo\Core\Utils\Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getConfig(): \Espo\Core\Utils\Config
    {
        return $this->container->get('config');
    }

    protected function getEntityManager(): \Espo\Core\Orm\EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getUser(): \Espo\Entities\User
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
}

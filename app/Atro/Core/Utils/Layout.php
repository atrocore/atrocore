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

namespace Atro\Core\Utils;

use Atro\Core\Container;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Espo\Core\Acl;
use Espo\Core\Injectable;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\File;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Espo\Entities\Preferences;
use Espo\ORM\IEntity;
use Atro\Core\EventManager\Event;

/**
 * Class Layout
 */
class Layout extends Injectable
{
    protected array $changedData = [];
    protected array $customData = [];
    protected ?IEntity $defaultProfile;

    public function __construct()
    {
        $this->addDependency('container');
        $this->addDependency('acl');
        $this->addDependency('eventManager');
    }

    public function isCustom(string $scope, string $name): bool
    {
        return !empty($this->customData[$scope][$name]);
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
     * @param string $name
     *
     * @return json|string
     */
    public function get(string $scope, string $name, ?string $relatedEntity = null, ?string $layoutProfileId = null, bool $isAdminPage = false)
    {
        // prepare scope
        $scope = $this->sanitizeInput($scope);

        // prepare name
        $name = $this->sanitizeInput($name);

        // compose
        list($isCustom, $layout) = $this->compose($scope, $name, $relatedEntity, $layoutProfileId);

        // remove fields from layout if this fields not exist in metadata
        $layout = $this->disableNotExistingFields($scope, $name, $layout);

        if ($name === 'list') {
            foreach ($layout as $k => $row) {
                if (!empty($row['name']) && empty($row['notSortable']) && !empty($this->getMetadata()->get(['entityDefs', $scope, 'fields', $row['name'], 'notStorable']))) {
                    $layout[$k]['notSortable'] = true;
                }
            }
        }

        $event = new Event([
            'params' => [
                'scope'           => $scope,
                'name'            => $name,
                'relatedEntity'   => $relatedEntity,
                'layoutProfileId' => $layoutProfileId,
                'isAdminPage'     => $isAdminPage,
                'isCustom'        => $isCustom,
            ],
            'result' => $layout]);

        $layout = $this->getInjection('eventManager')->dispatch('Layout', 'afterGetLayoutContent', $event)
            ->getArgument('result');


        return Json::encode($layout);
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

        if (!empty($this->changedData[$scope]) && !empty($this->changedData[$scope][$name])) {
            unset($this->changedData[$scope][$name]);
        }
        $this->getContainer()->get('dataManager')->clearCache();

        return $this->get($scope, $name, $relatedScope, $layoutProfileId);
    }

    public function resetAllToDefault(string $layoutProfileId): bool
    {
        $layoutRepo = $this->getEntityManager()->getRepository('Layout');
        $layoutRepo->where(['layoutProfileId' => $layoutProfileId])->removeCollection();

        $this->getContainer()->get('dataManager')->clearCache();

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
        if ($layoutName === 'list') {
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
            return $layout->getData($isOriginal);
        }
        return [];
    }

    protected function compose(string $scope, string $name, ?string $relatedEntity, ?string $layoutProfileId): array
    {
        // from custom layout
        $customLayout = $this->getCustomLayout($scope, $name, $relatedEntity, $layoutProfileId);
        $this->customData[$scope][$name] = !empty($customLayout);
        if (!empty($customLayout)) {
            return [true, $customLayout];
        }

        // prepare data
        $data = [];

        if (!empty($relatedEntity)) {
            if ($name == 'list') {
                $data = $this->getLayoutFromFiles($scope, "listIn$relatedEntity");
            }

            if (empty($data)) {
                // for backward compatibility : use small layout files
                // Todo: Remove this later
                $data = $this->getLayoutFromFiles($scope, $name . 'Small');
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
            }
        }

        if (empty($data)) {
            // prepare file path
            $fileFullPath = $this->concatPath($this->concatPath(VENDOR_PATH . '/atrocore-legacy/app/Espo/Core/defaults', 'layouts'), $name . '.json');

            if (file_exists($fileFullPath)) {
                // get file data
                $fileData = $this->getFileManager()->getContents($fileFullPath);

                // prepare data
                $data = Json::decode($fileData, true);
            }
        }

        if ($name === 'detail') {
            $data = $this->injectMultiLanguageFields($data, $scope);
        }

        return [false, $data];
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
            case 'listSmall':
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
            case 'detailSmall':
                $data = [
                    [
                        "label" => "Overview",
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
    protected function disableNotExistingFields($scope, $name, $data): array
    {
        // get entityDefs
        $entityDefs = $this->getMetadata()->get('entityDefs')[$scope] ?? [];

        // check if entityDefs exists
        if (!empty($entityDefs)) {
            // get fields for entity
            $fields = array_keys($entityDefs['fields']);
            $fields[] = 'id';

            // remove fields from layout if this fields not exist in metadata
            switch ($name) {
                case 'filters':
                case 'massUpdate':
                    $data = array_values(array_intersect($data, $fields));
                    break;
                case 'detail':
                case 'detailSmall':
                    for ($key = 0; $key < count($data[0]['rows']); $key++) {
                        foreach ($data[0]['rows'][$key] as $fieldKey => $fieldData) {
                            if (isset($fieldData['name']) && !in_array($fieldData['name'], $fields)) {
                                $data[0]['rows'][$key][$fieldKey] = false;
                            }
                        }
                    }
                    break;
                case 'list':
                case 'listSmall':
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

    protected function getContainer(): Container
    {
        return $this->getInjection('container');
    }

    protected function getFileManager(): File\Manager
    {
        return $this->getContainer()->get('fileManager');
    }

    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }

    protected function getConfig(): Config
    {
        return $this->getContainer()->get('config');
    }

    protected function getEntityManager(): \Espo\Core\ORM\EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }

    protected function getUser(): \Espo\Entities\User
    {
        return $this->getContainer()->get('user');
    }


    private function getPreferences(): ?Preferences
    {
        return $this->getContainer()->get('preferences');
    }

    private function getAcl(): Acl
    {
        return $this->getInjection('acl');
    }

    public function getPreferencesId(): string
    {
        $preferences = $this->getPreferences();
        if (!empty($preferences)) {
            return $preferences->get('id');
        }
        return "";
    }
}

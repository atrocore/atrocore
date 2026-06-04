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
use Atro\Core\DataManager;
use Atro\Core\EventManager\Event;
use Atro\Core\EventManager\Manager;
use Atro\Repositories\Translation;
use Atro\Repositories\Translation as TranslationRepository;
use Atro\Services\AbstractService;
use Espo\Core\Utils\File\Unifier;
use Atro\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class Language
{
    public const string DEFAULT_LANGUAGE = 'en_US';

    protected Container $container;

    protected Unifier $unifier;
    protected array $data = [];
    protected array $deletedData = [];
    protected array $changedData = [];
    protected ?string $localeId;
    protected ?string $language = null;

    public function __construct(Container $container, ?string $localeId = null)
    {
        if ($localeId === null) {
            $user = $container->get('config')->get('isInstalled', false) ? $container->get('user') : null;
            $localeId = self::detectLocale($container->get('config'), $user);
        }

        $this->container = $container;
        $this->localeId = $localeId;
        $this->unifier = new Unifier($this->container->get('fileManager'), $this->getMetadata());
    }

    public static function detectLocale(Config $config, User $user = null): ?string
    {
        $localeId = AbstractService::getHeader('Locale-Id');

        if (empty($localeId) && $user) {
            $localeId = $user->get('localeId');
        }

        if (empty($localeId)) {
            $localeId = $config->get('locale');
        }

        return $localeId ?? null;
    }

    public static function detectLanguage(Config $config, User $user = null): ?string
    {
        $localeId = self::detectLocale($config, $user);
        if (!empty($localeId)) {
            return $config->get('locales')[$localeId]['language'] ?? self::DEFAULT_LANGUAGE;
        }

        return self::DEFAULT_LANGUAGE;
    }

    public function getLanguage(): string
    {
        return $this->getConfig()->get('locales')[$this->localeId]['language'] ?? self::DEFAULT_LANGUAGE;
    }

    public function setLanguage(string $languageCode): void
    {
        $this->language = $languageCode;
    }

    public function setLocale(?string $localeId): void
    {
        $this->localeId = $localeId;
    }

    public function translate(string $label, string $category = 'labels', string $scope = 'Global'): string
    {
        $translate = $this->getRepository()->findByCode("$scope.$category.$label");
        if ($translate === null) {
            return $label;
        }

        return $this->resolveTranslation($translate) ?? $label;
    }

    public function translateOption(string $value, string $field, string $scope = 'Global')
    {
        $translate = $this->getRepository()->findByCode("$scope.options.$field.$value");
        if ($translate === null) {
            return $value;
        }

        return $this->resolveTranslation($translate) ?? $value;
    }

    public function refreshTranslations(): void
    {
        $records = $this->getSimplifiedTranslates($this->getModulesData());

        $existingMap = $this->getRepository()->fetchExistingCodeMap();

        $toInsert = [];
        $toUpdate = [];
        $orphanedIds = [];

        foreach ($records as $key => $row) {
            if (!isset($existingMap[$key])) {
                $row['id'] = IdGenerator::uuid();
                $toInsert[] = $row;
            } elseif (!$existingMap[$key]['isCustomized']) {
                $row['id'] = $existingMap[$key]['id'];
                $toUpdate[] = $row;
            }
            // customized version exists — skip, do not touch
        }

        foreach ($existingMap as $code => $entry) {
            if (!$entry['isCustomized'] && !isset($records[$code])) {
                $orphanedIds[] = $entry['id'];
            }
        }

        foreach (array_chunk($orphanedIds, 1000) as $chunk) {
            $this->getRepository()->bulkDelete($chunk);
        }

        foreach (array_chunk($toUpdate, 1000) as $rows) {
            $this->getRepository()->bulkUpdate($rows);
        }

        foreach (array_chunk($toInsert, 1000) as $rows) {
            $this->getRepository()->bulkInsert($rows);
        }

        $this->getRepository()->refreshTimestamp([]);
    }

    public function getAll()
    {
        if (empty($this->data)) {
            $this->init();
        }

        if (!empty($this->language)) {
            $data = $this->data[self::DEFAULT_LANGUAGE];
            if ($this->language !== self::DEFAULT_LANGUAGE) {
                $data = Util::merge($this->data[self::DEFAULT_LANGUAGE], $this->data[$this->language]);
            }
        }

        if (!empty($this->localeId)) {
            $cacheName = "locale_{$this->localeId}";

            $data = $this->getDataManager()->getCacheData($cacheName);
            if (empty($data)) {
                $data = $this->data[self::DEFAULT_LANGUAGE];
                $locales = $this->getConfig()->get('locales') ?? [];

                $fallbackLanguage = $locales[$this->localeId]['fallbackLanguage'] ?? null;
                if (!empty($fallbackLanguage) && $fallbackLanguage !== self::DEFAULT_LANGUAGE) {
                    $data = Util::merge($data, $this->data[$fallbackLanguage]);
                }

                $language = $locales[$this->localeId]['language'] ?? self::DEFAULT_LANGUAGE;

                if (!empty($locales[$this->localeId]['displayLabelsInContentLanguage'])) {
                    $key = $locales[$this->localeId]['language'] . '_with_labels_in_content_language';
                    if (array_key_exists($key, $this->data)) {
                        $language = $key;
                    }
                }

                if (array_key_exists($language, $this->data)) {
                    $data = Util::merge($data, $this->data[$language]);
                }

                $this->getDataManager()->setCacheData($cacheName, $data);
            }
        }

        if (!empty($this->changedData)) {
            $data = Util::merge($data, $this->changedData);
        }

        if (!isset($data) || $data === false) {
            return null;
        }

        return Util::getValueByKey($data);
    }

    public function save()
    {
        if (!empty($this->changedData)) {
            $simplifiedTranslates = [];
            self::toSimpleArray($this->changedData, $simplifiedTranslates);

            foreach ($simplifiedTranslates as $key => $value) {
                $label = $this->getEntityManager()->getRepository('Translation')->getEntityByCode($key);
                if (empty($label)) {
                    $label = $this->getEntityManager()->getRepository('Translation')->get();
                    $label->id = md5($key);
                    $label->set(['code' => $key, 'module' => 'custom']);
                }
                $label->set('isCustomized', true);
                $label->set(Util::toCamelCase(strtolower($this->getLanguage())), $value);

                $this->getEntityManager()->saveEntity($label);
            }
        }

        if (!empty($this->deletedData)) {
            foreach ($this->deletedData as $scope => $unsetData) {
                foreach ($unsetData as $category => $names) {
                    if ($category === 'options') {
                        $newNames = [];
                        foreach ($names as $field => $options) {
                            foreach ($options as $option) {
                                $newNames[] = "$field.$option";
                            }
                        }
                        $names = $newNames;
                    }
                    foreach ($names as $name) {
                        $label = $this->getEntityManager()->getRepository('Translation')->getEntityByCode("$scope.$category.$name");
                        if (!empty($label) && $label->get('module') === 'custom' && !empty($label->get('isCustomized'))) {
                            $this->getEntityManager()->removeEntity($label);
                        }
                    }
                }
            }
        }

        $this->clearChanges();

        return true;
    }

    public function clearChanges(): void
    {
        $this->changedData = [];
        $this->deletedData = [];
        $this->init();
    }

    public function getModulesData(): array
    {
        $data = [];

        // load core
        $data['core'] = $this->unify(CORE_PATH . '/Atro/Resources/i18n');

        // load modules
        foreach ($this->getMetadata()->getModules() as $name => $module) {
            $data[$name] = [];
            $module->loadTranslates($data[$name]);
        }

        return $data;
    }

    public function set(string $scope, string $category, $name, $value): void
    {
        if (is_array($name)) {
            foreach ($name as $rowLabel => $rowValue) {
                $this->set($scope, $category, $rowLabel, $rowValue);
            }
            return;
        }

        $this->changedData[$scope][$category][$name] = $value;

        $currentLanguage = $this->getLanguage();
        if (!isset($this->data[$currentLanguage])) {
            $this->init();
        }
        $this->data[$currentLanguage][$scope][$category][$name] = $value;

        $this->undelete($scope, $category, $name);
    }

    public function setOption(string $scope, string $field, string $option, $value): void
    {
        $category = 'options';

        $this->changedData[$scope][$category][$field][$option] = $value;

        $currentLanguage = $this->getLanguage();
        if (!isset($this->data[$currentLanguage])) {
            $this->init();
        }
        $this->data[$currentLanguage][$scope][$category][$field][$option] = $value;

        $this->undeleteOption($scope, $field, $option);
    }

    public function delete(string $scope, string $category, $name): void
    {
        if (is_array($name)) {
            foreach ($name as $rowLabel) {
                $this->delete($scope, $category, $rowLabel);
            }
            return;
        }

        $this->deletedData[$scope][$category][] = $name;

        $currentLanguage = $this->getLanguage();
        if (!isset($this->data[$currentLanguage])) {
            $this->init();
        }

        if (isset($this->data[$currentLanguage][$scope][$category][$name])) {
            unset($this->data[$currentLanguage][$scope][$category][$name]);
        }

        if (isset($this->changedData[$scope][$category][$name])) {
            unset($this->changedData[$scope][$category][$name]);
        }
    }

    public function deleteOption(string $scope, string $field, $name): void
    {
        $category = 'options';
        $this->deletedData[$scope][$category][$field][] = $name;

        $currentLanguage = $this->getLanguage();
        if (!isset($this->data[$currentLanguage])) {
            $this->init();
        }

        if (isset($this->data[$currentLanguage][$scope][$category][$field][$name])) {
            unset($this->data[$currentLanguage][$scope][$category][$field][$name]);
        }

        if (isset($this->changedData[$scope][$category][$field][$name])) {
            unset($this->changedData[$scope][$category][$field][$name]);
        }
    }

    protected function undelete(string $scope, string $category, string $name): void
    {
        if (isset($this->deletedData[$scope][$category])) {
            foreach ($this->deletedData[$scope][$category] as $key => $labelName) {
                if ($name === $labelName) {
                    unset($this->deletedData[$scope][$category][$key]);
                }
            }
        }
    }

    protected function undeleteOption(string $scope, string $field, string $name): void
    {
        $category = 'options';
        if (isset($this->deletedData[$scope][$category][$field])) {
            foreach ($this->deletedData[$scope][$category][$field] as $key => $labelName) {
                if ($name === $labelName) {
                    unset($this->deletedData[$scope][$category][$field][$key]);
                }
            }
        }
    }

    public function clearCache(): void
    {
        $this->getDataManager()->clearCache(true);
    }

    protected function init(): void
    {
        /** @var bool $installed */
        $installed = $this->getConfig()->get('isInstalled', false);

        $data = [];

        if ($installed) {
            $data = $this->getPreparedTranslations();
        }

        if (empty($data)) {
            $data = $this->getModulesData();
        }

        $fullData = [];

        // load core
        if (!empty($data['core'])) {
            $fullData = Util::merge($fullData, $data['core']);
        }

        // load modules
        foreach ($this->getMetadata()->getModules() as $name => $module) {
            if (!empty($data[$name])) {
                $fullData = Util::merge($fullData, $data[$name]);
            }
        }

        // load custom
        if (!empty($data['custom'])) {
            $fullData = Util::merge($fullData, $data['custom']);
        }

        foreach ($fullData as $i18nName => $i18nData) {
            if (!empty($i18nData['User']) && !empty($i18nData['Global']['labels']['Followed'])) {
                foreach ($this->getMetadata()->get("entityDefs.User.links") as $link => $defs) {
                    if (!empty($defs['foreign']) && $defs['foreign'] === 'followers' && !empty($i18nData['Global']['scopeNamesPlural'][$defs['entity']])) {
                        $i18nData['User']['fields'][$link] = $i18nData['Global']['scopeNamesPlural'][$defs['entity']] . ' (' . $i18nData['Global']['labels']['Followed'] . ')';
                    }
                }
                $i18nData['UserProfile'] = $i18nData['User'];
            }
            $this->data[$i18nName] = $i18nData;
        }

        if ($installed) {
            $this->data = $this->getEventManager()
                ->dispatch('Language', 'modify', new Event(['data' => $this->data]))
                ->getArgument('data');
        }
    }

    public static function getLocalizedFieldName(Container $container, string $scope, string $fieldName): string
    {
        $user = $container->get('user');
        $config = $container->get('config');

        if (!empty($user) && !empty($config->get('isMultilangActive')) && !empty($container->get('metadata')->get(['entityDefs', $scope, 'fields', $fieldName, 'isMultilang']))) {
            $userLanguageCode = self::detectLanguage($config, $user);
            $mainLanguageCode = $config->get('mainLanguage');

            if (!in_array($userLanguageCode, $config->get('inputLanguageList'))) {
                $userLanguageCode = null;
            }

            $field = $fieldName;

            if (!empty($userLanguageCode) && $userLanguageCode !== $mainLanguageCode) {
                $field .= ucfirst(Util::toCamelCase(strtolower($userLanguageCode)));
            }

            return $field;
        }

        return $fieldName;
    }

    protected function unify(string $path): array
    {
        return $this->unifier->unify('i18n', $path, true);
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getEventManager(): Manager
    {
        return $this->container->get('eventManager');
    }

    protected function getDataManager(): DataManager
    {
        return $this->container->get('dataManager');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected static function languageToField(string $language): string
    {
        return Util::toCamelCase(strtolower($language));
    }

    private function resolveTranslation(Entity $translate): ?string
    {
        if (!empty($this->language)) {
            return $translate->get(self::languageToField($this->language)) ?? $translate->get(self::languageToField(self::DEFAULT_LANGUAGE));
        }

        if (empty($this->localeId)) {
            return null;
        }

        $locales = $this->getConfig()->get('locales') ?? [];
        $language = $locales[$this->localeId]['language'] ?? null;

        if (!empty($locales[$this->localeId]['displayLabelsInContentLanguage'])) {
            $key = $language . '_with_labels_in_content_language';
            if (array_key_exists($key, $this->data)) {
                $language = $key;
            }
        }

        if (!empty($language)) {
            $res = $translate->get(self::languageToField($language));
            if ($res !== null) {
                return $res;
            }
        }

        if (!empty($locales[$this->localeId]['fallbackLanguage'])) {
            $res = $translate->get(self::languageToField($locales[$this->localeId]['fallbackLanguage']));
            if ($res !== null) {
                return $res;
            }
        }

        return $translate->get(self::languageToField(self::DEFAULT_LANGUAGE));
    }

    private function getPreparedTranslations(): array
    {
        $preparedTranslationData = [];
        foreach ($this->getEntityManager()->getRepository('Translation')->find()->toArray() as $record) {
            foreach ($this->getMetadata()->get('multilang.languageList', []) as $locale) {
                $row = [];
                $field = Util::toCamelCase(strtolower($locale));
                if (array_key_exists($field, $record) && $record[$field] !== null) {
                    $insideRow = [];

                    $hasDots = strpos($record['code'], '...') !== false;
                    $parts = explode('.', $record['code']);
                    if ($hasDots) {
                        array_pop($parts);
                        array_pop($parts);
                        array_pop($parts);
                        $parts[] = array_pop($parts) . '...';
                    }

                    $this->prepareTreeValue($parts, $insideRow, $record[$field]);
                    $row[$record['module']][$locale] = $insideRow;
                    $preparedTranslationData = Util::merge($preparedTranslationData, $row);
                }
            }
        }
        // remove normalize number key to remove __integer
        $this->normalizeIntegerKey($preparedTranslationData);

        return $preparedTranslationData;
    }

    private function normalizeIntegerKey(array &$data): void
    {
        foreach ($data as $key => $value) {
            if (is_array($data[$key])) {
                $this->normalizeIntegerKey($data[$key]);
            }
            if (is_string($key) && str_starts_with($key, '__integer__')) {
                $realKey = str_replace('__integer__', '', $key);
                $data[$realKey] = $data[$key];;
                unset($data[$key]);
            }
        }
    }

    private function prepareTreeValue(array $data, &$result, $value): void
    {
        if (!empty($data)) {
            $first = array_shift($data);
            if (!empty($data)) {
                $this->prepareTreeValue($data, $result[$first], $value);
            } else {
                if (preg_match('/^[0-9]+$/', $first)) {
                    $first = '__integer__' . $first;
                }
                $result[$first] = $value;
            }
        }
    }

    private function getSimplifiedTranslates(array $data): array
    {
        $records = [];
        foreach ($data as $module => $moduleData) {
            foreach ($moduleData as $locale => $localeData) {
                $preparedLocaleData = [];
                self::toSimpleArray($localeData, $preparedLocaleData);
                foreach ($preparedLocaleData as $key => $value) {
                    $records[$key]['code'] = $key;
                    $records[$key]['module'] = $module;
                    $records[$key]['isCustomized'] = $module === 'custom';
                    $records[$key]['createdAt'] = date('Y-m-d H:i:s');
                    $records[$key][Util::toCamelCase(strtolower($locale))] = $value;
                }
            }
        }

        return $records;
    }

    private static function toSimpleArray(array $data, array &$result, array &$parents = []): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $parents[] = $key;
                self::toSimpleArray($value, $result, $parents);
            } else {
                $result[implode('.', array_merge($parents, [$key]))] = $value;
            }
        }

        if (!empty($parents)) {
            array_pop($parents);
        }
    }

    private function getRepository(): Translation
    {
        return $this->getEntityManager()->getRepository('Translation');
    }
}

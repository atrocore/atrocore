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
use Atro\Core\EventManager\Event;
use Atro\Core\EventManager\Manager;
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
            return $data;
        }

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

        return $data;
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

    private function init(): void
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

    private static function languageToField(string $language): string
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
        $languages = [self::languageToField(self::DEFAULT_LANGUAGE) => self::DEFAULT_LANGUAGE];
        foreach ($this->getConfig()->get('locales') ?? [] as $locale) {
            if (!empty($locale['language'])) {
                $languages[self::languageToField($locale['language'])] = $locale['language'];
            }
            if (!empty($locale['fallbackLanguage'])) {
                $languages[self::languageToField($locale['fallbackLanguage'])] = $locale['fallbackLanguage'];
            }
        }
        $languageFields = array_keys($languages);

        $translations = $this->getRepository()
            ->select(array_merge(['id', 'code', 'module'], $languageFields))
            ->find();

        $preparedTranslationData = [];
        foreach ($translations as $translation) {
            $code = $translation->get('code');
            foreach ($languages as $field => $language) {
                if ($translation->get($field) === null) {
                    continue;
                }

                $row = [];
                $insideRow = [];

                $parts = explode('.', $code);

                if (str_contains($code, '...') !== false) {
                    array_pop($parts);
                    array_pop($parts);
                    array_pop($parts);
                    $parts[] = array_pop($parts) . '...';
                }

                $this->prepareTreeValue($parts, $insideRow, $translation->get($field));
                $row[$translation->get('module')][$language] = $insideRow;
                $preparedTranslationData = Util::merge($preparedTranslationData, $row);
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

    private function getModulesData(): array
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

    private function getRepository(): TranslationRepository
    {
        return $this->getEntityManager()->getRepository('Translation');
    }

    private function unify(string $path): array
    {
        return $this->unifier->unify('i18n', $path, true);
    }

    private function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    private function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    private function getEventManager(): Manager
    {
        return $this->container->get('eventManager');
    }

    private function getConfig(): Config
    {
        return $this->container->get('config');
    }
}

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
use Atro\Core\LanguageResolvers\AbstractLanguageResolver;
use Atro\DTOs\TranslationKeyDTO;
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
    protected ?string $localeId;
    protected ?string $language = null;

    private array $translateCache = [];
    private ?array $fieldToLanguageCode = null;
    private ?array $resolvers = null;
    private ?array $storedTranslations = null;
    private ?array $fullTranslations = null;
    private array $resolvingKeys = [];

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

    /**
     * $GLOBALS['localeId'] has super priority over everything else
     */
    public static function detectLocale(Config $config, ?User $user = null): ?string
    {
        if (!empty($GLOBALS['localeId'])) {
            return $GLOBALS['localeId'];
        }

        $localeId = AbstractService::getHeader('Locale-Id');

        if (empty($localeId) && $user) {
            $localeId = $user->get('localeId');
        }

        if (empty($localeId)) {
            $localeId = $config->get('locale');
        }

        return $localeId ?? null;
    }

    public static function detectLanguage(Config $config, ?User $user = null): ?string
    {
        $localeId = self::detectLocale($config, $user);
        if (!empty($localeId)) {
            return $config->get('locales')[$localeId]['language'] ?? self::DEFAULT_LANGUAGE;
        }

        return self::DEFAULT_LANGUAGE;
    }

    public function getLanguage(): string
    {
        if (!empty($this->language)) {
            return $this->language;
        }

        return $this->getConfig()->get('locales')[$this->localeId]['language'] ?? self::DEFAULT_LANGUAGE;
    }

    public function getLocaleId(): ?string
    {
        return $this->localeId;
    }

    public function setLanguage(string $languageCode): void
    {
        $this->language = $languageCode;
        $this->storedTranslations = null;
    }

    public function setLocale(?string $localeId): void
    {
        $this->localeId = $localeId;
        $this->storedTranslations = null;
    }

    public function translate(string $name, string $category = 'labels', string $scope = 'Global'): string
    {
        if (!isset($this->translateCache[$this->localeId][$scope][$category][$name])) {
            $value = $this->resolveKey(new TranslationKeyDTO($scope, $category, $name));

            if ($value === null && $scope !== 'Global') {
                $value = $this->translate($name, $category, 'Global');
            }

            $this->translateCache[$this->localeId][$scope][$category][$name] = $value ?? $name;
        }

        return $this->translateCache[$this->localeId][$scope][$category][$name];
    }

    public function translateOption(string $value, string $field, string $scope = 'Global'): string
    {
        if (!isset($this->translateCache[$this->localeId][$scope]['options'][$field][$value])) {
            $resolved = $this->resolveKey(new TranslationKeyDTO($scope, 'options', $field, $value));

            $this->translateCache[$this->localeId][$scope]['options'][$field][$value] = $resolved ?? $value;
        }

        return $this->translateCache[$this->localeId][$scope]['options'][$field][$value];
    }

    public function findTranslation(TranslationKeyDTO $key, ?AbstractLanguageResolver $except = null): ?string
    {
        return $this->resolveKey($key, $except);
    }

    public function getStoredTranslation(TranslationKeyDTO $key): ?string
    {
        if ($this->storedTranslations !== null) {
            return $this->readFromTree($this->storedTranslations, $key);
        }

        $translation = $this->findStoredEntity($key);

        return $translation === null ? null : $this->resolveTranslation($translation);
    }

    public function getStoredTranslationInCurrentLanguage(TranslationKeyDTO $key): ?string
    {
        if ($this->fullTranslations !== null) {
            return $this->readFromTree($this->getTranslationsInCurrentLanguage(), $key);
        }

        $translation = $this->findStoredEntity($key);

        return $translation?->get(self::languageToField($this->getLanguage()));
    }

    private function readFromTree(array $tree, TranslationKeyDTO $key): ?string
    {
        $value = $key->option === null
            ? ($tree[$key->scope][$key->category][$key->name] ?? null)
            : ($tree[$key->scope]['options'][$key->name][$key->option] ?? null);

        return is_string($value) ? $value : null;
    }

    private function findStoredEntity(TranslationKeyDTO $key): ?Entity
    {
        return $key->option === null
            ? $this->getRepository()->getTranslation($key->scope, $key->category, $key->name)
            : $this->getRepository()->getOptionTranslation($key->scope, $key->name, $key->option);
    }

    /**
     * @return AbstractLanguageResolver[]
     */
    public function getResolvers(): array
    {
        if ($this->resolvers === null) {
            $this->resolvers = [];

            if ($this->getConfig()->get('isInstalled', false)) {
                foreach ($this->getMetadata()->get(['app', 'languageResolvers'], []) as $resolverDefs) {
                    if (empty($resolverDefs['className']) || !class_exists($resolverDefs['className'])) {
                        continue;
                    }
                    $this->resolvers[] = new $resolverDefs['className']($this->container, $this);
                }
            }
        }

        return $this->resolvers;
    }

    private function resolveKey(TranslationKeyDTO $key, ?AbstractLanguageResolver $except = null): ?string
    {
        $code = $key->getCode() . ($except === null ? '' : '|' . get_class($except));

        if (isset($this->resolvingKeys[$code])) {
            $GLOBALS['log']->warning("Language: cyclic translation resolving detected for '$code'.");

            return $this->getStoredTranslation($key);
        }

        $this->resolvingKeys[$code] = true;

        try {
            foreach ($this->getResolvers() as $resolver) {
                if ($resolver === $except || !$resolver->decoratesStoredTranslation() || !$resolver->supports($key)) {
                    continue;
                }
                $value = $resolver->resolve($key);
                if ($value !== null) {
                    return $value;
                }
            }

            $exact = $this->getStoredTranslationInCurrentLanguage($key);
            if ($exact !== null) {
                return $exact;
            }

            $inheritedScope = $this->getInheritedScope($key->scope);
            if ($inheritedScope !== null) {
                $value = $this->resolveKey($this->keyForScope($key, $inheritedScope));
                if ($value !== null) {
                    return $value;
                }
            }

            foreach ($this->getResolvers() as $resolver) {
                if ($resolver === $except || !!$resolver->decoratesStoredTranslation() || !$resolver->supports($key)) {
                    continue;
                }
                $value = $resolver->resolve($key);
                if ($value !== null) {
                    return $value;
                }
            }

            return $this->getStoredTranslation($key);
        } finally {
            unset($this->resolvingKeys[$code]);
        }
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
                $row['customizedLanguages'] = json_encode([]);
                $toInsert[] = $row;
                continue;
            }

            $existing = $existingMap[$key];
            $customizedLanguages = $existing['customizedLanguages'];

            $updateRow = ['id' => $existing['id'], 'code' => $key, 'module' => $row['module']];
            $hasChanges = ($row['module'] !== $existing['module']);

            foreach ($row as $field => $value) {
                if (in_array($field, ['code', 'module', 'createdAt'], true)) {
                    continue;
                }

                $languageCode = $this->getLanguageCodeForField($field);
                if ($languageCode !== null && in_array($languageCode, $customizedLanguages, true)) {
                    continue;
                }

                $currentValue = $existing['values'][$field] ?? null;
                if ((string)$currentValue !== (string)$value) {
                    $updateRow[$field] = $value;
                    $hasChanges = true;
                }
            }

            if ($hasChanges) {
                $toUpdate[] = $updateRow;
            }
        }

        foreach ($existingMap as $code => $entry) {
            if ($entry['module'] === 'custom' || !empty($entry['customizedLanguages'])) {
                continue;
            }
            if (!isset($records[$code])) {
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

    private function getLanguageCodeForField(string $field): ?string
    {
        if ($this->fieldToLanguageCode === null) {
            $this->fieldToLanguageCode = [];
            // same source as the customizedLanguages field's own options (Locale.languageCode values, see Listeners/Metadata.php)
            foreach ($this->getMetadata()->get(['entityDefs', 'Translation', 'fields', 'customizedLanguages', 'options'], []) as $code) {
                $this->fieldToLanguageCode[Util::toCamelCase(strtolower($code))] = $code;
            }
        }

        return $this->fieldToLanguageCode[$field] ?? null;
    }

    public function getAll(): array
    {
        $result = $this->getStoredTranslations();

        foreach ($this->collectKeys() as $key) {
            $value = $this->findTranslation($key);
            if ($value === null) {
                continue;
            }

            if ($key->option === null) {
                $result[$key->scope][$key->category][$key->name] = $value;
            } else {
                $result[$key->scope]['options'][$key->name][$key->option] = $value;
            }
        }

        return $this->applyLanguageListeners($result);
    }

    /**
     * @deprecated use a language resolver instead
     */
    private function applyLanguageListeners(array $result): array
    {
        if (!$this->getConfig()->get('isInstalled', false)) {
            return $result;
        }

        $language = $this->getLanguage();

        $data = $this->getEventManager()
            ->dispatch('Language', 'modify', new Event(['data' => [$language => $result]]))
            ->getArgument('data');

        return $data[$language] ?? $result;
    }

    /**
     * @return TranslationKeyDTO[]
     */
    private function collectKeys(): array
    {
        $keys = [];

        foreach ($this->getResolvers() as $resolver) {
            foreach ($resolver->getKeys() as $key) {
                $keys[$key->getCode()] = $key;
            }
        }

        foreach ($this->getStoredTranslations() as $scope => $categories) {
            foreach ($categories as $category => $items) {
                if (!is_array($items)) {
                    continue;
                }
                foreach ($items as $name => $value) {
                    if ($category === 'options' && is_array($value)) {
                        foreach ($value as $option => $optionValue) {
                            if (!is_string($optionValue)) {
                                continue;
                            }
                            $stored = new TranslationKeyDTO($scope, 'options', (string)$name, (string)$option);
                            $keys[$stored->getCode()] = $stored;
                        }
                        continue;
                    }
                    if (!is_string($value)) {
                        continue;
                    }
                    $stored = new TranslationKeyDTO($scope, (string)$category, (string)$name);
                    $keys[$stored->getCode()] = $stored;
                }
            }
        }

        $byScope = [];
        foreach ($keys as $key) {
            $byScope[$key->scope][] = $key;
        }

        foreach (array_keys($this->getMetadata()->get('scopes', [])) as $scope) {
            $seen = [$scope => true];

            for ($source = $this->getInheritedScope((string)$scope); $source !== null; $source = $this->getInheritedScope($source)) {
                if (isset($seen[$source])) {
                    break;
                }
                $seen[$source] = true;

                foreach ($byScope[$source] ?? [] as $key) {
                    $inherited = $this->keyForScope($key, (string)$scope);
                    $keys[$inherited->getCode()] = $inherited;
                }
            }
        }

        return $keys;
    }


    private function getInheritedScope(string $scope): ?string
    {
        if ($scope === 'UserProfile') {
            return 'User';
        }

        $scopeDefs = $this->getMetadata()->get(['scopes', $scope], []);
        if (empty($scopeDefs['type'])) {
            return null;
        }

        $source = $scopeDefs['primaryEntityId'] ?? $scopeDefs['derivativeForRelation'] ?? null;

        return $source === $scope ? null : $source;
    }

    private function keyForScope(TranslationKeyDTO $key, string $scope): TranslationKeyDTO
    {
        return new TranslationKeyDTO($scope, $key->category, $key->name, $key->option);
    }

    public function getStoredTranslations(): array
    {
        if ($this->storedTranslations !== null) {
            return $this->storedTranslations;
        }

        $fullData = $this->getFullTranslations();

        $result = $fullData[self::DEFAULT_LANGUAGE] ?? [];

        if (!empty($this->language)) {
            if ($this->language !== self::DEFAULT_LANGUAGE) {
                $result = Util::merge($result, $fullData[$this->language] ?? []);
            }

            return $this->storedTranslations = $result;
        }

        $locales = $this->getConfig()->get('locales') ?? [];

        $fallbackLanguage = $locales[$this->localeId]['fallbackLanguage'] ?? null;
        if (!empty($fallbackLanguage) && $fallbackLanguage !== self::DEFAULT_LANGUAGE) {
            $result = Util::merge($result, $fullData[$fallbackLanguage] ?? []);
        }

        $language = $locales[$this->localeId]['language'] ?? self::DEFAULT_LANGUAGE;

        if (array_key_exists($language, $fullData)) {
            $result = Util::merge($result, $fullData[$language]);
        }

        return $this->storedTranslations = $result;
    }

    public function preload(): void
    {
        $this->getStoredTranslations();
    }

    public function clearCache(): void
    {
        $this->translateCache = [];
        $this->storedTranslations = null;
        $this->fullTranslations = null;
    }

    public function getTranslationsInCurrentLanguage(): array
    {
        return $this->getFullTranslations()[$this->getLanguage()] ?? [];
    }

    private function getFullTranslations(): array
    {
        if ($this->fullTranslations !== null) {
            return $this->fullTranslations;
        }

        $data = [];

        if ($this->getConfig()->get('isInstalled', false)) {
            $data = $this->getPreparedTranslations();
        }

        if (empty($data)) {
            $data = $this->getModulesData();
        }

        $fullData = [];

        if (!empty($data['core'])) {
            $fullData = Util::merge($fullData, $data['core']);
        }

        foreach ($this->getMetadata()->getModules() as $name => $module) {
            if (!empty($data[$name])) {
                $fullData = Util::merge($fullData, $data[$name]);
            }
        }

        if (!empty($data['custom'])) {
            $fullData = Util::merge($fullData, $data['custom']);
        }

        return $this->fullTranslations = $fullData;
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

    /**
     * Sets a translation value to memory cache for the specified scope, category, and name.
     *
     * @param string $scope    The scope of the translation.
     * @param string $category The category of the translation.
     * @param string $name     The name of the translation entry.
     * @param string $value    The translation value to set.
     *
     * @return void
     */
    public function set(string $scope, string $category, string $name, string $value): void
    {
        $this->translateCache[$this->localeId][$scope][$category][$name] = $value;
    }

    public static function languageToField(string $language): string
    {
        return TranslationRepository::languageToField($language);
    }

    private function resolveTranslation(Entity $translation): ?string
    {
        if (!empty($this->language)) {
            return $translation->get(self::languageToField($this->language)) ?? $translation->get(self::languageToField(self::DEFAULT_LANGUAGE));
        }

        if (empty($this->localeId)) {
            return null;
        }

        $locales = $this->getConfig()->get('locales') ?? [];
        $language = $locales[$this->localeId]['language'] ?? null;

        if (!empty($language)) {
            $res = $translation->get(self::languageToField($language));
            if ($res !== null) {
                return $res;
            }
        }

        if (!empty($locales[$this->localeId]['fallbackLanguage'])) {
            $res = $translation->get(self::languageToField($locales[$this->localeId]['fallbackLanguage']));
            if ($res !== null) {
                return $res;
            }
        }

        return $translation->get(self::languageToField(self::DEFAULT_LANGUAGE));
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

    public function getSimplifiedTranslates(array $data): array
    {
        $records = [];
        foreach ($data as $module => $moduleData) {
            foreach ($moduleData as $locale => $localeData) {
                $preparedLocaleData = [];
                self::toSimpleArray($localeData, $preparedLocaleData);
                foreach ($preparedLocaleData as $key => $value) {
                    $records[$key]['code'] = $key;
                    $records[$key]['module'] = $module;
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

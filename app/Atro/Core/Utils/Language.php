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
use Atro\Repositories\Translation as TranslationRepository;
use Espo\Core\Utils\File\Unifier;
use Espo\Entities\Preferences;

class Language
{
    public const DEFAULT_LANGUAGE = 'en_US';

    protected Container $container;

    protected Unifier $unifier;
    protected array $data = [];
    protected array $deletedData = [];
    protected array $changedData = [];
    protected string $currentLanguage;

    public function __construct(Container $container, string $currentLanguage = null)
    {
        if ($currentLanguage === null) {
            $preferences = $container->get('config')->get('isInstalled', false) ? $container->get('preferences') : null;
            $currentLanguage = self::detectLanguage($container->get('config'), $preferences);
        }

        $this->container = $container;
        $this->currentLanguage = $currentLanguage;
        $this->unifier = new Unifier($this->container->get('fileManager'), $this->getMetadata());
    }

    public static function detectLanguage(Config $config, Preferences $preferences = null): string
    {
        if ($preferences) {
            $language = $preferences->get('language');
        }

        if (empty($language)) {
            $language = $config->get('language');
        }

        if (empty($language)) {
            $language = self::DEFAULT_LANGUAGE;
        }

        return $language;
    }

    public function getLanguage(): string
    {
        return $this->currentLanguage;
    }

    public function setLanguage(string $language): void
    {
        $this->currentLanguage = $language;
    }

    public function translate($label, $category = 'labels', $scope = 'Global', $requiredOptions = null)
    {
        if (is_array($label)) {
            $translated = [];

            foreach ($label as $subLabel) {
                $translated[$subLabel] = $this->translate($subLabel, $category, $scope, $requiredOptions);
            }

            return $translated;
        }

        $key = $scope . '.' . $category . '.' . $label;
        $translated = $this->get($key);

        if (!isset($translated)) {
            $key = 'Global.' . $category . '.' . $label;
            $translated = $this->get($key, $label);
        }

        if (is_array($translated) && isset($requiredOptions)) {

            $translated = array_intersect_key($translated, array_flip($requiredOptions));

            $optionKeys = array_keys($translated);
            foreach ($requiredOptions as $option) {
                if (!in_array($option, $optionKeys)) {
                    $translated[$option] = $option;
                }
            }
        }

        return $translated;
    }

    public function translateOption($value, $field, $scope = 'Global')
    {
        $options = $this->get($scope . '.options.' . $field);
        if (is_array($options) && array_key_exists($value, $options)) {
            return $options[$value];
        } else {
            if ($scope !== 'Global') {
                $options = $this->get('Global.options.' . $field);
                if (is_array($options) && array_key_exists($value, $options)) {
                    return $options[$value];
                }
            }
        }
        return $value;
    }

    public function get($key = null, $returns = null)
    {
        $data = $this->getData();

        if (!isset($data) || $data === false) {
            return null;
        }

        return Util::getValueByKey($data, $key, $returns);
    }

    public function getAll()
    {
        return $this->get();
    }

    public function save()
    {
        if (!empty($this->changedData)) {
            $simplifiedTranslates = [];
            TranslationRepository::toSimpleArray($this->changedData, $simplifiedTranslates);

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

    public function clearCache(): void
    {
        foreach ($this->getMetadata()->get('multilang.languageList', []) as $language) {
            $cacheFile = "data/cache/{$language}.json";
            if (file_exists($cacheFile)) {
                @unlink($cacheFile);
            }
        }
    }

    protected function init(): void
    {
        /** @var bool $installed */
        $installed = $this->getConfig()->get('isInstalled', false);

        $data = [];

        if ($installed) {
            $data = $this->getEntityManager()->getRepository('Translation')->getPreparedTranslations();
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
            $this->data[$i18nName] = $i18nData;
        }

        if ($installed) {
            $this->data = $this->getEventManager()->dispatch('Language', 'modify', new Event(['data' => $this->data]))->getArgument('data');
        }
    }

    protected function getData()
    {
        $currentLanguage = $this->getLanguage();
        if (empty($this->data)) {
            $this->init();
        }

        if ($currentLanguage === self::DEFAULT_LANGUAGE) {
            return $this->data[$currentLanguage];
        }

        if (empty($data = $this->getDataManager()->getCacheData($currentLanguage))) {
            $data = $this->data[self::DEFAULT_LANGUAGE];

            foreach ($this->getConfig()->get('locales', []) as $locale) {
                if (empty($locale['fallbackLanguage'])) {
                    continue;
                }
                if ($locale['language'] !== $currentLanguage) {
                    continue;
                }
                if (!isset($this->data[$locale['fallbackLanguage']])) {
                    continue;
                }
                $data = Util::merge($data, $this->data[$locale['fallbackLanguage']]);
            }

            if (isset($this->data[$currentLanguage])) {
                $data = Util::merge($data, $this->data[$currentLanguage]);
            }

            $this->getDataManager()->setCacheData($currentLanguage, $data);
        }

        return $data;
    }

    protected function unify(string $path): array
    {
        return $this->unifier->unify('i18n', $path, true);
    }

    protected function getEntityManager(): \Espo\ORM\EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getMetadata(): \Espo\Core\Utils\Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getEventManager(): \Atro\Core\EventManager\Manager
    {
        return $this->container->get('eventManager');
    }

    protected function getDataManager(): \Atro\Core\DataManager
    {
        return $this->container->get('dataManager');
    }

    protected function getConfig(): \Espo\Core\Utils\Config
    {
        return $this->container->get('config');
    }
}

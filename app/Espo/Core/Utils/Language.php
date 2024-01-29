<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

declare(strict_types=1);

namespace Espo\Core\Utils;

use Atro\Console\RefreshTranslations;
use Atro\Core\Container;
use Doctrine\DBAL\ParameterType;
use Espo\Core\EventManager\Event;
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\File\Unifier;
use Espo\Entities\Preferences;

/**
 * Class Language
 */
class Language
{
    public const DEFAULT_LANGUAGE = 'en_US';

    /**
     * @var Unifier
     */
    private $unifier;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var array
     */
    private $deletedData = [];

    /**
     * @var array
     */
    private $changedData = [];

    /**
     * @var string
     */
    private $currentLanguage;

    /**
     * @var bool
     */
    private $noCustom;

    /**
     * @var string
     */
    private $corePath = CORE_PATH . '/Atro/Resources/i18n';

    /**
     * @var string
     */
    private $customPath = 'custom/Espo/Custom/Resources/i18n';

    /**
     * @var Container
     */
    private $container;

    /**
     * Language constructor.
     *
     * @param Container $container
     * @param string    $currentLanguage
     * @param bool      $noCustom
     */
    public function __construct(Container $container, string $currentLanguage = self::DEFAULT_LANGUAGE, bool $noCustom = false)
    {
        $this->container = $container;
        $this->currentLanguage = $currentLanguage;
        $this->noCustom = $noCustom;
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

    /**
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->currentLanguage;
    }

    /**
     * @param string $language
     */
    public function setLanguage(string $language): void
    {
        $this->currentLanguage = $language;
    }

    /**
     * @param string $label
     * @param string $category
     * @param string $scope
     * @param null   $requiredOptions
     *
     * @return array|mixed
     * @throws Error
     */
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

    /**
     * @param string $value
     * @param string $field
     * @param string $scope
     *
     * @return mixed
     * @throws Error
     */
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

    /**
     * @param mixed $key
     * @param mixed $returns
     *
     * @return mixed
     * @throws Error
     */
    public function get($key = null, $returns = null)
    {
        $data = $this->getData();

        if (!isset($data) || $data === false) {
            return null;
        }

        return Util::getValueByKey($data, $key, $returns);
    }

    /**
     * @return mixed
     * @throws Error
     */
    public function getAll()
    {
        return $this->get();
    }

    /**
     * @return bool
     */
    public function save()
    {
        $field = Util::toCamelCase(strtolower($this->getLanguage()));

        if (!empty($this->changedData)) {
            $simplifiedTranslates = [];
            RefreshTranslations::toSimpleArray($this->changedData, $simplifiedTranslates);

            foreach ($simplifiedTranslates as $key => $value) {
                $label = $this->getEntityManager()->getRepository('Translation')->where(['name' => $key])->findOne();
                if (empty($label)) {
                    $label = $this->getEntityManager()->getRepository('Translation')->get();
                    $label->set(['name' => $key, 'module' => 'custom']);
                }
                $label->set('isCustomized', true);
                $label->set($field, $value);
                $this->getEntityManager()->saveEntity($label);
            }
        }

        if (!empty($this->deletedData)) {
            foreach ($this->deletedData as $scope => $unsetData) {
                foreach ($unsetData as $category => $names) {
                    foreach ($names as $name) {
                        $label = $this->getEntityManager()->getRepository('Translation')->where(['name' => "$scope.$category.$name", 'module' => 'custom', 'isCustomized' => true])->findOne();
                        if (!empty($label)) {
                            $this->getEntityManager()->removeEntity($label);
                        }
                    }
                }
            }
        }

        $this->clearChanges();

        return true;
    }

    /**
     * Clear unsaved changes
     *
     * @return void
     */
    public function clearChanges(): void
    {
        $this->changedData = [];
        $this->deletedData = [];
        $this->init();
    }

    /**
     * @return array
     */
    public function getModulesData(): array
    {
        $data = [];

        // load core
        $data['core'] = $this->unify($this->corePath);

        // load modules
        foreach ($this->getMetadata()->getModules() as $name => $module) {
            $data[$name] = [];
            $module->loadTranslates($data[$name]);
        }

        // load custom
        if (!$this->noCustom) {
            $data['custom'] = $this->unify($this->customPath);
        }

        return $data;
    }

    /**
     * @param string       $scope
     * @param string       $category
     * @param string|array $name
     * @param mixed        $value
     *
     * @return void
     */
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

    /**
     * @param string       $scope
     * @param string       $category
     * @param string|array $name
     */
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

    public function reload(): array
    {
        $data = [];
        $languageList = $this->getMetadata()->get('multilang.languageList', []);
        $dbData = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select('*')
            ->from('translation')
            ->where('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($dbData as $record) {
            $record = Util::arrayKeysToCamelCase($record);
            foreach ($languageList as $locale) {
                $row = [];
                $field = Util::toCamelCase(strtolower($locale));
                if (isset($record[$field]) && $record[$field] !== null) {
                    $insideRow = [];

                    $hasDots = strpos($record['name'], '...') !== false;
                    $parts = explode('.', $record['name']);
                    if ($hasDots) {
                        array_pop($parts);
                        array_pop($parts);
                        array_pop($parts);
                        $parts[] = array_pop($parts) . '...';
                    }

                    $this->prepareTreeValue($parts, $insideRow, $record[$field]);
                    $row[$record['module']][$locale] = $insideRow;
                    $data = Util::merge($data, $row);
                }
            }
        }

        $this->getDataManager()->setCacheData('translations', $data);

        return $data;
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

    protected function init(): void
    {
        /** @var bool $installed */
        $installed = $this->getConfig()->get('isInstalled', false);

        $data = [];

        if ($installed) {
            $data = $this->getDataManager()->getCacheData('translations');
            if (empty($data)) {
                $data = $this->reload();
            }
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
        if (!$this->noCustom && !empty($data['custom'])) {
            $fullData = Util::merge($fullData, $data['custom']);
        }

        foreach ($fullData as $i18nName => $i18nData) {
            if ($i18nName != self::DEFAULT_LANGUAGE) {
                $i18nData = Util::merge($fullData[self::DEFAULT_LANGUAGE], $i18nData);
            }
            $this->data[$i18nName] = $i18nData;
        }

        if ($installed) {
            $this->data = $this->getEventManager()->dispatch('Language', 'modify', new Event(['data' => $this->data]))->getArgument('data');
        }
    }

    /**
     * Get data of Unifier language files
     *
     * @return array
     */
    protected function getData()
    {
        $currentLanguage = $this->getLanguage();
        if (!isset($this->data[$currentLanguage])) {
            $this->init();
        }

        if (!isset($this->data[$currentLanguage])) {
            $this->data[$currentLanguage] = $this->data['en_US'];
        }

        return $this->data[$currentLanguage];
    }

    private function unify(string $path): array
    {
        return $this->unifier->unify('i18n', $path, true);
    }

    /**
     * @param array $data
     * @param mixed $result
     * @param mixed $value
     */
    private function prepareTreeValue(array $data, &$result, $value): void
    {
        if (!empty($data)) {
            $first = array_shift($data);
            if (!empty($data)) {
                $this->prepareTreeValue($data, $result[$first], $value);
            } else {
                $result[$first] = $value;
            }
        }
    }

    private function getEntityManager(): \Espo\ORM\EntityManager
    {
        return $this->container->get('entityManager');
    }

    private function getMetadata(): \Espo\Core\Utils\Metadata
    {
        return $this->container->get('metadata');
    }

    private function getEventManager(): \Atro\Core\EventManager\Manager
    {
        return $this->container->get('eventManager');
    }

    private function getDataManager(): \Espo\Core\DataManager
    {
        return $this->container->get('dataManager');
    }

    private function getConfig(): \Espo\Core\Utils\Config
    {
        return $this->container->get('config');
    }
}

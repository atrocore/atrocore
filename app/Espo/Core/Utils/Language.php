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

use Espo\Core\Utils\File\Unifier;
use Espo\Core\Exceptions\Error;
use Espo\Entities\Preferences;
use Treo\Core\EventManager\Event;
use Treo\Core\EventManager\Manager as EventManager;

/**
 * Class Language
 */
class Language
{
    public const DEFAULT_LANGUAGE = 'en_US';

    /**
     * @var File\Manager
     */
    private $fileManager;

    /**
     * @var Metadata
     */
    private $metadata;

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
     * @var EventManager|null
     */
    private $eventManager;

    /**
     * @var bool
     */
    private $noCustom;

    /**
     * @var string
     */
    private $corePath = CORE_PATH . '/Espo/Resources/i18n';

    /**
     * @var string
     */
    private $customPath = 'custom/Espo/Custom/Resources/i18n';

    /**
     * @var array
     */
    private $moduleData = [];

    /**
     * Language constructor.
     *
     * @param string            $currentLanguage
     * @param File\Manager      $fileManager
     * @param Metadata          $metadata
     * @param EventManager|null $eventManager
     * @param bool              $noCustom
     */
    public function __construct(string $currentLanguage, File\Manager $fileManager, Metadata $metadata, EventManager $eventManager = null, bool $noCustom = false)
    {
        $this->currentLanguage = $currentLanguage;
        $this->fileManager = $fileManager;
        $this->metadata = $metadata;
        $this->eventManager = $eventManager;
        $this->noCustom = $noCustom;
        $this->unifier = new Unifier($this->fileManager, $this->metadata);
    }

    public static function detectLanguage(Config $config, Preferences $preferences = null): string
    {
        $language = self::DEFAULT_LANGUAGE;
        if ($preferences) {
            $language = $preferences->get('language');
        }
        if (!$language) {
            $language = $config->get('language');
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
            throw new Error('Language: current language [' . $this->getLanguage() . '] does not found');
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
        $path = $this->customPath;
        $currentLanguage = $this->getLanguage();

        $result = true;
        if (!empty($this->changedData)) {
            foreach ($this->changedData as $scope => $data) {
                if (!empty($data)) {
                    $result &= $this->fileManager->mergeContents(array($path, $currentLanguage, $scope . '.json'), $data, true);
                }
            }
        }

        if (!empty($this->deletedData)) {
            foreach ($this->deletedData as $scope => $unsetData) {
                if (!empty($unsetData)) {
                    $result &= $this->fileManager->unsetContents(array($path, $currentLanguage, $scope . '.json'), $unsetData, true);
                }
            }
        }

        $this->clearChanges();

        return (bool)$result;
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
        $this->clearChanges();

        return $this->moduleData;
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
        // load core
        $this->moduleData['core'] = $this->unify($this->corePath);
        $fullData = Util::merge([], $this->moduleData['core']);

        // load modules
        foreach ($this->metadata->getModules() as $name => $module) {
            $this->moduleData[$name] = [];
            $module->loadTranslates($this->moduleData[$name]);
            $fullData = Util::merge($fullData, $this->moduleData[$name]);
        }

        // load custom
        if (!$this->noCustom) {
            $this->moduleData['custom'] = $this->unify($this->customPath);
            $fullData = Util::merge($fullData, $this->moduleData['custom']);
        }

        foreach ($fullData as $i18nName => $i18nData) {
            if ($i18nName != self::DEFAULT_LANGUAGE) {
                $i18nData = Util::merge($fullData[self::DEFAULT_LANGUAGE], $i18nData);
            }
            $this->data[$i18nName] = $i18nData;
        }

        if (!is_null($this->eventManager)) {
            $this->data = $this->eventManager->dispatch('Language', 'modify', new Event(['data' => $this->data]))->getArgument('data');
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

        return $this->data[$currentLanguage];
    }

    private function unify(string $path): array
    {
        return $this->unifier->unify('i18n', $path, true);
    }
}

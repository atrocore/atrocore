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

namespace Treo\Core\Utils;

use Espo\Core\Exceptions\Error;
use Treo\Core\EventManager\Event;
use Treo\Core\EventManager\Manager as EventManager;

/**
 * Class Language
 */
class Language extends \Espo\Core\Utils\Language
{
    /**
     * @var Language|null
     */
    protected $eventManager = null;

    /**
     * @param EventManager $eventManager
     *
     * @return Language
     */
    public function setEventManager(EventManager $eventManager): Language
    {
        $this->eventManager = $eventManager;

        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function init($reload = false)
    {
        if ($reload || !file_exists($this->getLangCacheFile()) || !$this->useCache) {
            // load espo
            $fullData = $this->unify(CORE_PATH . '/Espo/Resources/i18n');

            // load treo
            $fullData = Util::merge($fullData, $this->unify(CORE_PATH . '/Treo/Resources/i18n'));

            // load modules
            foreach ($this->getMetadata()->getModules() as $module) {
                $module->loadTranslates($fullData);
            }

            // load custom
            if (!$this->noCustom) {
                $fullData = Util::merge($fullData, $this->unify('custom/Espo/Custom/Resources/i18n'));
            }

            $result = true;
            foreach ($fullData as $i18nName => $i18nData) {
                if ($i18nName != $this->defaultLanguage) {
                    $i18nData = Util::merge($fullData[$this->defaultLanguage], $i18nData);
                }

                $this->data[$i18nName] = $i18nData;

                if ($this->useCache) {
                    $i18nCacheFile = str_replace('{*}', $i18nName, $this->cacheFile);
                    $result &= $this->getFileManager()->putPhpContents($i18nCacheFile, $i18nData);
                }
            }

            if ($result == false) {
                throw new Error('Language::init() - Cannot save data to a cache');
            }
        }

        $currentLanguage = $this->getLanguage();
        if (empty($this->data[$currentLanguage])) {
            $this->data[$currentLanguage] = $this->getFileManager()->getPhpContents($this->getLangCacheFile());
        }

        if (!is_null($this->eventManager)) {
            $this->data = $this->eventManager->dispatch('Language', 'modify', new Event(['data' => $this->data]))->getArgument('data');
        }
    }

    /**
     * @param string $path
     *
     * @return array
     */
    private function unify(string $path): array
    {
        return $this->getUnifier()->unify('i18n', $path, true);
    }
}

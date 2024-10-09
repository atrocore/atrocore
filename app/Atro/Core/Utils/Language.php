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
use Espo\Core\EventManager\Event;
use Espo\Core\Utils\Util;

class Language extends \Espo\Core\Utils\Language
{
    public function __construct(Container $container)
    {
        $currentLanguage = self::detectLanguage($container->get('config'), $container->get('preferences'));

        parent::__construct($container, $currentLanguage);
    }

    public function clearCache(): void
    {
        $this->reload();
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
}

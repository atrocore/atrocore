<?php

declare(strict_types=1);

namespace Treo\Core\Utils;

use Espo\Core\Utils\Language as Base;
use Espo\Core\Exceptions\Error;
use Treo\Core\EventManager\Event;
use Treo\Core\EventManager\Manager as EventManager;

/**
 * Class Language
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Language extends Base
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
     * @inheritDoc
     */
    public function translate($label, $category = 'labels', $scope = 'Global', $requiredOptions = null)
    {
        return utf8_decode(parent::translate($label, $category, $scope, $requiredOptions));
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

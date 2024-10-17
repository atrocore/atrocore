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

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\Utils\Util;
use Atro\Core\Utils\Language;
use Espo\Core\DataManager;
use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity;

class Translation extends ReferenceData
{
    protected string $cacheFilePath = 'data/translations.json';

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('module') === 'custom' && !$entity->isNew() && !$entity->get('isCustomized')) {
            $entity->set('isCustomized', true);
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @inheritDoc
     */
    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->refreshTimestamp($options);
    }

    public function getPreparedTranslations(): array
    {
        if (!file_exists($this->cacheFilePath)) {
            $this->saveCacheFile($this->getAllItems());
        }

        return json_decode(file_get_contents($this->cacheFilePath), true);
    }

    protected function saveCacheFile(array $data): void
    {
        $preparedTranslationData = [];
        foreach ($data as $record) {
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
        file_put_contents($this->cacheFilePath, json_encode($preparedTranslationData));
    }

    public function refreshToDefault(): void
    {
        $records = self::getSimplifiedTranslates((new Language($this->getInjection('container')))->getModulesData());
        $this->saveDataToFile($records);
        $this->refreshTimestamp([]);
    }

    public static function getSimplifiedTranslates(array $data): array
    {
        $records = [];
        foreach ($data as $module => $moduleData) {
            foreach ($moduleData as $locale => $localeData) {
                $preparedLocaleData = [];
                self::toSimpleArray($localeData, $preparedLocaleData);
                foreach ($preparedLocaleData as $key => $value) {
                    $records[$key]['id'] = md5($key);
                    $records[$key]['code'] = $key;
                    $records[$key]['module'] = $module;
                    $records[$key]['isCustomized'] = $module === 'custom';
                    $records[$key]['createdAt'] = date('Y-m-d H:i:s');
                    $records[$key]['createdById'] = 'system';
                    $records[$key][Util::toCamelCase(strtolower($locale))] = $value;
                }
            }
        }

        return $records;
    }

    public static function toSimpleArray(array $data, array &$result, array &$parents = []): void
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

    protected function saveDataToFile(array $data): bool
    {
        $res = parent::saveDataToFile($data);

        if ($res) {
            $this->saveCacheFile($data);
        }

        return $res;
    }

    protected function prepareTreeValue(array $data, &$result, $value): void
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

    /**
     * @inheritDoc
     */
    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->refreshTimestamp($options);
    }

    protected function refreshTimestamp(array $options): void
    {
        if (!empty($options['keepCache'])) {
            return;
        }

        $this->getInjection('language')->clearCache();

        $this->getConfig()->set('cacheTimestamp', time());
        $this->getConfig()->save();
        DataManager::pushPublicData('dataTimestamp', time());
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
        $this->addDependency('language');
    }
}

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
                    $preparedTranslationData = Util::merge($preparedTranslationData, $row);
                }
            }
        }
        file_put_contents($this->cacheFilePath, json_encode($preparedTranslationData));
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

        $this->addDependency('language');
    }
}

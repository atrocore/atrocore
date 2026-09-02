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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity;

class Locale extends ReferenceData
{
    /**
     * The fields of this entity that the config exposes.
     */
    public static function getConfigData(): array
    {
        $path = self::DIR_PATH . '/Locale.json';

        if (!file_exists($path)) {
            return [];
        }

        $items = @json_decode(file_get_contents($path), true);

        if (!is_array($items)) {
            return [];
        }

        $res = [];
        foreach ($items as $key => $row) {
            $res[$key] = [
                'id' => $row['id'] ?? null,
                'code' => $row['code'] ?? null,
                'name' => $row['name'] ?? null,
                'languageCode' => $row['languageCode'] ?? null,
                'fallbackLanguageCode' => $row['fallbackLanguageCode'] ?? null,
                'weekStart' => $row['weekStart'] ?? null,
                'dateFormat' => $row['dateFormat'] ?? null,
                'timeFormat' => $row['timeFormat'] ?? null,
                'timeZone' => $row['timeZone'] ?? null,
                'thousandSeparator' => $row['thousandSeparator'] ?? null,
                'decimalMark' => $row['decimalMark'] ?? null,
                'displayLabelsInContentLanguage' => $row['displayLabelsInContentLanguage'] ?? null,
                'disableForUi' => $row['disableForUi'] ?? null,
            ];
        }

        return $res;
    }

    public function refreshCache(): void
    {
        $this->getInjection('dataManager')->clearCache(true);
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->isAttributeChanged('code') && !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $entity->get('code'))) {
            throw new BadRequest("Code is invalid.");
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $systemLocale = $this->getConfig()->get('locale');
        $systemLocales = $this->getConfig()->get('locales');

        if (empty($systemLocale) || !array_key_exists($systemLocale, $systemLocales)) {
            $systemLocale = array_key_first($systemLocales);
        }

        if (
            $this->getEntityManager()->getRepository('User')->where(['localeId' => $entity->get('id')])->findOne()
            || $systemLocale === $entity->get('id')
        ) {
            throw new BadRequest($this->getInjection('language')->translate('localeIsUsed', 'exceptions', 'Locale'));
        }

        parent::beforeRemove($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = []){
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('displayLabelsInContentLanguage')) {
            $this->refreshCache();
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->refreshCache();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('dataManager');
    }
}

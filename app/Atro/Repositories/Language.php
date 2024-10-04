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

use Atro\Core\Templates\Repositories\Base;
use Doctrine\DBAL\ParameterType;
use Espo\Core\DataManager;
use Espo\ORM\Entity;

class Language extends Base
{
    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->refreshCache($options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->refreshCache($options);
    }

    protected function refreshCache(array $options): void
    {
        if (!empty($options['keepCache'])) {
            return;
        }

        $records = $this->getConnection()->createQueryBuilder()
            ->select('id, code, content_usage')
            ->from('language')
            ->where('deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        $inputLanguageList = [];
        foreach ($records as $record) {
            if ($record['content_usage'] === 'main') {
                $this->getConfig()->set('mainLanguage', $record['code']);
            }
            if ($record['content_usage'] === 'additional') {
                $inputLanguageList[] = $record['code'];
            }
        }

        $toRebuild = $inputLanguageList !== $this->getConfig()->get('inputLanguageList');

        $this->getConfig()->set('isMultilangActive', !empty($inputLanguageList));
        $this->getConfig()->set('inputLanguageList', $inputLanguageList);
        $this->getConfig()->save();

        if ($toRebuild) {
            $this->getInjection('dataManager')->rebuild();
        }

        $this->getInjection('language')->clearCache();

        $this->getConfig()->set('cacheTimestamp', time());
        $this->getConfig()->save();
        DataManager::pushPublicData('dataTimestamp', time());
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('dataManager');
        $this->addDependency('language');
    }
}

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

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if(($entity->isNew() || $entity->isAttributeChanged('code')) && !empty($entity->get('code'))){
            $this->getEntityManager()
                ->getRepository('NotificationTemplate')
                ->addUiHandlerForLanguage($entity->get('code'));
        }

        $this->refreshCache();
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if (
            $this->getEntityManager()->getRepository('User')->where(['localeId' => $entity->get('id')])->findOne()
            || $this->getConfig()->get('localeId') === $entity->get('id')
        ) {
            throw new BadRequest($this->getInjection('language')->translate('localeIsUsed', 'exceptions', 'Locale'));
        }

        parent::beforeRemove($entity, $options);
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

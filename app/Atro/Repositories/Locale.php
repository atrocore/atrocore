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
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class Locale extends Base
{
    public function refreshCache(): void
    {
        $this->getInjection('dataManager')->clearCache();
        $this->getInjection('dataManager')->rebuild();
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->refreshCache();
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if (
            $this->getEntityManager()->getRepository('Preferences')->hasLocale((string)$entity->get('id'))
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

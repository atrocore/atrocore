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

use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Templates\Repositories\Base;
use Espo\Core\AclManager;
use Espo\ORM\Entity;

class TeamLanguage extends Base
{
    public function beforeSave(Entity $entity, array $options = [])
    {
        $exists = $this->where([
            'teamId'     => $entity->get('teamId'),
            'languageId' => $entity->get('languageId'),
        ])->findOne();

        if (!empty($exists) && $exists->get('id') !== $entity->get('id')) {
            $fieldName = $this->getLanguage()->translate('language', 'fields', 'TeamLanguage');
            $message = $this->getLanguage()->translate('notUniqueRecordField', 'exceptions');
            throw new NotUnique(sprintf($message, $fieldName));
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->getAclManager()->clearAclCache();
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->getAclManager()->clearAclCache();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function getAclManager(): AclManager
    {
        return $this->getInjection('container')->get('aclManager');
    }
}

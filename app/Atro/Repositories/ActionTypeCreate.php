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

use Espo\ORM\Entity;

/**
 * The records created for the found records of the search entity are configured via script only.
 */
class ActionTypeCreate extends AbstractActionType
{
    protected const array DISABLED_TARGET_ACTION_FLAGS = ['createDisabled'];

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if (!empty($entity->get('searchEntity')) && $entity->get('updateType') !== 'script') {
            $entity->set('updateType', 'script');
        }
    }
}

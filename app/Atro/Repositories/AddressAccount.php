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
use Atro\Core\Templates\Repositories\Relation;
use Espo\ORM\Entity;

class AddressAccount extends Relation
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isAttributeChanged('default') && $entity->get('default') && !empty($account = $entity->get('account'))) {
            $exist = $this
                ->where([
                    'default' => true,
                    'accountId' => $account->get('id'),
                    'id!=' => $entity->get('id')
                ])
                ->findOne();

            if ($exist) {
                throw new BadRequest($this->getLanguage()->translate('defaultAddressAlreadyExist', 'exceptions', 'Account'));
            }
        }

        parent::beforeSave($entity, $options);
    }
}

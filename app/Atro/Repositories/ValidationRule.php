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

use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class ValidationRule extends Base
{
    public function beforeSave(Entity $entity, array $options = array())
    {
        // set name
        $entity->set('name', $entity->get('type'));

        parent::beforeSave($entity, $options);
    }
}

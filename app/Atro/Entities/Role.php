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

namespace Atro\Entities;

class Role extends \Espo\Core\ORM\Entity
{
    public function getAclData(): \stdClass
    {
        return $this->getEntityManager()->getRepository('Role')->getAclData($this);
    }
}

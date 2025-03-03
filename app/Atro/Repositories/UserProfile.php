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

namespace Atro\Repositories;

use Espo\ORM\EntityFactory;
use Espo\ORM\EntityManager;

class UserProfile extends User
{
    public function __construct($entityType, EntityManager $entityManager, EntityFactory $entityFactory)
    {
        parent::__construct($entityType, $entityManager, $entityFactory);

        $this->entityType = 'User';
        $this->entityName = 'User';
    }
}

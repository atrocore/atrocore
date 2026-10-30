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

namespace Atro\Services;

use Espo\ORM\Entity;

class ConnectionTypeOauth1 extends ConnectionSubtype
{
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        // the callback identifies the connection record, it is the supertype record
        $connectionId = $this->getConnectionService()->encryptPassword((string)$entity->get('supertypeId'));
        $url = $this->getConfig()->get('siteUrl') . '?entryPoint=oauth1Callback&connectionId=' . $connectionId;

        $entity->set('callbackUrl', $url . '&type=callback');
        $entity->set('linkUrl', $url . '&type=link');
    }
}

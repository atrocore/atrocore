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

use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class Sharing extends Base
{
    protected $mandatorySelectAttributeList = ['active', 'validTill', 'allowedUsage', 'used'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('link', $this->getConfig()->get('siteUrl') . '/?entryPoint=sharing&id=' . $entity->get('id'));

        $availableViaValidTill = empty($entity->get('validTill')) || $entity->get('validTill') >= (new \DateTime())->format('Y-m-d H:i:s');
        $availableViaAllowedUsage = empty($entity->get('allowedUsage')) || (int)$entity->get('used') < $entity->get('allowedUsage');

        $entity->set('available', !empty($entity->get('active')) && $availableViaValidTill && $availableViaAllowedUsage);
    }
}

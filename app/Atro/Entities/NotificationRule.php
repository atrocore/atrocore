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

namespace Atro\Entities;

use Atro\Core\Templates\Entities\Base;
use Espo\Core\ORM\Entity;

class NotificationRule extends Base
{
    protected $entityType = "NotificationRule";

    public function isTransportActive(string $transportType): bool
    {
        return !empty($this->get($transportType . 'Active'));
    }

    public function hasTransportTemplate($transportType): bool
    {
        return !empty($this->get($transportType . 'TemplateId'));
    }

    public function getTransportTemplate($transportType): ?NotificationTemplate
    {
        return $this->getEntityManager()->getEntity('NotificationTemplate', $this->get($transportType . 'TemplateId'));
    }
}

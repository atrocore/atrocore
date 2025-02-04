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
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\IEntity;

class Layout extends Base
{
    protected $mandatorySelectAttributeList = ["layoutProfileId", "layoutProfileName"];
}

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

class Attribute extends Base
{
    protected $entityType = "Attribute";

    public function getLinkMultipleLinkName(): string
    {
        return self::buildLinkMultipleLinkName($this->get('id'), $this->get('entityType'));
    }

    public static function buildLinkMultipleLinkName(string $id, string $entityType): string
    {
        return $id . '_' . lcfirst($entityType);
    }
}

<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Services;

use Atro\Core\Templates\Services\Hierarchy;

class AssetCategory extends Hierarchy
{
    public function createEntity($attachment)
    {
        if (!property_exists($attachment, 'assetsIds')) {
            $attachment->assetsIds = [];
        }
        if (!property_exists($attachment, 'assetsNames')) {
            $attachment->assetsNames = null;
        }

        return parent::createEntity($attachment);
    }
}

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

use Espo\ORM\IEntity;
use Atro\Core\Templates\Entities\Base;

class ClassificationAttribute extends Base
{
    protected $entityType = "ClassificationAttribute";

    public function getAttributeType($attribute)
    {
        if ($attribute === 'textValue' && $this->has('attributeId')) {
            if (!empty($attr = $this->get('attribute'))) {
                if (in_array($attr->get('type'), ['array', 'extensibleMultiEnum'])) {
                    return IEntity::JSON_ARRAY;
                }
            }
        }

        return parent::getAttributeType($attribute);
    }
}

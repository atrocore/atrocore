<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\TwigFilter;

use Atro\Core\Twig\AbstractTwigFilter;
use Espo\Core\ORM\Entity;

class EditableEntity extends AbstractTwigFilter
{
    public function __construct()
    {
        $this->addDependency('acl');
    }

    public function filter($value)
    {
        if (!$value instanceof Entity) {
            return null;
        }

        if (!$this->getInjection('acl')->check($value->getEntityType(), 'edit')) {
            return null;
        }

        return "data-editor-type={$value->getEntityType()} data-editor-id={$value->id}";
    }
}
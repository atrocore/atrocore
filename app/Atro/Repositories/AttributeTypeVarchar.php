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

namespace Atro\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Subtype;
use Atro\Core\Utils\RegexUtil;
use Espo\ORM\Entity;

/**
 * The pattern of a string attribute has to be a valid regular expression.
 */
class AttributeTypeVarchar extends Subtype
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (!empty($entity->get('pattern')) && ($entity->isNew() || $entity->isAttributeChanged('pattern'))) {
            if (!RegexUtil::validate($entity->get('pattern'))) {
                throw new BadRequest(
                    sprintf($this->getLanguage()->translate('regexSyntaxError', 'exceptions', 'FieldManager'), 'pattern')
                );
            }
        }

        parent::beforeSave($entity, $options);
    }
}

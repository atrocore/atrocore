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
use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity;

class EmailTemplate extends ReferenceData
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        $systemTemplates = $this->getMetadata()->get(['app', 'systemEmailTemplates'], []);
        if (!$entity->isNew() && $entity->isAttributeChanged('code') && in_array($entity->getFetched('code'), $systemTemplates)) {
            throw new BadRequest($this->getInjection('language')->translate("systemTemplateCodeChanged", "exceptions", 'EmailTemplate'));
        }

        parent::beforeSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $systemTemplates = $this->getMetadata()->get(['app', 'systemEmailTemplates'], []);
        if (in_array($entity->get('code'), $systemTemplates)) {
            throw new BadRequest($this->getInjection('language')->translate("systemTemplatesCannotBeDeleted", "exceptions", 'EmailTemplate'));
        }

        parent::beforeRemove($entity, $options);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}

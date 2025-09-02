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
use Atro\Core\FileValidation\Items\Ratio;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class FileType extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('priority') === null) {
            $entity->set('priority', 0);
        }

        if (!empty($entity->get('aspectRatio')) && (!preg_match('/^[0-9]+:[0-9]+$/', $entity->get('aspectRatio')) ||
                empty(Ratio::aspectRatioToFloat($entity->get('aspectRatio'))))) {
            throw new BadRequest($this->getLanguage()->translate('aspectRatioMustBeInFormat', 'exceptions', 'FileType'));
        }

        parent::beforeSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        foreach ($this->getMetadata()->get('entityDefs') as $entityName => $entityDefs) {
            if (!empty($entityDefs['fields'])) {
                foreach ($entityDefs['fields'] as $fieldName => $fieldDefs) {
                    if (!empty($fieldDefs['fileTypeId']) && $fieldDefs['fileTypeId'] === $entity->get('id')) {
                        throw new BadRequest(
                            sprintf(
                                $this->getLanguage()->translate('fileTypeCannotBeDeleted', 'exceptions', 'FileType'),
                                $this->getLanguage()->translate($fieldName, 'fields', $entityName),
                                $this->getLanguage()->translate($entityName, 'scopeNames')
                            )
                        );
                    }
                }
            }
        }

        parent::beforeRemove($entity, $options);
    }
}

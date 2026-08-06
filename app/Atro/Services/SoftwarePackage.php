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

use Atro\Core\Templates\Services\ReferenceData;
use Espo\ORM\Entity;

class SoftwarePackage extends ReferenceData
{
    public function putAclMeta(Entity $entity): void
    {
        parent::putAclMeta($entity);

        if (empty($entity->get('currentVersion'))) {
            $entity->setMetaPermission('edit', false);
        }
        $entity->setMetaPermission('delete', false);

        $entity->setMetaPermission('install', !$entity->get('installed'));
        $entity->setMetaPermission(
            'uninstall', $entity->get('installed') && $entity->get('id') !== 'Atro' && !empty($entity->get('currentVersion')) && !empty($entity->get('targetVersion'))
        );
    }

    public function install(array $ids): bool
    {
        return true;
    }

    public function uninstall(array $ids): bool
    {
        return true;
    }
}

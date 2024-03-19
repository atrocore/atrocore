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

use Atro\Core\Templates\Services\Hierarchy;

class Folder extends Hierarchy
{
    public function getDefaultStorage(string $folderId): ?array
    {
        if (!empty($folderId)) {
            $parents = $this->getRepository()->getParentsRecursivelyArray($folderId);
            $fId = $folderId;
            while (true) {
                $folderStorages = $this->getEntityManager()->getRepository('FolderStorage')
                    ->where(['folderId' => $fId])
                    ->find();

                if (!empty($folderStorages[0])) {
                    break;
                }

                if (!empty($parents)) {
                    $fId = array_shift($parents);
                } else {
                    break;
                }
            }
        }

        if (!empty($folderStorages[0])) {
            $storage = $this->getEntityManager()->getRepository('Storage')
                ->where(['id' => array_column($folderStorages->toArray(), 'storageId')])
                ->order('priority', 'DESC')
                ->findOne();
        } else {
            $storage = $this->getEntityManager()->getRepository('Storage')
                ->order('priority', 'DESC')
                ->findOne();
        }

        return empty($storage) ? null : ['id' => $storage->get('id'), 'name' => $storage->get('name')];
    }
}

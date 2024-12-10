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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Xattr;
use Espo\ORM\Entity;

class Storage extends Base
{
    public function createScanJob(string $storageId, bool $manual): bool
    {
        $storage = $this->getEntity($storageId);
        if (empty($storage)) {
            throw new NotFound();
        }

        if (empty($storage->get('isActive'))) {
            throw new BadRequest('The Storage is not active.');
        }

        if ($storage->get('type') === 'local') {
            $xattr = new Xattr();
            if (!$xattr->hasServerExtensions()) {
                throw new BadRequest("Xattr extension is not installed and the attr command is not available. See documentation for details.");
            }
        }

        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'    => $this->getLanguage()->translate('scan', 'labels', 'Storage') . ' ' . $storage->get('name'),
            'type'    => 'ScanStorage',
            'payload' => [
                'storageId'   => $storage->get('id'),
                'storageName' => $storage->get('name'),
                'manual'      => $manual
            ]
        ]);
        $this->getEntityManager()->saveEntity($jobEntity);

        return true;
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }

    protected function getLanguage(): Language
    {
        return $this->getInjection('language');
    }
}

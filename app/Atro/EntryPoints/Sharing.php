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

namespace Atro\EntryPoints;

use Atro\Core\Exceptions\NotFound;
use Atro\Entities\File;

class Sharing extends AbstractEntryPoint
{
    public static bool $authRequired = false;

    public function run()
    {
        if (empty($_GET['id'])) {
            throw new NotFound();
        }

        $sharing = $this->getEntityManager()->getRepository('Sharing')->get($_GET['id']);
        if (empty($sharing)) {
            throw new NotFound();
        }

        if (empty($sharing->get('active'))) {
            throw new NotFound();
        }

        if (!empty($sharing->get('validTill')) && $sharing->get('validTill') < (new \DateTime())->format('Y-m-d H:i:s')) {
            throw new NotFound();
        }

        if (!empty($sharing->get('allowedUsage'))) {
            $used = (int)$sharing->get('used');
            if ($used >= $sharing->get('allowedUsage')) {
                throw new NotFound();
            }
            $sharing->set('used', $used + 1);
            $this->getEntityManager()->saveEntity($sharing);
        }

        $entity = $this->getEntityManager()->getRepository($sharing->get('entityType'))->get($sharing->get('entityId'));
        if (empty($entity)) {
            throw new NotFound();
        }

        switch ($sharing->get('type')) {
            case 'download':
                if ($entity instanceof File) {
                    header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
                    header("Cache-Control: public");
                    header('Content-Type: ' . $entity->get('mimeType'));
                    header("Content-Transfer-Encoding: Binary");
                    header('Content-Length: ' . $entity->get('fileSize'));
                    header("Content-Disposition: attachment; filename={$entity->get('name')}");
                    readfile($entity->getFilePath());
                    exit;
                }
                break;
        }

        throw new NotFound();
    }
}

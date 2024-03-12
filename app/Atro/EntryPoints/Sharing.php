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

namespace Atro\EntryPoints;

use Atro\Core\Exceptions\NotFound;

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
                if ($entity->getEntityType() === 'Asset') {
                    $attachment = $entity->get('file');
                    $fileName = $this->getEntityManager()->getRepository('Attachment')->getFilePath($attachment);
                    if (!file_exists($fileName)) {
                        throw new NotFound();
                    }

                    header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
                    header("Cache-Control: public");
                    header('Content-Type: ' . $attachment->get('type'));
                    header("Content-Transfer-Encoding: Binary");
                    header('Content-Length: ' . filesize($fileName));
                    header("Content-Disposition: attachment; filename={$attachment->get('name')}");
                    readfile($fileName);
                    exit;
                }
                break;
        }

        throw new NotFound();
    }
}

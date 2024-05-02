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
use Psr\Http\Message\StreamInterface;

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

        /** @var File $file */
        $file = $sharing->get('file');
        if (empty($file)) {
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

        /** @var StreamInterface $stream */
        $stream = $this->getEntityManager()->getRepository('File')->getStorage($file)->getStream($file);

        header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
        header("Cache-Control: public");
        header('Content-Type: application/octet-stream');
        header("Content-Length: {$file->get('fileSize')}");
        header("Content-Disposition: attachment; filename={$file->get('name')}");

        $stream->rewind();
        while (!$stream->eof()) {
            echo $stream->read(4096);
        }
        $stream->close();
        exit;
    }
}

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

use Atro\Core\Download\Custom;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Entities\File;
use Psr\Http\Message\StreamInterface;

class Download extends AbstractEntryPoint
{
    public static bool $authRequired = true;

    public function run()
    {
        $id = $_GET['id'] ?? null;
        if (empty($id)) {
            throw new BadRequest();
        }

        /** @var File $file */
        $file = $this->getEntityManager()->getEntity('File', $id);
        if (!$file) {
            throw new NotFound();
        }

        if (!$this->getAcl()->checkEntity($file)) {
            throw new Forbidden();
        }

        $type = $_GET['type'] ?? null;

        if ($type === 'custom') {
            $path = $this->container->get(Custom::class)->convert($file, $_GET);
            header("Location: $path", true, 302);
            exit;
        }

        $this->downloadByFileStream($file);
    }

    protected function downloadByFileStream(File $file): void
    {
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

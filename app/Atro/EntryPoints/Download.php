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
            $this->sendConvertedImage($file, $_GET);
        }

        $this->downloadByFileStream($file);
    }

    /**
     * Converts the image on every request; the browser keeps it and asks the server again, the server answers
     * "not modified" without converting while the file and the parameters are the same.
     */
    protected function sendConvertedImage(File $file, array $params): never
    {
        $key = [(string)$file->get('hash'), (string)$file->get('fileMtime')];
        foreach (['width', 'height', 'quality', 'scale', 'format'] as $name) {
            $key[] = is_scalar($params[$name] ?? null) ? (string)$params[$name] : '';
        }
        $etag = '"' . substr(md5(implode('|', $key)), 0, 12) . '"';

        header('Cache-Control: private, no-cache');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');

        if ($this->isNotModified($etag)) {
            http_response_code(304);
            exit;
        }

        $result = $this->container->get(Custom::class)->convertToBlob($file, $params);

        header('Content-Type: ' . $result['mimeType']);
        header('Content-Length: ' . strlen($result['content']));
        header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($result['name']));

        echo $result['content'];
        exit;
    }

    protected function downloadByFileStream(File $file): void
    {
        /** @var StreamInterface $stream */
        $stream = $this->getEntityManager()->getRepository('File')->getStorage($file)->getStream($file);

        $fileSize = $file->get('fileSize');
        if (is_null($fileSize)) {
            $fileSize = @filesize($file->getFilePath());
        }

        if (ob_get_level()) {
            ob_end_clean();
        }

        header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
        header("Cache-Control: private");
        header('Content-Type: application/octet-stream');
        header("Content-Length: {$fileSize}");
        header("Content-Disposition: attachment; filename=\"{$file->get('name')}\"");

        set_time_limit(0);

        $stream->rewind();
        while (!$stream->eof()) {
            echo $stream->read(4096);
            flush(); // Force output to browser
            if (connection_aborted()) {
                break;
            }
        }
        $stream->close();
        exit;
    }
}

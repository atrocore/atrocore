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

namespace Atro\EntryPoints;

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Utils\Thumbnail as ThumbnailCreator;
use Atro\Entities\File;

/**
 * Serves the thumbnail of a file to a user who has access to the file. The thumbnail is created on the first request.
 */
class Thumbnail extends AbstractEntryPoint
{
    private const int MAX_AGE = 31536000;

    private const string PLACEHOLDER
        = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#f0f0f0"/>'
        . '<path d="M22 14h14l10 10v26H22z" fill="none" stroke="#b0b0b0" stroke-width="2"/></svg>';

    public function run()
    {
        $id = (string)($_GET['id'] ?? '');
        $size = (string)($_GET['size'] ?? '');

        // errors are never cached, the headers of a successful response replace it
        header('Cache-Control: no-store');

        if (preg_match('/^[a-zA-Z0-9_-]+$/', $id) !== 1) {
            throw new NotFound();
        }

        /** @var File $file */
        $file = $this->getEntityManager()->getEntity('File', $id);

        // a file the user has no access to is reported the same way as a missing one
        if (empty($file) || !$this->getAcl()->checkEntity($file)) {
            throw new NotFound();
        }

        $tc = $this->getThumbnailCreator();
        if (!$tc->isSupported($file) || !$tc->isSizeValid($size)) {
            throw new NotFound();
        }

        $version = $tc->getVersion($file, $size);
        $etag = '"' . $version . '"';

        header('X-Content-Type-Options: nosniff');
        header('ETag: ' . $etag);
        if (($_GET['cache'] ?? null) === $version) {
            header('Cache-Control: private, max-age=' . self::MAX_AGE . ', immutable');
        } else {
            header('Cache-Control: private, no-cache');
        }

        if ($this->isNotModified($etag)) {
            http_response_code(304);
            exit;
        }

        $path = $tc->generate($file, $size);
        if ($path === null) {
            header('Cache-Control: no-store');
            header_remove('ETag');
            if (!$tc->isPending($file, $size)) {
                throw new NotFound();
            }
            $this->output('image/svg+xml', self::PLACEHOLDER);
        }

        $this->sendFile($path);
    }

    protected function sendFile(string $path): never
    {
        $mimeType = $this->getThumbnailCreator()->getMimeType($path);

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline');
        if ($mimeType === 'image/svg+xml') {
            header('Content-Security-Policy: sandbox');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            readfile($path);
        }
        exit;
    }

    private function output(string $mimeType, string $contents): never
    {
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . strlen($contents));
        header('Content-Security-Policy: sandbox');

        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            echo $contents;
        }
        exit;
    }

    protected function getThumbnailCreator(): ThumbnailCreator
    {
        return $this->container->get(ThumbnailCreator::class);
    }
}

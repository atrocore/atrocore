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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Entities\File;

class Sharing extends Download
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
            throw new Forbidden();
        }

        if (!empty($sharing->get('allowedUsage'))) {
            $used = (int)$sharing->get('used');
            if ($used >= $sharing->get('allowedUsage')) {
                throw new Forbidden();
            }
            $sharing->set('used', $used + 1);
            $this->getEntityManager()->saveEntity($sharing);
        }

        $type = $_GET['type'] ?? null;
        if ($type === 'custom') {
            $path = $this->container->get(Custom::class)->convert($file, $_GET);
            header("Location: $path", true, 302);
            exit;
        }

        if (!empty($_GET['view']) && $this->isEnabledFilePreview($file)) {
            header('Content-Type: ' . $file->get('mimeType'));
            echo $file->getContents();
            exit;
        }

        $this->downloadByFileStream($file);
    }

    protected function isEnabledFilePreview(File $file): bool
    {
        return !empty($_GET['view']) && in_array($file->get('mimeType'), $this->getMetadata()->get(['app', 'typesWithThumbnails'], []));
    }
}

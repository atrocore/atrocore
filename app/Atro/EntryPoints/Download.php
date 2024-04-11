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
        } else {
            $path = $file->getDownloadUrl();
        }

        header("Location: $path", true, 302);
        exit;
    }
}

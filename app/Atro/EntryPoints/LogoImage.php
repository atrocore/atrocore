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

use Atro\Entities\File;
use Atro\Core\Exceptions\NotFound;

class LogoImage extends Image
{
    public static bool $authRequired = false;

    public function run()
    {
        $id = !empty($_GET['id']) ? $_GET['id'] : $this->getConfig()->get('companyLogoId');
        if (empty($id)) {
            throw new NotFound();
        }

        /** @var File $file */
        $file = $this->getEntityManager()->getEntity("File", $_GET['id']);
        if (empty($file)) {
            throw new NotFound();
        }

        $this->show($file, $_GET['size'] ?? null);
    }

    protected function getImageSize(string $size): ?array
    {
        if ($size == 'small-logo') {
            return [181, 44];
        }

        return parent::getImageSize($size);
    }
}


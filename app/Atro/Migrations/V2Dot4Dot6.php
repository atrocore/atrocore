<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V2Dot4Dot6 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-28 12:00:00');
    }

    public function up(): void
    {
        // public/upload is not used since 3.0.14, its .htaccess is not shipped anymore
        if (!file_exists('vendor/atrocore/core/copy/public/upload/.htaccess')) {
            return;
        }

        if (!is_dir('public/upload')) {
            mkdir('public/upload', 0755, true);
        }
        copy('vendor/atrocore/core/copy/public/upload/.htaccess', 'public/upload/.htaccess');
    }
}

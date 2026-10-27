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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V2Dot7Dot3 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-27 12:00:00');
    }

    public function up(): void
    {
        // openapi schema has moved to /apidocs/openapi.json and is protected by own basic auth
        if (!is_dir('public/apidocs')) {
            mkdir('public/apidocs', 0755, true);
        }
        copy('vendor/atrocore/core/copy/public/apidocs/index.html', 'public/apidocs/index.html');
        copy('vendor/atrocore/core/copy/public/.htaccess', 'public/.htaccess');
    }
}

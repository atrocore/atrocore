<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot3Dot12 extends Base
{
    public function up(): void
    {
        if (file_exists('apidocs/index.html')) {
            unlink('apidocs/index.html');
        }
        copy('vendor/atrocore/core/copy/apidocs/index.html', 'apidocs/index.html');

        try {
            $this->getPDO()->exec("DELETE FROM scheduled_job WHERE job='RestApiDocs'");
            $this->getPDO()->exec("DELETE FROM job WHERE name='Generate REST API docs'");
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
    }
}

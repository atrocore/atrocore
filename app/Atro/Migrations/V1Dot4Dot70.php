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

class V1Dot4Dot70 extends Base
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `pseudo_transaction_job` ADD md5 VARCHAR(255) DEFAULT NULL UNIQUE COLLATE utf8mb4_unicode_ci");
        $this->execute("CREATE UNIQUE INDEX UNIQ_9AEE3C08E86CEBE1EB3B4E33 ON `pseudo_transaction_job` (md5, deleted)");
        $this->execute("DROP INDEX md5 ON `pseudo_transaction_job`");
    }

    public function down(): void
    {
        $this->execute("DROP INDEX UNIQ_9AEE3C08E86CEBE1EB3B4E33 ON `pseudo_transaction_job`");
        $this->execute("ALTER TABLE `pseudo_transaction_job` DROP md5");
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}

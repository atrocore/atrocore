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
use Atro\Core\Utils\Util;

class V2Dot1Dot25 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-11-07 13:00:00');
    }

    public function up(): void
    {
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');

        foreach ($metadata->get('scopes') ?? [] as $scope => $defs) {
            if (empty($defs['type'])) {
                continue;
            }

            if ($defs['type'] === 'Hierarchy') {
                $tableName = Util::toUnderScore(lcfirst($scope.'Hierarchy'));
                if ($this->isPgSQL()) {
                    $this->exec("ALTER TABLE $tableName ADD route TEXT DEFAULT NULL");
                } else {
                    $this->exec("ALTER TABLE $tableName ADD route LONGTEXT DEFAULT NULL");
                }
            }
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}

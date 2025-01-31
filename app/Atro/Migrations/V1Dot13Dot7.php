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

class V1Dot13Dot7 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-01-31 09:00:00');
    }

    public function up(): void
    {
        if($this->isPgSQL()) {
            $this->exec('ALTER TABLE layout_profile ADD data TEXT DEFAULT NULL;');
            $this->exec("COMMENT ON COLUMN layout_profile.data IS '(DC2Type:jsonObject)';");
        }else{
            $this->exec("ALTER TABLE layout_profile ADD data LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', ADD parent_id VARCHAR(36) DEFAULT NULL;");
        }

        // migrate menu
        $this->getConnection()->createQueryBuilder()
            ->update('layout_profile')
            ->set('data', ':data')
            ->setParameter('data', json_encode([
                "field" => [
                    "navigation" => $this->getConfig()->get('twoLevelTabList') ?? $this->getConfig()->get('tabList'),
                    "quickCreateList" => $this->getConfig()->get('quickCreateList') ?? [],
                    "dashboardLayout" => $this->getConfig()->get('dashboardLayout')
                ]
            ]))
        ->executeStatement();

    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}

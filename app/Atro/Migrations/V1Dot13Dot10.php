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

class V1Dot13Dot10 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-02-04 10:00:00');
    }

    public function up(): void
    {
        if($this->isPgSQL()) {
            $this->exec('ALTER TABLE layout_profile ADD navigation TEXT DEFAULT NULL');
            $this->exec('ALTER TABLE layout_profile ADD dashboard_layout TEXT DEFAULT NULL');
            $this->exec('ALTER TABLE layout_profile ADD dashlets_options TEXT DEFAULT NULL');
            $this->exec("COMMENT ON COLUMN layout_profile.navigation IS '(DC2Type:jsonObject)'");
            $this->exec("COMMENT ON COLUMN layout_profile.dashboard_layout IS '(DC2Type:jsonObject)'");
            $this->exec("COMMENT ON COLUMN layout_profile.dashlets_options IS '(DC2Type:jsonObject)'");
        }else{
            $this->exec("COMMENT ON COLUMN layout_profile.dashlets_options IS '(DC2Type:jsonObject)'");
            $this->exec("ALTER TABLE layout_profile ADD navigation LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)'");
            $this->exec("ALTER TABLE layout_profile ADD dashboard_layout LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)'");
            $this->exec("ALTER TABLE layout_profile ADD dashlets_options LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)'");
        }

        // migrate menu
        $this->getConnection()->createQueryBuilder()
            ->update('layout_profile')
            ->set('navigation', ':navigation')
            ->set('dashboard_layout', ':dashboardLayout')
            ->set('dashlets_options', ':dashletsOptions')
            ->setParameter("navigation", json_encode($this->getConfig()->get('twoLevelTabList') ?? $this->getConfig()->get('tabList') ?? []))
            ->setParameter("dashboardLayout", json_encode($this->getConfig()->get('dashboardLayout') ?? []))
            ->setParameter('dashletsOptions', json_encode($this->getConfig()->get('dashletsOptions') ?? []))
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

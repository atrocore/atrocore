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

class V1Dot13Dot16 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-02-06 14:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->getPDO()->exec("ALTER TABLE layout_profile ADD favorites_list TEXT DEFAULT NULL");
            $this->getPDO()->exec("COMMENT ON COLUMN layout_profile.favorites_list IS '(DC2Type:jsonArray)'");
        } else {
            $this->getPDO()->exec("ALTER TABLE layout_profile ADD COLUMN favorites_list TEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)'");
        }

        $favList = ['Product', 'File'];

        if (class_exists('\Pim\Module')) {
            $favList = array_merge(['Classification'], $favList);
        }

        if (class_exists('\Import\Module')) {
            $favList[] = 'ImportFeed';
        }

        if (class_exists('\Export\Module')) {
            $favList[] = 'ExportFeed';
        }

        $this->getConnection()->createQueryBuilder()
            ->update('layout_profile')
            ->set('favorites_list', ':favorites_list')
            ->setParameter('favorites_list', json_encode($favList))
            ->executeStatement();
    }
}
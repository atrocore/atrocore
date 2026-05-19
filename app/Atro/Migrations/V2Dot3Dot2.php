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

class V2Dot3Dot2 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-05-15 10:00:00');
    }

    public function up(): void
    {
        foreach (['equal' => 'fieldEqual', 'similar' => 'fieldSimilar', 'contains' => 'fieldContains'] as $old => $new) {
            $this->getDbal()->createQueryBuilder()
                ->update('matching_rule')
                ->set('type', ':new')
                ->where('type = :old')
                ->setParameter('new', $new)
                ->setParameter('old', $old)
                ->executeQuery();
        }

        $this->exec("ALTER TABLE matching_rule ADD attribute_id VARCHAR(36) DEFAULT NULL");
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}

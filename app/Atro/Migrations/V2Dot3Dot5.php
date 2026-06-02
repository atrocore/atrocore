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

class V2Dot3Dot5 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-06-02 18:00:00');
    }

    public function up(): void
    {
        $this->getDbal()->createQueryBuilder()
            ->update($this->getDbal()->quoteIdentifier('attribute'))
            ->set('code', 'id')
            ->where('code IS NULL')
            ->executeQuery();
    }
}

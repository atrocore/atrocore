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

class V2Dot1Dot24 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-10-31 13:00:00');
    }

    public function up(): void
    {
        $extensions = $this->getConnection()->createQueryBuilder()->select('extensions')
            ->from('file_type')
            ->where('id= :id')
            ->setParameter('id', 'a_image')
            ->fetchOne();

        if (!empty($extensions)) {
            $extensions = @json_decode($extensions, true);
            if (!empty($extensions) && !in_array('avif', $extensions)) {
                $extensions[] = 'avif';

                $this->getConnection()->createQueryBuilder()
                    ->update('file_type')
                    ->set('extensions', ':extensions')
                    ->where('id= :id')
                    ->setParameter('id', 'a_image')
                    ->setParameter('extensions', json_encode($extensions))
                    ->executeStatement();
            }
        }
    }
}

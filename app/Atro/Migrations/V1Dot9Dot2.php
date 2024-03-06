<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V1Dot9Dot2 extends Base
{
    public function up(): void
    {
        $this->getConnection()
           ->createQueryBuilder()
           ->update('extensible_enum_option')
           ->set('code', ':null')
           ->where('code LIKE :code')
           ->setParameter('code', 'null-%', ParameterType::STRING)
           ->setParameter('null', null, ParameterType::NULL)
           ->executeStatement();
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited!');
    }
}

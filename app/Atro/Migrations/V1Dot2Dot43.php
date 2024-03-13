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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

/**
 * Migration for version 1.2.43
 */
class V1Dot2Dot43 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->getConfig()->set('languageList', ['en_US', 'de_DE', 'ru_RU', 'es_ES']);
        $this->getConfig()->save();
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $this->getConfig()->set('languageList', ['en_US', 'de_DE', 'ru_RU']);
        $this->getConfig()->save();
    }
}

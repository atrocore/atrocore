<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Repositories;

use Espo\Core\ORM\Repositories\RDB;

class ActionHistoryRecord extends RDB
{
    protected $processFieldsAfterSaveDisabled = true;
    protected $processFieldsBeforeSaveDisabled = true;
    protected $processFieldsAfterRemoveDisabled = true;
    
    protected bool $cacheable = false;
}

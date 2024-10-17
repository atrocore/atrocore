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

declare(strict_types=1);

namespace Atro\Controllers;

use Atro\Console\AbstractConsole;
use Atro\Core\Templates\Controllers\ReferenceData;

class Translation extends ReferenceData
{
    public function postActionReset(): bool
    {
        exec(AbstractConsole::getPhpBinPath($this->getConfig()) . " index.php refresh translations >/dev/null");

        return true;
    }
}

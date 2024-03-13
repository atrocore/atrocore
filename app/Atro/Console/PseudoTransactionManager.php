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

declare(strict_types=1);

namespace Atro\Console;

use Atro\Core\Application;

class PseudoTransactionManager extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Run pseudo transaction jobs.';
    }

    public function run(array $data): void
    {
        if (empty($this->getConfig()->get('isInstalled')) || Application::isSystemUpdating()) {
            exit(1);
        }

        $this->getContainer()->get('pseudoTransactionManager')->run();

        self::show('Pseudo transaction jobs run successfully', self::SUCCESS, true);
    }
}

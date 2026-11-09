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

namespace Atro\Core\ModuleManager;

use Atro\Core\Utils\Config;
use Doctrine\DBAL\Connection;

/**
 * A step that is run once, after the module is removed from the system.
 *
 * A step is a class in the `AfterDelete` folder of the module (`{Module}\AfterDelete\*`), it is found without
 * any registration. When the step is run, the code of the module does not exist anymore: the folder is copied
 * to `data/module-manager-events/{Module}/AfterDelete/` on every update and the step is run from the copy.
 * That is why the step gets the database connection and the config only and must not use any class of its
 * module. The entities of the module are not in the metadata anymore, so their data is changed via the
 * connection.
 */
abstract class AbstractAfterDelete
{
    final public function __construct(
        protected readonly Connection $connection,
        protected readonly Config $config
    ) {
    }

    /**
     * The order of the step among the steps of its module, the lower one runs first.
     */
    abstract public static function getSortOrder(): int;

    abstract public function run(): void;
}

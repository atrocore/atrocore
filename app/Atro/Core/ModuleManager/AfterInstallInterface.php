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

/**
 * A step that is run once, when the module is added to the system. For the core it is run when the system is
 * installed.
 *
 * A step is a class in the `AfterInstall` folder of the module (`{Module}\AfterInstall\*`), it is found without
 * any registration. The steps are run when the system is complete: the database schema, the metadata and the
 * services are available. A step is obtained from the container, so its dependencies are injected via the
 * constructor.
 */
interface AfterInstallInterface
{
    /**
     * The order of the step among the steps of its module, the lower one runs first.
     */
    public static function getSortOrder(): int;

    public function run(): void;
}

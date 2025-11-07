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

namespace Atro\Console;

class RebuildHierarchyRoutes extends RebuildHierarchyRoutesForEntity
{
    public static function getDescription(): string
    {
        return 'Rebuild routes for hierarchy entities.';
    }

    public function run(array $data): void
    {
        foreach ($this->getMetadata()->get("scopes") ?? [] as $scope => $defs) {
            if (!empty($defs['type']) && $defs['type'] === 'Hierarchy') {
                if ($this->rebuild($scope)) {
                    self::show("Routes has been built successfully for the '$scope'.", self::SUCCESS);
                }
            }
        }
    }
}

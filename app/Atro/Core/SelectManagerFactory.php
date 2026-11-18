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

namespace Atro\Core;

use Atro\Core\SelectManagers\Base;
use Atro\Core\Utils\Util;

/**
 * Creates the select manager of an entity: the class set in app.selectManagers, then the class of the module
 * of the entity, then the class of the core, then the base select manager. A derivative entity uses
 * the select manager of its primary entity.
 */
class SelectManagerFactory
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * The select manager works for the current user.
     */
    public function create(string $entityType): Base
    {
        $className = $this->getClassName($entityType);

        return new $className($this->container, $entityType);
    }

    private function getClassName(string $entityType): string
    {
        $metadata = $this->container->get('metadata');

        $className = $metadata->get(['app', 'selectManagers', $entityType]);
        if (!empty($className)) {
            return $className;
        }

        $scope = $metadata->get("scopes.$entityType.primaryEntityId") ?: $entityType;
        $name = Util::normilizeClassName($scope);

        $module = $metadata->getScopeModuleName($scope);
        if ($module && class_exists("$module\\SelectManagers\\$name")) {
            return "$module\\SelectManagers\\$name";
        }

        if (class_exists("Atro\\SelectManagers\\$name")) {
            return "Atro\\SelectManagers\\$name";
        }

        return Base::class;
    }
}

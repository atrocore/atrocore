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

namespace Atro\Services;

use Atro\Core\Exceptions\Error;
use Atro\Core\Templates\Services\ReferenceData;

class Entity extends ReferenceData
{
    public function resetToDefault(string $scope): bool
    {
        if ($this->getMetadata()->get("scopes.$scope.isCustom")) {
            throw new Error("Can't reset to defaults custom entity '$scope'.");
        }

        @unlink("data/metadata/clientDefs/{$scope}.json");
        @unlink("data/metadata/scopes/{$scope}.json");

        $this->getMetadata()->delete('entityDefs', $scope, [
            'collection.sortBy',
            'collection.asc',
            'collection.textFilterFields'
        ]);
        $this->getMetadata()->save();

        $this->getInjection('dataManager')->clearCache();

        return true;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('dataManager');
    }
}

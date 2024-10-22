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

namespace Atro\SelectManagers;

use Espo\Core\SelectManagers\Base;
use Atro\Services\Composer;

class Store extends Base
{
//    /**
//     * @inheritdoc
//     */
//    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
//    {
//        // parent
//        $result = parent::getSelectParams($params, $withAcl, $checkWherePermission);
//
//        if (isset($params['isInstalled']) && empty($params['isInstalled'])) {
//            $result['whereClause'][] = [
//                'id!='        => array_keys($this->getMetadata()->getModules()),
//                'packageId!=' => $this->getComposerModules()
//            ];
//        }
//
//        return $result;
//    }
//
//    /**
//     * @return array
//     */
//    private function getComposerModules(): array
//    {
//        $data = Composer::getComposerJson();
//
//        $result = [];
//        if (isset($data['require'])) {
//            $result = array_keys($data['require']);
//        }
//
//        return $result;
//    }
}

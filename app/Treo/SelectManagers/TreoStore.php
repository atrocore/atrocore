<?php

declare(strict_types=1);

namespace Treo\SelectManagers;

use Espo\Core\SelectManagers\Base;
use Treo\Services\Composer;

/**
 * Class TreoStore
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class TreoStore extends Base
{
    /**
     * @inheritdoc
     */
    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
        // parent
        $result = parent::getSelectParams($params, $withAcl, $checkWherePermission);

        if (isset($params['isInstalled']) && empty($params['isInstalled'])) {
            $result['whereClause'][] = [
                'id!='        => array_keys($this->getMetadata()->getModules()),
                'packageId!=' => $this->getComposerModules()
            ];
        }

        return $result;
    }

    /**
     * @return array
     */
    private function getComposerModules(): array
    {
        $data = Composer::getComposerJson();

        $result = [];
        if (isset($data['require'])) {
            $result = array_keys($data['require']);
        }

        return $result;
    }
}

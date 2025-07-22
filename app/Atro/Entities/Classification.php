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

namespace Atro\Entities;

use Atro\Core\Templates\Entities\Hierarchy;

class Classification extends Hierarchy
{
    /**
     * @var string
     */
    protected $entityType = 'Classification';

    public function _getProductsIds(): array
    {
        $data = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->select(['id'])
            ->join('classifications')
            ->where(['classifications.id' => $this->get('id')])
            ->find()
            ->toArray();

        return array_column($data, 'id');
    }
}

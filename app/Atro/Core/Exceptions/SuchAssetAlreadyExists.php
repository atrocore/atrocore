<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Exceptions;

use Atro\Entities\Asset;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\WithStatusReasonData;

class SuchAssetAlreadyExists extends BadRequest implements WithStatusReasonData
{
    protected ?Asset $asset = null;

    public function getStatusReasonData(): string
    {
        if (empty($this->asset)) {
            return '';
        }

        return $this->asset->get('id');
    }

    public function setAsset(Asset $asset): SuchAssetAlreadyExists
    {
        $this->asset = $asset;

        return $this;
    }
}

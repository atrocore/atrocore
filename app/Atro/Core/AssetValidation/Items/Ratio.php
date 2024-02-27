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

namespace Atro\Core\AssetValidation\Items;

use Atro\Core\AssetValidation\Base;
use Espo\Core\Exceptions\BadRequest;

class Ratio extends Base
{
    /**
     * @return bool
     */
    public function validate(): bool
    {
        $imageParams = getimagesize($this->getFilePath());

        return ($imageParams[0] / $imageParams[1]) == $this->params;
    }

    /**
     * @throws BadRequest
     */
    public function onValidateFail()
    {
        throw new BadRequest(sprintf($this->exception('imageRatioValidationFailed'), $this->params));
    }
}
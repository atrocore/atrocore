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

class Quality extends Base
{
    /**
     * @return bool
     * @throws \ImagickException
     */
    public function validate(): bool
    {
        $img = new \Imagick($this->getFilePath());
        $quality = $img->getImageCompressionQuality();

        if ($img->getImageMimeType() !== "image/jpeg") {
            return true;
        }

        return $quality >= $this->params['min'] && $quality <= $this->params['max'];
    }

    /**
     * @throws BadRequest
     */
    public function onValidateFail()
    {
        throw new BadRequest(sprintf($this->exception('imageQualityValidationFailed'), $this->params['min'], $this->params['max']));
    }
}
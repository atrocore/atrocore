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

/**
 * Class ColorDepth
 *
 * @package Dam\Core\Validation\Items
 */
class ColorDepth extends Base
{
    /**
     * @return bool
     * @throws \ImagickException
     */
    public function validate(): bool
    {
        $img = new \Imagick($this->getFilePath());

        return in_array($img->getImageDepth(), $this->params);
    }

    /**
     * @throws BadRequest
     */
    public function onValidateFail()
    {
        throw new BadRequest(sprintf($this->exception('colorDepthValidationFailed'), implode(", ", $this->params)));
    }
}
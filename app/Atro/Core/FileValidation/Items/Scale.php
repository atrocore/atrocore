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

namespace Atro\Core\FileValidation\Items;

use Atro\Core\FileValidation\Base;
use Atro\Core\Exceptions\BadRequest;
use Atro\Entities\File;

class Scale extends Base
{
    public function validate(File $file): bool
    {
        list ($width, $height) = getimagesizefromstring($file->getContents());

        return (empty($this->params['minWidth']) || $width > $this->params['minWidth']) && (empty($this->params['minHeight']) || $height > $this->params['minHeight']);
    }

    /**
     * @throws BadRequest
     */
    public function onValidateFail()
    {
        if (!empty($this->params['minWidth']) && !empty($this->params['minHeight'])) {
            throw new BadRequest(sprintf($this->exception('imageScaleValidationFailed'), $this->params['minWidth'], $this->params['minHeight']));
        } elseif (!empty($this->params['minWidth'])) {
            throw new BadRequest(sprintf($this->exception('imageScaleWidthValidationFailed'), $this->params['minWidth']));
        } else {
            throw new BadRequest(sprintf($this->exception('imageScaleHeightValidationFailed'), $this->params['minHeight']));
        }
    }
}

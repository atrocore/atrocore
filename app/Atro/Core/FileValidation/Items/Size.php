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

class Size extends Base
{
    public function validate(): bool
    {
        $imageSize = (filesize($this->file->getFilePath()) / 1024);
        if ($imageSize >= $this->validationRule->get('min') && $imageSize <= $this->validationRule->get('max')) {
            return true;
        }

        return false;
    }

    public function onValidateFail()
    {
        throw new BadRequest(sprintf($this->exception('imageSizeValidationFailed'), $this->params['min'], $this->params['max']));
    }
}
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

class Ratio extends Base
{
    public function validate(File $file): bool
    {
        list ($width, $height) = getimagesizefromstring($file->getContents());

        if (empty($height)) {
            return false;
        }

        $ratio = $this->aspectRatioToFloat($this->params['ratio']);
        if (empty($ratio)) {
            return false;
        }

        return ($width / $height) == $ratio;
    }

    public static function aspectRatioToFloat(string $ratio): ?float
    {
        [$width, $height] = explode(':', $ratio);

        if (empty((float)$height)) {
            return null;
        }

        return (float)$width / (float)$height;
    }


    public function onValidateFail()
    {
        throw new BadRequest(sprintf($this->exception('imageRatioValidationFailed'), $this->params['ratio']));
    }
}

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

class Mime extends Base
{
    public function validate(File $file): bool
    {
        $mimeType = (string)mime_content_type($file->getFilePath());

        if ($this->rule->get('validateBy') == 'List') {
            return in_array($mimeType, $this->rule->get('mimeList'));
        } elseif ($this->rule->get('validateBy') == 'Pattern') {
            return stripos($mimeType, $this->rule->get('pattern')) === false ? false : true;
        }

        return true;
    }

    public function onValidateFail()
    {
        throw new BadRequest($this->exception('mimeTypeValidationFailed'));
    }
}
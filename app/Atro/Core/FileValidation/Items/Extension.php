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

class Extension extends Base
{
    public function validate(File $file): bool
    {
        return in_array(strtolower(pathinfo($file->get('name'))['extension']), array_map('strtolower', $this->rule->get('extension')));
    }

    public function onValidateFail()
    {
        throw new BadRequest(sprintf($this->exception('fileExtensionValidationFailed'), implode(", ", $this->rule->get('extension'))));
    }
}

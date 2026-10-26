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
use Atro\Core\Utils\MimeTypeResolver;

class Mime extends Base
{
    public function validate(File $file): bool
    {
        // the same resolution the stored mimeType gets, so a file is validated as what it will be saved as
        $mimeType = $this->getMimeTypeResolver()->resolveByContents($file->getContents(), $file->get('name'));

        if (in_array($mimeType, $this->params['mimeTypes'])) {
            return true;
        }

        foreach ($this->params['mimeTypes'] ?? [] as $type) {
            // if type is a regex
            if (preg_match('/^\/.*\/[a-z]*$/', $type) === 1) {
                if (preg_match($type, $mimeType) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    public function onValidateFail()
    {
        throw new BadRequest($this->exception('mimeTypeValidationFailed'));
    }

    protected function getMimeTypeResolver(): MimeTypeResolver
    {
        return $this->container->get(MimeTypeResolver::class);
    }
}

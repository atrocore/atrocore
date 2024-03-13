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

namespace Atro\Core\AssetValidation\Items;

use Atro\Core\AssetValidation\Base;
use Espo\Core\Exceptions\BadRequest;

/**
 * Class Mime
 */
class Mime extends Base
{
    /**
     * @return bool
     */
    public function validate(): bool
    {
        // get mime type
        $mimeType = (string)mime_content_type($this->getFilePath());

        if (isset($this->params['list'])) {
            return in_array($mimeType, $this->params['list']);
        } elseif (isset($this->params['pattern'])) {
            return stripos($mimeType, $this->params['pattern']) === false ? false : true;
        }

        return true;
    }

    /**
     * @throws BadRequest
     */
    public function onValidateFail()
    {
        throw new BadRequest($this->exception('mimeTypeValidationFailed'));
    }
}
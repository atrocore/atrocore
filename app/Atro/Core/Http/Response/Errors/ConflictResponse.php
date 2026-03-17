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

namespace Atro\Core\Http\Response\Errors;

use Atro\Core\Http\Response\ErrorResponse;

class ConflictResponse extends ErrorResponse
{
    public function __construct(string $message = 'Conflict', array $extraHeaders = [])
    {
        parent::__construct(409, $message, $extraHeaders);
    }
}

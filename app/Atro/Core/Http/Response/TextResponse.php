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

namespace Atro\Core\Http\Response;

use GuzzleHttp\Psr7\Response;

class TextResponse extends Response
{
    public function __construct(string $text, int $status = 200)
    {
        parent::__construct(
            $status,
            ['Content-Type' => 'text/plain; charset=utf-8'],
            $text
        );
    }
}

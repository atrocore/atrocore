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

class ErrorResponse extends Response
{
    public function __construct(int $status, string $message, array $extraHeaders = [])
    {
        // HTTP header values must not contain control characters (newlines, etc.)
        $headerMessage = preg_replace('/[\x00-\x08\x0A-\x1F\x7F]/', ' ', $message);
        parent::__construct(
            $status,
            array_merge(['Content-Type' => 'text/html; charset=utf-8', 'X-Status-Reason' => $headerMessage], $extraHeaders),
            $message
        );
    }
}

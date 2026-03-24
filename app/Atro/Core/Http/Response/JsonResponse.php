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
use Psr\Http\Message\ResponseInterface;

class JsonResponse extends Response
{
    private const HEADERS = [
        'Content-Type'  => 'application/json; charset=utf-8',
        'Expires'       => '0',
        'Cache-Control' => 'no-store, no-cache, must-revalidate',
        'Pragma'        => 'no-cache',
    ];

    public function __construct(array $data, int $status = 200)
    {
        parent::__construct($status, self::HEADERS, json_encode($data));
    }

    /**
     * For legacy controllers that return a pre-encoded JSON string.
     */
    public static function raw(string $json, int $status = 200): ResponseInterface
    {
        return new Response($status, self::HEADERS, $json);
    }
}

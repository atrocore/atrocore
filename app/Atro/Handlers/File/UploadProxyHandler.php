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

namespace Atro\Handlers\File;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Routing\Route;
use Atro\Core\Utils\UrlGuard;
use Atro\Handlers\AbstractHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/File/uploadProxy',
    methods: [
        'POST',
    ],
    summary: 'Proxy file upload from URL',
    description: 'Streams a remote file through the server to the client, so the browser can upload a file it is not allowed to read cross-origin. Only http and https URLs pointing at routable public addresses are accepted, and redirects are not followed.',
    tag: 'File',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'url',
                    ],
                    'properties' => [
                        'url' => [
                            'type'   => 'string',
                            'format' => 'uri',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'File content stream',
        ],
        400 => [
            'description' => 'The URL is missing, is not http/https, resolves to a non-routable address, or could not be fetched.',
        ],
        403 => [
            'description' => 'The current user does not have File create permission.',
        ],
    ],
)]
class UploadProxyHandler extends AbstractHandler
{
    private const TIMEOUT       = 30;
    private const MAX_REDIRECTS = 0;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('File', 'create')) {
            throw new Forbidden();
        }

        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'url') || empty($data->url) || !is_string($data->url)) {
            throw new BadRequest();
        }

        $allowedHosts = (array)$this->getConfig()->get('fetchAllowedHosts', []);
        $url          = UrlGuard::assertFetchable($data->url, $allowedHosts);

        $stream = $this->fetch($url, UrlGuard::isHostAllowlisted($url, $allowedHosts));

        return new Response(200, ['Content-Type' => 'application/octet-stream'], Utils::streamFor($stream));
    }

    /**
     * Downloads into a temp stream rather than handing the URL to fopen(): that way redirects
     * stay off, and the address the transfer actually connected to is checked as well, which is
     * what a name resolving differently for the check and for the request would rely on.
     *
     * @return resource
     *
     * @throws BadRequest
     */
    private function fetch(string $url, bool $hostAllowlisted)
    {
        $target = fopen('php://temp', 'w+b');
        if ($target === false) {
            throw new BadRequest('Failed to open file stream.');
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $target);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, self::MAX_REDIRECTS > 0);
        curl_setopt($ch, CURLOPT_MAXREDIRS, self::MAX_REDIRECTS);
        UrlGuard::restrictProtocols($ch);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);

        // aborts as soon as the headers are in, if the connection landed somewhere it should not have
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, string $header) use ($hostAllowlisted) {
            $ip = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
            if (!$hostAllowlisted && !empty($ip)) {
                try {
                    UrlGuard::assertIpAllowed($ip);
                } catch (BadRequest $e) {
                    return 0;
                }
            }

            return strlen($header);
        });

        curl_exec($ch);
        $error        = curl_errno($ch);
        $responseCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $primaryIp    = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        curl_close($ch);

        if ($primaryIp !== '' && !$hostAllowlisted) {
            UrlGuard::assertIpAllowed($primaryIp);
        }

        if ($error !== 0 || $responseCode < 200 || $responseCode >= 300) {
            fclose($target);
            throw new BadRequest('Failed to fetch the given URL.');
        }

        rewind($target);

        return $target;
    }
}

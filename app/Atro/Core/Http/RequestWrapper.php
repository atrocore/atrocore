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

namespace Atro\Core\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Wraps a PSR-7 ServerRequestInterface and exposes Slim 2-compatible methods
 * so that legacy controllers work without modification during the migration period.
 *
 * @deprecated Remove when all controllers are migrated to PSR-15 handlers.
 */
class RequestWrapper
{
    public function __construct(private readonly ServerRequestInterface $request)
    {
    }

    public function getMethod(): string
    {
        return $this->request->getMethod();
    }

    public function isGet(): bool
    {
        return $this->request->getMethod() === 'GET';
    }

    public function isPost(): bool
    {
        return $this->request->getMethod() === 'POST';
    }

    public function isPut(): bool
    {
        return $this->request->getMethod() === 'PUT';
    }

    public function isPatch(): bool
    {
        return $this->request->getMethod() === 'PATCH';
    }

    public function isDelete(): bool
    {
        return $this->request->getMethod() === 'DELETE';
    }

    /**
     * Returns a query-string parameter value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->request->getQueryParams()[$key] ?? $default;
    }

    /**
     * Returns the raw request body as a string.
     */
    public function getBody(): string
    {
        return (string) $this->request->getBody();
    }

    public function getContentType(): string
    {
        return $this->request->getHeaderLine('Content-Type');
    }

    public function headers(string $key): ?string
    {
        $value = $this->request->getHeaderLine($key);

        return $value !== '' ? $value : null;
    }

    public function getPsr7Request(): ServerRequestInterface
    {
        return $this->request;
    }
}

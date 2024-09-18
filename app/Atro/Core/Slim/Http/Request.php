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

namespace Atro\Core\Slim\Http;

class Request extends \Slim\Http\Request
{
    /**
     * Set data to query
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return Request
     */
    public function setQuery(string $key, $value): Request
    {
        // get query
        $query = $this->env['slim.request.query_hash'];

        // prepare query
        $query[$key] = $value;

        // set to query
        $this->env['slim.request.query_hash'] = $query;

        return $this;
    }

    public function getPsrRequest(): \Psr\Http\Message\RequestInterface
    {
        $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $uri = $psr17Factory->createUri($this->getUrl() . $this->getPathInfo());
        $headers = $this->headers->all();
        $psr7Request = $psr17Factory->createServerRequest($this->getMethod(), $uri, $_SERVER);

        foreach ($headers as $name => $value) {
            $psr7Request = $psr7Request->withAddedHeader($name, $value);
        }

        return $psr7Request->withBody($psr17Factory->createStream($this->getBody()));
    }
}

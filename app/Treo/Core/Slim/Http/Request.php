<?php

declare(strict_types=1);

namespace Treo\Core\Slim\Http;

use Slim\Http\Request as SlimRequest;

/**
 * Request class
 *
 * @author r.ratsun@zinitsolutions.com
 */
class Request extends SlimRequest
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
}

<?php

declare(strict_types=1);

namespace Treo\Core\Utils\Api;

use Espo\Core\Utils\Api\Slim as EspoSlim;
use Treo\Core\Slim\Http\Request;

/**
 * Slim class
 *
 * @author r.ratsun@zinitsolutions.com
 */
class Slim extends EspoSlim
{
    /**
     * Slim construct
     */
    public function __construct(...$args)
    {
        // call parent
        parent::__construct(...$args);

        // set request
        $this->container->singleton('request', function ($c) {
            return new Request($c['environment']);
        });
    }
}

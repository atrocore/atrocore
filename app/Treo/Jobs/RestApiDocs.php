<?php

declare(strict_types=1);

namespace Treo\Jobs;

use Espo\Core\Jobs\Base;

/**
 * RestApiDocs job
 *
 * @author r.ratsun r.ratsun@zinitsolutions.com
 */
class RestApiDocs extends Base
{
    /**
     * Run cron job
     *
     * @return bool
     */
    public function run(): bool
    {
        return $this->getServiceFactory()->create('RestApiDocs')->generateDocumentation();
    }
}

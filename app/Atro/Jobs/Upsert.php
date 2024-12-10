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

namespace Atro\Jobs;

use Atro\Entities\Job;
use Espo\Core\Utils\Json;

class Upsert extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();

        try {
            $result = $this->getServiceFactory()->create('MassActions')->upsert((array)Json::decode(Json::encode($data)));
            $message = Json::encode($result);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
        }

        $job->set('message', $message);
        $this->getEntityManager()->saveEntity($job, ['skipAll' => true]);
    }
}

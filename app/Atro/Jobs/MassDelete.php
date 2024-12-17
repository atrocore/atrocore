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

use Atro\Core\Exceptions\NotFound;
use Atro\Entities\Job;

class MassDelete extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();
        if (empty($data['entityType']) || empty($data['total']) || empty($data['ids'])) {
            return;
        }

        $entityType = $data['entityType'];
        $service = $this->getServiceFactory()->create($data['entityType']);

        $method = 'deleteEntity';
        if (!empty($data['deletePermanently'])) {
            $method = 'deleteEntityPermanently';
        }

        foreach ($data['ids'] as $id) {
            try {
                $service->$method($id);
            } catch (NotFound $e) {
                // ignore
            } catch (\Throwable $e) {
                $message = "MassDelete {$entityType} '$id', failed: {$e->getMessage()}";
                $GLOBALS['log']->error($message);
                $this->createNotification($job, $message);
            }
        }
    }
}

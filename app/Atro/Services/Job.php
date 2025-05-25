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

namespace Atro\Services;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class Job extends Base
{
    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        parent::beforeUpdateEntity($entity, $data);

        if (!empty($data->status) && $data->status === 'Running') {
            throw new Forbidden();
        }
    }


    public function getMassActionJobStatus(string $jobId): array
    {
        $job = $this->getRepository()->get($jobId);
        if (empty($job)) {
            throw new NotFound();
        }

        $messages = [];
        $errors = [];

        if ($job->status !== 'Success') {
            $done = false;
        } else {
            $childJobs = $this
                ->getRepository()
                ->where([
                    'payload*' => '%"jobCreatorId":"' . $jobId . '"%'
                ])
                ->find();

            $done = true;
            foreach ($childJobs as $childJob) {
                if (!in_array($childJob->status, ['Success', 'Failed'])) {
                    $done = false;
                    break;
                }

                $jobMessages = explode("\n", $childJob->message);
                $messages[] = $jobMessages[0];
                array_splice($jobMessages, 0, 1);
                $errors = array_merge($errors, $jobMessages);
            }
        }

        return [
            'done'    => $done,
            'errors'  => str_replace("\n", '<br>', trim(implode("\n", $errors))),
            'message' => str_replace("\n", '<br>', trim(implode("\n", $messages))),
        ];
    }
}

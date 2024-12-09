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

namespace Atro\Repositories;

use Atro\Core\Exceptions\Error;
use Atro\Core\JobManager;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class Job extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->isAttributeChanged('status')) {
            if ($entity->get('status') === 'Pending') {
                $entity->set('message', null);
            }

            if (!$entity->isNew()) {
                $transitions = [
                    "Running"  => [
                        "Pending"
                    ],
                    "Success"  => [
                        "Running"
                    ],
                    "Canceled" => [
                        "Pending",
                        "Running"
                    ],
                    "Failed"   => [
                        "Pending",
                        "Running"
                    ],
                    "Pending"  => [
                        "Success",
                        "Canceled",
                        "Failed"
                    ]
                ];

                if (!isset($transitions[$entity->get('status')])) {
                    throw new Error("Unknown status '{$entity->get('status')}'.");
                }

                if (!in_array($entity->getFetched('status'), $transitions[$entity->get('status')])) {
                    throw new Error("It is impossible to change the status from '{$entity->getFetched('status')}' to '{$entity->get('status')}'.");
                }
            }
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->get('status') === 'Pending' && !empty($entity->get('type'))) {
            file_put_contents(JobManager::QUEUE_FILE, '1');
        }

        if ($entity->get('status') === 'Canceled' && !empty($entity->get('pid'))) {
            exec("kill -9 {$entity->get('pid')}");
        }
    }
}

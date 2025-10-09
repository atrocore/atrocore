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

use Atro\Core\AttributeFieldConverter;
use Atro\Core\ORM\Repositories\RDB;
use Atro\Entities\Job;

class CalculateScriptFieldsForEntity extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();

        if (empty($data['scope']) || empty($data['ids'])) {
            return;
        }

        /** @var RDB $repository */
        $repository = $this->getEntityManager()->getRepository($data['scope']);

        $entities = $repository->where(['id' => $data['ids']])->find();

        foreach ($entities as $entity) {
            $this->getContainer()->get(AttributeFieldConverter::class)->putAttributesToEntity($entity);
            $repository->calculateScriptFields($entity);
        }
    }
}

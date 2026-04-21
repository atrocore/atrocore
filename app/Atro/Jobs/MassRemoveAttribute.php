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

use Atro\Core\Exceptions\NotModified;
use Atro\Entities\Job;
use Atro\ORM\DB\RDB\Mapper;

class MassRemoveAttribute extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();
        if (empty($data['entityType']) || empty($data['total']) || empty($data['ids']) || empty($data['attributes'])) {
            return;
        }

        if (
            $this->getMetadata()->get(['scopes', $data['entityType'], 'hasAttribute'])
            && $this->getMetadata()->get(['scopes', $data['entityType'], 'disableAttributeLinking'])
        ) {
            return;
        }

        $attributes = $data['attributes'];

        $attributeService = $this->getServiceFactory()->create('Attribute');
        $recordService    = $this->getServiceFactory()->create($data['entityType']);

        $attributeIds = $attributes['ids'] ?? [];

        if (empty($attributeIds) && !empty($attributes['where'])) {
            $sp           = $attributeService->getSelectParams(['where' => $attributes['where']]);
            $sp['select'] = ['id'];

            $records      = $this->getEntityManager()->getRepository('Attribute')->find($sp);
            $attributeIds = array_column($records->toArray(), 'id');
        }

        $count  = 0;
        $errors = [];

        foreach ($data['ids'] as $id) {
            $input                       = new \stdClass();
            $input->__attributesToRemove = $attributeIds;

            try {
                $recordService->updateEntity($id, $input);
                $count++;
            } catch (NotModified $e) {

            } catch (\Throwable $e) {
                $errors[] = "Error for $id: " . $e->getMessage();
            }
        }

        // update Job message
        $message = "$count record(s) updated";
        if (!empty($errors)) {
            $message .= "\n" . implode("\n", $errors);
        }
        $job->set('message', $message);
    }
}

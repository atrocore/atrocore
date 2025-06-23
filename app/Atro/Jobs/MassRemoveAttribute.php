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

use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotModified;
use Atro\Entities\Job;

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

        $service = $this->getServiceFactory()->create('Attribute');

        $count = 0;
        $errors = [];

        foreach ($data['ids'] as $id) {
            $res = $service->removeAttributeValues($data['entityType'], $id, $attributes['ids'] ?? null, $attributes['where'] ?? null);

            $count += $res['count'] ?? 0;
            $errors = array_merge($errors, $res['errors'] ?? []);
        }

        // update Job message
        $message = "$count attribute(s) removed";
        if (!empty($errors)) {
            $message .= "\n" . implode("\n", $errors);
        }
        $job->set('message', $message);
    }
}

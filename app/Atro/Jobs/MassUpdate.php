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
use Espo\ORM\Entity;

class MassUpdate extends AbstractJob implements JobInterface
{
    public function run(Entity $job): void
    {
        $data = $job->get('payload');
        if (empty($data['entityType']) || empty($data['total']) || empty($data['ids']) || empty($data['input'])) {
            return;
        }

        $service = $this->getServiceFactory()->create($data['entityType']);

        foreach ($data['ids'] as $id) {
            $input = json_decode(json_encode($data['input']));
            $input->_isMassUpdate = true;

            try {
                $service->updateEntity($id, $input);
            } catch (NotModified $e) {
            } catch (\Throwable $e) {
                $message = "Update {$data['entityType']} '$id' failed: {$e->getMessage()}";
                $GLOBALS['log']->error($message);
                $this->createNotification($job, $message);
            }
        }
    }
}

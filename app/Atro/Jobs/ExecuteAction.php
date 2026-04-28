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

use Atro\Core\ActionManager;
use Atro\Entities\Job;

class ExecuteAction extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data     = $job->getPayload();
        $actionId = (string)($data['actionId'] ?? '');

        $action = $this->getEntityManager()->getRepository('Action')->get($actionId);
        if (empty($action)) {
            throw new \RuntimeException("Action '$actionId' not found.");
        }

        $input           = new \stdClass();
        $inputData       = $data['input'] ?? [];
        foreach ($inputData as $key => $value) {
            $input->$key = $value;
        }
        $input->executedViaJob = true;
        $input->job            = $job;

        $this->getActionManager()->executeNow($action, $input);
    }

    protected function getActionManager(): ActionManager
    {
        return $this->getContainer()->get('actionManager');
    }
}

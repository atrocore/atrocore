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

class DeleteFilesChunks extends AbstractJob
{
    public function run(): bool
    {
        foreach ($this->getEntityManager()->getRepository('Storage')->find() as $storage) {
            $this->getContainer()->get($storage->get('type') . 'Storage')->deleteAllChunks($storage);
        }

        return true;
    }
}
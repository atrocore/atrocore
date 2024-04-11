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

namespace Atro\Console;

use Espo\ORM\EntityManager;

class ScanStorage extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Scan storage. The scanner will automatically prepare records at the File entity.';
    }

    public function run(array $data): void
    {
        $auth = new \Espo\Core\Utils\Auth($this->getContainer());
        $auth->useNoAuth();

        /** @var EntityManager $em */
        $em = $this->getContainer()->get('entityManager');

        if (empty($data['id']) || empty($storage = $em->getRepository('Storage')->get($data['id']))) {
            self::show('No such Storage found!', self::ERROR, true);
        }

        try {
            $this->getContainer()->get($storage->get('type') . 'Storage')->scan($storage);
            self::show("Storage '{$storage->get('name')}' has been scanned successfully.", self::SUCCESS);
        } catch (\Throwable $e) {
            self::show("Scanning the storage '{$storage->get('name')}' has been failed: " . $e->getMessage(), self::ERROR);
        }
    }
}

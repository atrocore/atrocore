<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Console;

use Espo\ORM\EntityManager;

class RegenerateExtensibleEnums extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Regenerate system lists.';
    }

    public function run(array $data): void
    {
        $this->refresh();
        $this->getContainer()->get('dataManager')->clearCache();

        self::show('Lists regenerated successfully.', self::SUCCESS);
    }

    public function refresh(): void
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('entityManager');

        foreach ($this->getMetadata()->get(['app', 'extensibleEnums'], []) as $extensibleEnumData) {
            $extensibleEnum = $em->getRepository('ExtensibleEnum')->get();
            $extensibleEnum->id = $extensibleEnumData['id'];
            $extensibleEnum->set($extensibleEnumData);

            try {
                $em->saveEntity($extensibleEnum);
            } catch (\Throwable $e) {
                // ignore all
            }
        }

        foreach ($this->getMetadata()->get(['app', 'extensibleEnumOptions'], []) as $extensibleEnumOptionData) {
            $extensibleEnumOption = $em->getRepository('ExtensibleEnumOption')->get();
            $extensibleEnumOption->id = $extensibleEnumOptionData['id'];
            $extensibleEnumOption->set($extensibleEnumOptionData);

            try {
                $em->saveEntity($extensibleEnumOption);
            } catch (\Throwable $e) {
                // ignore all
            }
        }
    }
}

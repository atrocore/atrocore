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
            $extensibleEnum = $em->getRepository('ExtensibleEnum')->get($extensibleEnumData['id']);
            if (!empty($extensibleEnum)) {
                continue;
            }
            $extensibleEnum = $em->getRepository('ExtensibleEnum')->get();
            $extensibleEnum->id = $extensibleEnumData['id'];
            $extensibleEnum->set($extensibleEnumData);

            try {
                $em->saveEntity($extensibleEnum);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("ExtensibleEnum generation failed: {$e->getMessage()}");
            }
        }

        foreach ($this->getMetadata()->get(['app', 'extensibleEnumOptions'], []) as  $extensibleEnumOptionData) {
            $extensibleEnumOption = $em->getRepository('ExtensibleEnumOption')->get($extensibleEnumOptionData['id']);

            if (empty($extensibleEnumOption)) {
                $extensibleEnumOption = $em->getRepository('ExtensibleEnumOption')->get();
                $extensibleEnumOption->id = $extensibleEnumOptionData['id'];
                $extensibleEnumOption->set($extensibleEnumOptionData);

                try {
                    $em->saveEntity($extensibleEnumOption);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error("ExtensibleEnumOption generation sdfsdfsd failed: {$e->getMessage()}");
                }
            }

            $eeeeo = $em->getRepository('ExtensibleEnumExtensibleEnumOption')
                ->where([
                    "extensibleEnumId" => $extensibleEnumOptionData['extensibleEnumId'],
                    "extensibleEnumOptionId" => $extensibleEnumOptionData['id']
                ])
                ->findOne();

            if(!empty($eeeeo)){
                continue;
            }

            $eeeeo = $em->getRepository('ExtensibleEnumExtensibleEnumOption')->get();
            $eeeeo->set('extensibleEnumId', $extensibleEnumOptionData['extensibleEnumId']);
            $eeeeo->set('extensibleEnumOptionId', $extensibleEnumOptionData['id']);
            $eeeeo->set('sorting', $extensibleEnumOptionData['sortOrder']);

            try {
                $em->saveEntity($eeeeo);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("ExtensibleEnumExtensibleEnumOption generation failed: {$e->getMessage()}");
            }
        }
    }
}

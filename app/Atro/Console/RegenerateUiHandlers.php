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

use Atro\Core\KeyValueStorages\StorageInterface;
use Doctrine\DBAL\Connection;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityManager;

class RegenerateUiHandlers extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Regenerate UI handlers.';
    }

    public function run(array $data): void
    {
        $this->refresh();
        $this->getContainer()->get('dataManager')->clearCache();

        self::show('UI handlers regenerated successfully.', self::SUCCESS);
    }

    public function refresh(): void
    {
        $this->getMemoryStorage()->set('ignorePushUiHandler', true);
        $clientDefsData = $this->getMetadata()->get('clientDefs', []);
        $this->getMemoryStorage()->set('ignorePushUiHandler', false);

        /** @var Connection $conn */
        $conn = $this->getContainer()->get('connection');
        $conn->createQueryBuilder()
            ->delete('ui_handler', 'q1')
            ->where('q1.hash IS NOT NULL')
            ->executeQuery();

        /** @var EntityManager $em */
        $em = $this->getContainer()->get('entityManager');

        foreach ($clientDefsData as $entityType => $clientDefs) {
            if (empty($clientDefs['dynamicLogic']['fields'])) {
                continue;
            }

            foreach ($clientDefs['dynamicLogic']['fields'] as $field => $fieldConditions) {
                foreach ($fieldConditions as $type => $fieldData) {
                    if (empty($fieldData['conditionGroup'])) {
                        continue;
                    }

                    switch ($type) {
                        case 'readOnly':
                            $typeId = 'ui_read_only';
                            break;
                        case 'visible':
                            $typeId = 'ui_visible';
                            break;
                        case 'required':
                            $typeId = 'ui_required';
                            break;
                        default:
                            $typeId = null;
                    }

                    if (empty($typeId)) {
                        continue;
                    }

                    $entity = $em->getRepository('UiHandler')->get();
                    $entity->id = Util::generateId();
                    $entity->set([
                        'name'           => "Make field '{$field}' {$type}",
                        'hash'           => md5("{$entityType}{$field}{$type}"),
                        'entityType'     => $entityType,
                        'fields'         => [$field],
                        'triggerAction'  => ['onChange'],
                        'type'           => $typeId,
                        'conditionsType' => 'basic',
                        'conditions'     => json_encode($fieldData),
                        'isActive'       => true
                    ]);

                    try {
                        $em->saveEntity($entity);
                    } catch (\Throwable $e) {
                        $GLOBALS['log']->error("UI Handler generation failed: {$e->getMessage()}");
                    }
                }
            }
        }
    }

    protected function getMemoryStorage(): StorageInterface
    {
        return $this->getContainer()->get('memoryStorage');
    }
}

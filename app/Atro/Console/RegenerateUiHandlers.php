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

use Atro\Core\KeyValueStorages\StorageInterface;
use Espo\ORM\Entity;
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

                    $id = strtolower("ui_{$entityType}_{$field}_{$type}");

                    $entity = $em->getRepository('UiHandler')->get($id);
                    if (!empty($entity)) {
                        continue;
                    }

                    $typeId = null;

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
                    }

                    $entity = $em->getRepository('UiHandler')->get();
                    $entity->id = $id;
                    $entity->set([
                        'name'           => "Make field '{$field}' {$type}",
                        'entityType'     => $entityType,
                        'field'          => $field,
                        'type'           => $typeId,
                        'conditionsType' => 'basic',
                        'conditions'     => json_encode($fieldData),
                        'isActive'       => true
                    ]);

                    try {
                        $em->saveEntity($entity);
                    } catch (\Throwable $e) {
                        // ignore all
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

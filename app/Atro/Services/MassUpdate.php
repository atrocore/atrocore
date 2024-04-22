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

namespace Atro\Services;

use Atro\Core\Exceptions\NotModified;
use Espo\Core\DataManager;
use Espo\Core\Utils\Util;
use Espo\Services\QueueManagerBase;

class MassUpdate extends QueueManagerBase
{
    public function run(array $data = []): bool
    {
        if (empty($data['entityType']) || empty($data['total']) || empty($data['ids']) || empty($data['input']) || empty($data['totalChunks'])) {
            return false;
        }

        $entityType = $data['entityType'];

        $service = $this->getContainer()->get('serviceFactory')->create($entityType);

        foreach ($data['ids'] as $id => $position) {
            $input = json_decode(json_encode($data['input']));
            $input->_isMassUpdate = true;

            if($data['totalChunks'] === 1){
                $publicData = DataManager::getPublicData('massUpdate');

                $massUpdateData = $publicData[$entityType] ?? ['total' => $data['total'], 'updated' => 0];

                if(empty($publicData[$entityType]['updated'])){
                    $massUpdateData = ['total' => $data['total'], 'updated' => 0];
                    self::updatePublicData($entityType, $massUpdateData);
                }

                $this->execute($service, $id, $input, $data['entityType']);
                $updated = $position + 1;

                if ($massUpdateData['updated'] < $updated) {
                    $massUpdateData['updated'] = $updated;
                    if ($massUpdateData['updated'] === $massUpdateData['total'] ) {
                        $massUpdateData['done'] = Util::generateId();
                    }
                    self::updatePublicData($entityType, $massUpdateData);
                }
            }else{
                $this->execute($service, $id, $input, $data['entityType']);
            }

        }

        return true;
    }

    public static function updatePublicData(string $entityType, ?array $data): void
    {
        $publicData = DataManager::getPublicData('massUpdate');
        if (empty($publicData)) {
            $publicData = [];
        }
        $publicData[$entityType] = $data;
        DataManager::pushPublicData('massUpdate', $publicData);
    }

    public function execute($service, $id, $input, $entityType): void
    {
        try {
            $service->updateEntity($id, $input);
        } catch (NotModified $e) {
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("Update {$entityType} '$id' failed: {$e->getMessage()}");
        }
    }
}

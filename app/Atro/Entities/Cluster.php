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

namespace Atro\Entities;

use Atro\Core\Templates\Entities\Base;
use Espo\ORM\IEntity;

class Cluster extends Base
{
    public function _getGoldenRecord(): ?IEntity
    {
        if (empty($this->get('goldenRecordId'))) {
            return null;
        }

        if (!isset($this->relationsContainer['goldenRecord'])) {
            $this->setRelationValue('goldenRecord', $this->getEntityManager()->getEntity($this->get('masterEntity'), $this->get('goldenRecordId')));
        }

        return $this->relationsContainer['goldenRecord'];
    }

    public function getState(): string
    {
        $items = $this->get('clusterItems');

        if (empty($items) || count($items) == 0) {
            return 'empty';
        }

        $masterItemCount = 0;
        $stagingItemCount = 0;

        foreach ($items as $item) {
            if ($item->get('entityName') === $this->get('masterEntity')) {
                $masterItemCount++;
            } else {
                $stagingItemCount++;
            }
        }

        if ($masterItemCount > 1 || $stagingItemCount === 0) {
            return 'invalid';
        }

        if (!empty($goldenRecord = $this->get('goldenRecord'))) {
            $allMerged = true;
            foreach ($items as $item) {
                if ($item->get('entityName') === $this->get('masterEntity')) {
                    if ($item->get('entityId') !== $goldenRecord->get('id')) {
                        $allMerged = false;
                        break;
                    }
                } else {
                    $record = $item->get('record');
                    if (empty($record) || $record->get('goldenRecordId') !== $goldenRecord->get('id')) {
                        $allMerged = false;
                        break;
                    }
                }
            }

            if ($allMerged) {
                return 'merged';
            }
        }

        return 'review';
    }
}

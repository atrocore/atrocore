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

use Atro\Core\Templates\Services\ReferenceData;

class Matching extends ReferenceData
{
    public function getMatchedRecords(string $code, string $entityName, string $entityId): array
    {
        $matching = $this->getEntityManager()->getRepository('Matching')->getEntityByCode($code);
        if (empty($matching)) {
            return [];
        }

        if ($entityName === $matching->get('stagingEntity')) {
            return $this->getRepository()->getMatchedRecords($matching, $entityName, $entityId);
        }

        if ($entityName === $matching->get('masterEntity')) {
            return $this->getRepository()->getForeignMatchedRecords($matching, $entityName, $entityId);
        }

        return [];
    }
}

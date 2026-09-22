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

namespace Atro\DTOs;

class MasterRecordPayloadDTO
{
    protected array $masterRecordData;
    protected bool $skipped;

    public function __construct(array $masterRecordData, bool $skipped)
    {
        $this->masterRecordData = $masterRecordData;
        $this->skipped = $skipped;
    }

    public function getMasterRecordData(): array
    {
        return $this->masterRecordData;
    }

    public function isSkipped(): bool
    {
        return $this->skipped;
    }
}

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

namespace Atro\DTO;

class HttpResponseDTO
{
    protected int $code;
    protected string $output;

    public function __construct(int $code, ?string $output)
    {
        $this->code = $code;
        $this->output = $output;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }
}
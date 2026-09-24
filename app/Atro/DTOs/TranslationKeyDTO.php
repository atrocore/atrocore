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

class TranslationKeyDTO
{
    public function __construct(
        public readonly string $scope,
        public readonly string $category,
        public readonly string $name,
        public readonly ?string $option = null
    ) {
    }

    public function getCode(): string
    {
        $code = "{$this->scope}.{$this->category}.{$this->name}";

        return $this->option === null ? $code : "$code.{$this->option}";
    }
}
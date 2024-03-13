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

namespace Atro\Core\Exceptions;

class Conflict extends Exception
{
    protected $code = 409;
    protected array $fields = [];

    public function setFields(array $fields): Conflict
    {
        $this->fields = $fields;
        return $this;
    }

    public function getFields(): array
    {
        return $this->fields;
    }
}


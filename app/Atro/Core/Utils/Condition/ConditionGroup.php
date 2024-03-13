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

namespace Atro\Core\Utils\Condition;

/**
 * Class ConditionGroup
 */
class ConditionGroup
{
    /**
     * @var string
     */
    protected $type = '';
    /**
     * @var array
     */
    protected $values = [];

    /**
     * ConditionGroup constructor.
     * @param string $type
     * @param array $values
     */
    public function __construct(string $type, array $values)
    {
        $this->type = $type;
        $this->values = $values;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function getValues(): array
    {
        return $this->values;
    }
}

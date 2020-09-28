<?php

declare(strict_types=1);

namespace Treo\Core\Utils\Condition;

/**
 * Class ConditionGroup
 *
 * @author r.ratsun <r.ratsun@gmail.com>
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

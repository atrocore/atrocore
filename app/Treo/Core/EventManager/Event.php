<?php

declare(strict_types=1);

namespace Treo\Core\EventManager;

/**
 * Class Event
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class Event extends \Symfony\Contracts\EventDispatcher\Event
{
    /**
     * @var array
     */
    protected $arguments = [];

    /**
     * Event constructor.
     *
     * @param array $arguments
     */
    public function __construct(array $arguments = [])
    {
        $this->arguments = $arguments;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param mixed $key
     *
     * @return mixed
     */
    public function getArgument($key)
    {
        if (!$this->hasArgument($key)) {
            return null;
        }

        return $this->arguments[$key];
    }

    /**
     * @param mixed $key
     *
     * @return bool
     */
    public function hasArgument($key): bool
    {
        return isset($this->arguments[$key]);
    }

    /**
     * @param mixed $key
     * @param mixed $value
     *
     * @return Event
     */
    public function setArgument($key, $value): Event
    {
        $this->arguments[$key] = $value;

        return $this;
    }
}

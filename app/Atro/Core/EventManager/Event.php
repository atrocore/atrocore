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

namespace Atro\Core\EventManager;

/**
 * Class Event
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

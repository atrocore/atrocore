<?php

declare(strict_types=1);

namespace Treo\Core\Exceptions;

/**
 * Class NotModified
 *
 * @package Treo\Core\Exceptions
 */
class NotModified extends \Exception
{
    /**
     * @var int
     */
    protected $code = 304;
}

<?php

declare(strict_types=1);

namespace Treo\Core\Exceptions;

/**
 * Class NoChange
 *
 * @author     r.ratsun <r.ratsun@treolabs.com>
 *
 * @deprecated We will remove it after 01.01.2021
 */
class NoChange extends NotModified
{
    /**
     * @var string
     */
    protected $message = 'No changes for updating';
}

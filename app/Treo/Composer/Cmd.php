<?php

declare(strict_types=1);

namespace Treo\Composer;

/**
 * Class Cmd
 *
 * @author     r.ratsun <r.ratsun@gmail.com>
 *
 * @deprecated We will remove it after 01.01.2021
 */
class Cmd
{
    /**
     * After update
     *
     * @deprecated We will remove it after 01.01.2021
     */
    public static function postUpdate(): void
    {
        require_once 'vendor/autoload.php';

        (new PostUpdate(true))->run();
    }
}

<?php

declare(strict_types=1);

namespace Treo\Core\Utils;

use Espo\Core\Utils\Config as Base;

/**
 * Class of Config
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Config extends Base
{
    /**
     * @inheritDoc
     */
    public function get($name, $default = null)
    {
        if ($name == 'isUpdating') {
            return file_exists(COMPOSER_LOG);
        }

        return parent::get($name, $default);
    }

    /**
     * @inheritdoc
     */
    public function getDefaults()
    {
        return array_merge(parent::getDefaults(), include CORE_PATH . '/Treo/Configs/defaultConfig.php');
    }
}

<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://treolabs.com
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

namespace Espo\Core\Portal\Utils;

use Espo\Entities\Portal;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;

/**
 * Class ThemeManager
 * @package Espo\Core\Portal\Utils
 */
class ThemeManager extends \Espo\Core\Utils\ThemeManager
{
    /**
     * @var Portal
     */
    protected $portal;

    /**
     * ThemeManager constructor.
     * @param Config $config
     * @param Metadata $metadata
     * @param Portal $portal
     */
    public function __construct(Config $config, Metadata $metadata, Portal $portal)
    {
        $this->config = $config;
        $this->metadata = $metadata;
        $this->portal = $portal;
    }

    /**
     * Get name theme
     *
     * @return string
     */
    public function getName()
    {
        $theme = $this->portal->get('theme');

        if (!$theme) {
            $theme = $this->config->get('theme', $this->defaultName);
        }
        return $theme;
    }
}



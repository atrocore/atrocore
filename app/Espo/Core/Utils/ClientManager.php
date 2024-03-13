<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

namespace Espo\Core\Utils;

class ClientManager
{
    private $themeManager;

    private $config;

    protected $mainHtmlFilePath = 'client/html/main.html';

    protected $htmlFilePathForDeveloperMode = 'client/html/main.html';

    protected $runScript = "app.start();";

    protected $basePath = '';

    public function __construct(Config $config, ThemeManager $themeManager)
    {
        $this->config = $config;
        $this->themeManager = $themeManager;
    }

    protected function getThemeManager()
    {
        return $this->themeManager;
    }

    protected function getConfig()
    {
        return $this->config;
    }

    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
    }

    public function getBasePath()
    {
        return $this->basePath;
    }

    protected function getCacheTimestamp()
    {
        if (!$this->getConfig()->get('useCache')) {
            return (string)time();
        }

        return $this->getConfig()->get('cacheTimestamp', (string)time());
    }

    protected function getFaviconUrl(string $faviconId): string
    {
        return '{{basePath}}?entryPoint=Download&id=' . $faviconId;
    }

    protected function getFaviconHtml()
    {
        $faviconId = $this->getConfig()->get('faviconId');
        if (!empty($faviconId)) {
            return '<link rel="icon" href="' . $this->getFaviconUrl($faviconId) . '" />';
        } else {
            return '<link rel="icon" href="{{basePath}}client/modules/treo-core/img/favicon.svg" type="image/svg+xml" />
                    <link rel="icon" href="{{basePath}}client/modules/treo-core/img/favicon.ico" sizes="16x16" type="image/x-icon">
                    <link rel="icon" href="{{basePath}}client/modules/treo-core/img/favicon_32.png" sizes="32x32" type="image/png">
                    <link rel="icon" href="{{basePath}}client/modules/treo-core/img/favicon_48.png" sizes="48x48" type="image/png">
                    <link rel="icon" href="{{basePath}}client/modules/treo-core/img/favicon_96.png" sizes="96x96" type="image/png">
                    <link rel="icon" href="{{basePath}}client/modules/treo-core/img/favicon_144.png" sizes="144x144" type="image/png">';
        }
    }

    public function display($runScript = null, $htmlFilePath = null, $vars = array())
    {
        if (is_null($runScript)) {
            $runScript = $this->runScript;
        }
        if (is_null($htmlFilePath)) {
            $htmlFilePath = $this->mainHtmlFilePath;
        }

        $isDeveloperMode = $this->getConfig()->get('isDeveloperMode');

        if ($isDeveloperMode) {
            if (file_exists('client/html/' . $htmlFilePath)) {
                $htmlFilePath = 'client/html/' . $htmlFilePath;
            }
        }

        $html = file_get_contents($htmlFilePath);
        foreach ($vars as $key => $value) {
            $html = str_replace('{{' . $key . '}}', $value, $html);
        }
        $html = str_replace('{{applicationName}}', $this->getConfig()->get('applicationName', 'EspoCRM'), $html);
        $html = str_replace('{{cacheTimestamp}}', $this->getCacheTimestamp(), $html);
        $html = str_replace('{{faviconHtml}}', $this->getFaviconHtml(), $html);
        $html = str_replace('{{useCache}}', $this->getConfig()->get('useCache') ? 'true' : 'false', $html);
        $html = str_replace('{{stylesheet}}', $this->getThemeManager()->getStylesheet(), $html);
        $html = str_replace('{{runScript}}', $runScript, $html);
        $html = str_replace('{{basePath}}', $this->basePath, $html);

        if (!empty($customStylesheet = $this->getThemeManager()->getCustomStylesheet())) {
            $link = '<link href="' . $this->basePath . $customStylesheet . '?r=' . $this->getCacheTimestamp() . '" rel="stylesheet" id="custom-stylesheet">';
            $html = str_replace('{{customStylesheet}}', $link, $html);
        } else {
            $html = str_replace('{{customStylesheet}}', '', $html);
        }
        if ($isDeveloperMode) {
            $html = str_replace('{{useCacheInDeveloperMode}}', $this->getConfig()->get('useCacheInDeveloperMode') ? 'true' : 'false', $html);
        }

        echo $html;
    }
}

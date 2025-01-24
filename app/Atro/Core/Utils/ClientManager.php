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

namespace Atro\Core\Utils;

class ClientManager
{
    private ThemeManager $themeManager;

    private Config $config;

    protected string $mainHtmlFilePath = 'client/html/main.html';

    protected string $htmlFilePathForDeveloperMode = 'client/html/main.html';

    protected string $runScript = "app.start();";

    protected string $basePath = '';

    public function __construct(Config $config, ThemeManager $themeManager)
    {
        $this->config = $config;
        $this->themeManager = $themeManager;
    }

    protected function getThemeManager(): ThemeManager
    {
        return $this->themeManager;
    }

    protected function getConfig(): Config
    {
        return $this->config;
    }

    public function setBasePath($basePath): void
    {
        $this->basePath = $basePath;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    protected function getCacheTimestamp(): string
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

    protected function getFaviconHtml(): string
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

    public function display($runScript = null, $htmlFilePath = null, $vars = array()): void
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
        $html = str_replace('{{stylesheet}}', 'client/modules/treo-core/css/treo/treo-classic-theme.css', $html);
        $html = str_replace('{{runScript}}', $runScript, $html);
        $html = str_replace('{{basePath}}', $this->basePath, $html);

        $link = null;

        if (!empty($customStylesheet = $this->getThemeManager()->getGlobalCustomStylesheet())) {
            $link .= '<link href="' . $this->basePath . $customStylesheet . '?r=' . $this->getCacheTimestamp() . '" rel="stylesheet" id="custom-stylesheet">' . PHP_EOL;
        }

        if (!empty($customStylesheet = $this->getThemeManager()->getCustomStylesheet())) {
            $link .= '<link href="' . $this->basePath . $customStylesheet . '?r=' . $this->getCacheTimestamp() . '" rel="stylesheet" id="custom-stylesheet">';
        }

        if (!empty($link)) {
            $html = str_replace('{{customStylesheet}}', $link, $html);
        } else {
            $html = str_replace('{{customStylesheet}}', '', $html);
        }

        if (!empty($customHeadCode = $this->getThemeManager()->getCustomHeadCode())) {
            $html = str_replace('{{customHeadCode}}', $customHeadCode, $html);
        } else {
            $html = str_replace('{{customHeadCode}}', '', $html);
        }

        if ($isDeveloperMode) {
            $html = str_replace('{{useCacheInDeveloperMode}}', $this->getConfig()->get('useCacheInDeveloperMode') ? 'true' : 'false', $html);
        }

        echo $html;
    }
}

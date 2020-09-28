<?php

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
            return (string) time();
        }
        return $this->getConfig()->get('cacheTimestamp', 0);
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
            $html = str_replace('{{'.$key.'}}', $value, $html);
        }
        $html = str_replace('{{applicationName}}', $this->getConfig()->get('applicationName', 'EspoCRM'), $html);
        $html = str_replace('{{cacheTimestamp}}', $this->getCacheTimestamp(), $html);
        $html = str_replace('{{useCache}}', $this->getConfig()->get('useCache') ? 'true' : 'false', $html);
        $html = str_replace('{{stylesheet}}', $this->getThemeManager()->getStylesheet(), $html);
        $html = str_replace('{{runScript}}', $runScript , $html);
        $html = str_replace('{{basePath}}', $this->basePath , $html);
        if ($isDeveloperMode) {
            $html = str_replace('{{useCacheInDeveloperMode}}', $this->getConfig()->get('useCacheInDeveloperMode') ? 'true' : 'false', $html);
        }

        echo $html;
    }
}

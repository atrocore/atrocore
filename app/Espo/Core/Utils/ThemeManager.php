<?php

namespace Espo\Core\Utils;

/**
 * Class ThemeManager
 * @package Espo\Core\Utils
 */
class ThemeManager
{
    protected $config;

    protected $metadata;

    protected $preferences;

    protected $defaultName = 'Espo';

    private $defaultStylesheet = 'Espo';

    /**
     * ThemeManager constructor.
     * @param Config $config
     * @param Metadata $metadata
     */
    public function __construct(Config $config, Metadata $metadata)
    {
        $this->config = $config;
        $this->metadata = $metadata;
    }

    /**
     * Get name theme
     *
     * @return string
     */
    public function getName()
    {
        return $this->config->get('theme', $this->defaultName);
    }

    /**
     * Get stylesheet
     *
     * @return string
     */
    public function getStylesheet()
    {
        return $this->metadata->get('themes.' . $this->getName() . '.stylesheet', 'client/css/espo.css');
    }
}



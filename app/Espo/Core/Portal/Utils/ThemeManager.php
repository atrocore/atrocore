<?php

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



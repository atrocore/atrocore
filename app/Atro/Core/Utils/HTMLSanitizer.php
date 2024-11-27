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

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as BaseHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class HTMLSanitizer
{
    public function sanitize(string $content, string $paramsString): string
    {
        if (empty($content) || empty($paramsString)) {
            return $content;
        }

        $params = $this->parse($paramsString);
        if (empty($params)) {
            return $content;
        }

        $sanitizer = new BaseHtmlSanitizer($this->getConfig($params));

        try {
            $sanitized = $sanitizer->sanitize($content);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("Failed to sanitize HTML content: " . $e->getMessage());
            $sanitized = $content;
        }

        return $sanitized;
    }

    public function parse(string $configuration)
    {
        $result = null;

        try {
            $result = Yaml::parse($configuration);
        } catch (ParseException $e) {
            $GLOBALS['log']->error("Failed to parse HTML sanitizer YAML configuration: " . $e->getMessage());
        }

        if (!is_array($result)) {
            $result = null;
        }

        return $result;
    }

    protected function getConfig(array $params): HtmlSanitizerConfig
    {
        $config = new HtmlSanitizerConfig();

        foreach ($params as $key => $value) {
            $method = Util::toCamelCase($key);

            if (method_exists($this, $method)) {
                $config = $this->{$method}($config, $value);
            }
        }

        return $config;
    }

    protected function blockElements(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        if (is_array($params)) {
            foreach ($params as $tag) {
                if (is_string($tag) && !empty($tag)) {
                    $config = $config->blockElement($tag);
                }
            }
        }

        return $config;
    }

    protected function allowSafeElements(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        if (!empty($params)) {
            $config = $config->allowSafeElements();
        }

        return $config;
    }

    protected function allowStaticElements(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        if (!empty($params)) {
            $config = $config->allowStaticElements();
        }

        return $config;
    }

    protected function allowElements(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        if (is_array($params)) {
            foreach ($params as $tag => $attributes) {
                $config = is_string($attributes) || is_array($attributes) ? $config->allowElement($tag, $attributes) : $config->allowElement($tag);
            }
        }

        return $config;
    }

    protected function dropElements(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        if (is_array($params)) {
            foreach ($params as $tag) {
                if (is_string($tag) && !empty($tag)) {
                    $config = $config->dropElement($tag);
                }
            }
        }

        return $config;
    }

    protected function allowAttributes(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        if (is_array($params)) {
            foreach ($params as $attribute => $tags) {
                if (is_string($tags) || is_array($tags)) {
                    $config = $config->allowAttribute($attribute, $tags);
                }
            }
        }

        return $config;
    }

    protected function dropAttributes(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        if (is_array($params)) {
            foreach ($params as $attribute => $tags) {
                if (is_string($tags) || is_array($tags)) {
                    $config = $config->dropAttribute($attribute, $tags);
                }
            }
        }

        return $config;
    }

    protected function forceAttributes(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        if (is_array($params)) {
            foreach ($params as $tag => $attributes) {
                if (is_array($attributes)) {
                    foreach ($attributes as $attribute => $value) {
                        $config = $config->forceAttribute($tag, $attribute, $value);
                    }
                }
            }
        }

        return $config;
    }

    protected function forceHttpsUrls(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        return $config->forceHttpsUrls((bool)$params);
    }

    protected function allowedLinkSchemes(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        if (is_array($params)) {
            $config = $config->allowLinkSchemes($params);
        }

        return $config;
    }

    protected function allowedLinkHosts(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        if (is_array($params) || is_null($params)) {
            $config = $config->allowLinkHosts($params);
        }

        return $config;
    }

    protected function allowRelativeLinks(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        return $config->allowRelativeLinks((bool)$params);
    }

    protected function allowedMediaSchemes(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        if (is_array($params)) {
            $config = $config->allowMediaSchemes($params);
        }

        return $config;
    }

    protected function allowedMediaHosts(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        if (is_array($params) || is_null($params)) {
            $config = $config->allowMediaHosts($params);
        }

        return $config;
    }

    protected function allowRelativeMedias(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        return $config->allowRelativeMedias((bool)$params);
    }

    protected function maxInputLength(HtmlSanitizerConfig $config, $params): HtmlSanitizerConfig
    {
        if (is_int($params)) {
            $config = $config->withMaxInputLength($params);
        }

        return $config;
    }
}

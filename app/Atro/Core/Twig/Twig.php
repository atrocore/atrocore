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

declare(strict_types=1);

namespace Atro\Core\Twig;

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Container;
use Atro\Core\DataManager;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Metadata;
use Espo\ORM\Entity;

class Twig
{
    protected Container $container;

    protected ?\Twig\Environment $twig = null;
    protected ?\Twig\Loader\ArrayLoader $loader = null;

    /**
     * Handlers resolved on first use in a template, reused for all subsequent renders
     *
     * @var AbstractTwigFilter[]
     */
    protected array $resolvedFilters = [];

    /**
     * @var AbstractTwigFunction[]
     */
    protected array $resolvedFunctions = [];

    protected array $templateData = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function renderTemplate(string $template, array $templateData, string $outputType = 'text')
    {
        $twig = $this->getTwigEnvironment();
        $templateData['config'] = $this->getConfig()->getData();

        foreach (['entity', 'record'] as $key) {
            if (isset($templateData[$key]) && $templateData[$key] instanceof Entity) {
                $this->getAttributeFieldConverter()->putAttributesToEntity($templateData[$key]);
            }
        }

        try {
            $this->templateData = $templateData;
            foreach ($this->resolvedFilters as $filter) {
                $filter->setTemplateData($templateData);
            }
            foreach ($this->resolvedFunctions as $twigFunction) {
                $twigFunction->setTemplateData($templateData);
            }

            // ArrayLoader derives the cache key from the template source, so reusing
            // the name for different scripts cannot collide in the compile cache
            $this->loader->setTemplate('template', $template);

            $res = $twig->render('template', $templateData);
        } catch (\Throwable $e) {
            return 'Error: ' . $e->getMessage();
        }

        $res = trim($res);

        if (strtolower($res) === 'null') {
            return null;
        }

        switch ($outputType) {
            case 'int':
                $res = $res === '' ? null : (int)$res;
                break;
            case 'float':
                $res = $res === '' ? null : (float)$res;
                break;
            case 'bool':
                $res = strtolower($res) === 'true' || $res === '1';
                break;
            case 'date':
                if ($res === '') {
                    $res = null;
                } else {
                    try {
                        $res = (new \DateTime($res))->format('Y-m-d');
                    } catch (\Throwable $e) {
                        $res = null;
                    }
                }
                break;
            case 'datetime':
                if ($res === '') {
                    $res = null;
                } else {
                    try {
                        $res = (new \DateTime($res))->format('Y-m-d H:i:s');
                    } catch (\Throwable $e) {
                        $res = null;
                    }
                }
                break;
        }

        return $res;
    }

    protected function getTwigEnvironment(): \Twig\Environment
    {
        if ($this->twig === null) {
            $this->loader = new \Twig\Loader\ArrayLoader();
            $this->twig = new \Twig\Environment($this->loader, [
                'cache' => new \Twig\Cache\FilesystemCache(DataManager::CACHE_DIR_PATH . '/twig'),
            ]);

            $this->twig->registerUndefinedFilterCallback(function (string $alias) {
                $data = $this->getMetadata()->get(['twig', 'filters', $alias]);
                if ($data === null) {
                    return false;
                }
                $className = is_array($data) ? $data['handler'] : $data;
                $filter = $this->container->get($className);
                if (!$filter instanceof AbstractTwigFilter) {
                    return false;
                }
                $filter->setTemplateData($this->templateData);
                $this->resolvedFilters[$alias] = $filter;

                return new \Twig\TwigFilter($alias, [$filter, 'filter']);
            });

            $this->twig->registerUndefinedFunctionCallback(function (string $alias) {
                $data = $this->getMetadata()->get(['twig', 'functions', $alias]);
                if ($data === null) {
                    return false;
                }
                $className = is_array($data) ? $data['handler'] : $data;
                $twigFunction = $this->container->get($className);
                if (!$twigFunction instanceof AbstractTwigFunction || !method_exists($twigFunction, 'run')) {
                    return false;
                }
                $twigFunction->setTemplateData($this->templateData);
                $this->resolvedFunctions[$alias] = $twigFunction;

                return new \Twig\TwigFunction($alias, [$twigFunction, 'run']);
            });
        }

        return $this->twig;
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getAttributeFieldConverter(): AttributeFieldConverter
    {
        return $this->container->get(AttributeFieldConverter::class);
    }
}
